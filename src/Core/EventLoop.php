<?php

namespace PfinalClub\Asyncio\Core;

use Fiber;
use Workerman\Timer;
use Workerman\Worker;
use PfinalClub\Asyncio\Core\Task;
use PfinalClub\Asyncio\Exception\GatherException;
use PfinalClub\Asyncio\Observable\Observable;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;


/**
 * 事件循环 - 基于 Fiber 的异步调度器
 * 负责调度和执行异步任务
 * 
 * @internal 这是内部实现类，用户应通过 EventLoopInterface 操作
 */
final class EventLoop implements EventLoopInterface
{
    private static ?EventLoop $instance = null;
    private array $fibers = [];
    private bool $running = false;
    private int $fiberIdCounter = 0;
    private array $timers = [];
    private int $fiberCleanupCounter = 0;
    private const CLEANUP_THRESHOLD = 50; // 降低阈值到50，提高清理频率
    private static ?string $eventLoopType = null;
    
    // 延迟清理池 - 存储已终止的Fiber ID（语义已死）
    private array $deferredCleanupPool = [];
    private const DEFERRED_POOL_MAX_SIZE = 50;
    
    // 清理统计（O(1)操作）
    private array $cleanupStats = [
        'high_freq_cleanups' => 0,
        'low_freq_cleanups' => 0,
        'memory_pressure_triggers' => 0, // 只记录触发次数，不决定清理
        'fibers_cleaned_total' => 0,
        'peak_fiber_count' => 0,
        'last_cleanup_time' => 0.0,
    ];
    
    // 内存压力检测（仅触发策略）
    private int $lastMemoryCheck = 0;
    private const MEMORY_CHECK_INTERVAL = 5;
    
    // 三级调度器
    private ?PriorityScheduler $scheduler = null;
    
    private function __construct()
    {
        // 初始化三级调度器
        $this->scheduler = new PriorityScheduler($this);
    }
    
    /**
     * 自动选择最优事件循环
     * 优先级: Ev (libev) > Event (libevent) > Select
     * 
     * @return \Workerman\Events\EventInterface
     */
    private function selectBestEventLoop(): \Workerman\Events\EventInterface
    {
        // Ev - 最高性能 (libev)
        if (extension_loaded('ev')) {
            self::$eventLoopType = 'Ev';
            return new \Workerman\Events\Ev();
        }
        
        // Event - 高性能 (libevent)
        if (extension_loaded('event')) {
            self::$eventLoopType = 'Event';
            return new \Workerman\Events\Event();
        }
        
        // Select - 基础性能（兜底方案）
        self::$eventLoopType = 'Select';
        return new \Workerman\Events\Select();
    }
    
    /**
     * 获取当前使用的事件循环类型
     * 
     * @return string|null
     */
    public static function getEventLoopType(): ?string
    {
        return self::$eventLoopType;
    }
    
    /**
     * 打印事件循环性能提示
     */
    private function printEventLoopInfo(): void
    {
        $tips = [
            'Ev' => '🚀 使用 Ev (libev) 事件循环 - 最佳性能 (100K+ 并发)',
            'Event' => '⚡ 使用 Event (libevent) 事件循环 - 高性能 (10K+ 并发)',
            'Select' => '⚠️  使用 Select 事件循环 - 基础性能 (<1K 并发)'
        ];
        
        echo $tips[self::$eventLoopType] . "\n";
        
        // 如果使用 Select，提示安装更高性能的扩展
        if (self::$eventLoopType === 'Select') {
            echo "💡 提示: 安装 ev 或 event 扩展可提升性能 10-100 倍:\n";
            echo "   pecl install ev      # 推荐，最高性能\n";
            echo "   pecl install event   # 次选，高性能\n";
        }
        echo "\n";
    }
    
    /**
     * 获取事件循环单例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 创建并启动新的 Fiber 任务
     * 
     * @deprecated 建议使用 schedule() 方法进行三级调度
     */
    public function createFiber(callable $callback, string $name = ''): Task
    {
        // 默认使用WORK级优先级进行调度
        return $this->schedule($callback, SchedulerInterface::PRIORITY_WORK, $name);
    }
    
    /**
     * 三级调度：基于优先级调度任务
     * 
     * @param callable $callback 要执行的回调函数
     * @param int $priority 优先级（SYSTEM/CONTROL/WORK）
     * @param string $name 任务名称
     * @return Task 创建的任务对象
     */
    public function schedule(callable $callback, int $priority = SchedulerInterface::PRIORITY_WORK, string $name = ''): Task
    {
        return $this->scheduler->schedule($callback, $priority, $name);
    }
    
    /**
     * 获取调度器实例
     * 
     * @return PriorityScheduler 调度器实例
     */
    public function getScheduler(): PriorityScheduler
    {
        return $this->scheduler;
    }
    
    /**
     * 内部方法：直接创建Fiber（不经过调度器）
     * 用于SYSTEM级任务和调度器内部使用
     */
    public function createFiberDirect(callable $callback, string $name = ''): Task
    {
        $fiberId = ++$this->fiberIdCounter;
        $task = new Task($callback, $fiberId, $name ?: "Fiber-{$fiberId}");
        
        $fiber = new Fiber(function () use ($task, $callback) {
            try {
                $result = $callback();
                $task->setResult($result);
            } catch (\Throwable $e) {
                $task->setException($e);
            }
        });
        
        // 获取当前 Fiber（父 Fiber）
        $currentFiber = Fiber::getCurrent();
        $parentFiberId = $currentFiber ? spl_object_id($currentFiber) : 0;
        
        // 获取新 Fiber 的实际 ID
        $newFiberId = spl_object_id($fiber);
        
        // 设置父子 Fiber 关系，用于上下文继承
        \PfinalClub\Asyncio\Resource\Context::setParent($newFiberId, $parentFiberId);
        
        $this->fibers[$fiberId] = [
            'fiber' => $fiber,
            'task' => $task,
            'name' => $task->getName(),
        ];
        
        // 立即启动 Fiber
        if (!$fiber->isStarted()) {
            try {
                // 发送任务创建事件
                if (Observable::getInstance()->isEnabled()) {
                    Observable::getInstance()->emitTaskEvent(
                        new TaskEvent(TaskEvent::CREATED, $task)
                    );
                }
                
                // 标记任务为运行中
                $task->markAsRunning();
                $fiber->start();
            } catch (\Throwable $e) {
                $task->setException($e);
            }
        }
        
        // 执行智能清理检查
        $this->performSmartCleanupCheck();
        
        return $task;
    }
    
    /**
     * 异步睡眠 - 暂停当前 Fiber
     * 使用 Workerman Timer 实现精确的事件驱动调度
     */
    public function sleep(float $seconds): void
    {
        $currentFiber = Fiber::getCurrent();
        
        if (!$currentFiber) {
            // 不在 Fiber 中，使用阻塞睡眠
            usleep((int)($seconds * 1000000));
            return;
        }
        
        // 直接创建 Timer，事件驱动，无需轮询
        Timer::add($seconds, function () use ($currentFiber) {
            if ($currentFiber->isSuspended()) {
                try {
                    $currentFiber->resume();
            } catch (\Throwable $e) {
                    error_log("Error resuming fiber after sleep: " . $e->getMessage());
                }
            }
        }, [], false); // false = 只执行一次
        
        // 暂停当前 Fiber
        Fiber::suspend();
    }
    
    /**
     * 等待任务完成
     * 直接在回调中恢复 Fiber，无延迟
     */
    public function await(Task $task): mixed
    {
        $currentFiber = Fiber::getCurrent();
        
        if (!$currentFiber) {
            throw new \RuntimeException("await() can only be called within a Fiber");
        }
        
        // 如果任务已完成，直接返回结果
        if ($task->isDone()) {
            return $task->getResult();
        }
        
        // 设置回调，当任务完成时立即恢复 Fiber
        $task->addDoneCallback(function () use ($currentFiber, $task) {
            if ($currentFiber->isSuspended()) {
                try {
                    if ($task->hasException()) {
                        $currentFiber->throw($task->getException());
                        } else {
                        $currentFiber->resume($task->getResult());
                    }
                    } catch (\Throwable $e) {
                    error_log("Error resuming fiber in await: " . $e->getMessage());
                }
            }
        });
        
        // 暂停当前 Fiber，等待任务完成
        return Fiber::suspend();
    }
    
    /**
     * 等待多个任务完成（gather）
     * 直接在回调中恢复 Fiber，无延迟
     * 
     * 改进:
     * - 支持不同的 gather 策略
     * - 收集所有异常，不只是第一个
     * - 抛出 GatherException 包含详细错误信息
     * - 在所有任务完成后清理回调，释放内存
     * - 保留成功任务的结果
     * 
     * @param array $tasks 任务数组
     * @param \PfinalClub\Asyncio\Concurrency\GatherStrategy $strategy 收集策略
     * @return array 所有任务的结果数组
     * @throws GatherException 如果有任务失败
     */
    public function gather(array $tasks, \PfinalClub\Asyncio\Concurrency\GatherStrategy $strategy = \PfinalClub\Asyncio\Concurrency\GatherStrategy::FAIL_FAST): array
    {
        if (empty($tasks)) {
            return [];
        }
        
        $currentFiber = Fiber::getCurrent();
        
        if (!$currentFiber) {
            throw new \RuntimeException("gather() can only be called within a Fiber");
        }
        
        $results = [];
        $exceptions = [];
        $taskNames = [];
        $remaining = count($tasks);
        $firstException = null;
        
        // 记录所有任务ID，用于快速访问
        $taskIds = [];
        foreach ($tasks as $index => $task) {
            if (!($task instanceof Task)) {
                throw new \InvalidArgumentException("gather() expects an array of Task objects");
            }
            
            $taskNames[$index] = $task->getName();
            $taskIds[$task->getId()] = $index;
        }
        
        // 创建闭包时减少捕获的变量数量
        // 使用弱引用来避免阻止Fiber回收
        $fiberWeakRef = WeakReference::create($currentFiber);
        
        // 为快速失败策略创建任务取消函数，避免闭包捕获整个tasks数组
        $cancelRemainingTasks = null;
        if ($strategy === \PfinalClub\Asyncio\Concurrency\GatherStrategy::FAIL_FAST) {
            $cancelRemainingTasks = function () use ($tasks) {
                foreach ($tasks as $otherTask) {
                    if (!$otherTask->isDone()) {
                        $otherTask->cancel();
                    }
                }
            };
        }
        
        // 策略4：添加弱引用监控 - 观测闭包内存使用情况
        // 仅在开发环境或调试模式下启用
        $gatherStartTime = microtime(true);
        $initialMemory = memory_get_usage(true);
        
        // 创建回调，收集所有结果和异常
        foreach ($tasks as $index => $task) {
            // 使用局部变量减少闭包捕获
            $taskId = $task->getId();
            
            $task->addDoneCallback(function () use ( 
                $task, 
                $index, 
                $taskId, 
                &$results, 
                &$exceptions, 
                &$remaining, 
                $fiberWeakRef, 
                &$firstException, 
                $strategy, 
                $cancelRemainingTasks,
                $taskNames
            ) {
                try {
                    if ($task->hasException()) {
                        $exceptions[$index] = $task->getException();
                        
                        // 快速失败策略 - 一旦有异常，立即取消所有其他任务
                        if ($strategy === \PfinalClub\Asyncio\Concurrency\GatherStrategy::FAIL_FAST && $firstException === null) {
                            $firstException = $task->getException();
                            
                            // 取消所有其他未完成的任务
                            if ($cancelRemainingTasks) {
                                $cancelRemainingTasks();
                            }
                        }
                    } else {
                        $results[$index] = $task->getResult();
                    }
                } catch (\Throwable $e) {
                    $exceptions[$index] = $e;
                }
                
                $remaining--;
                
                // 所有任务完成，立即恢复 Fiber
                if ($remaining === 0) {
                    $fiber = $fiberWeakRef->get();
                    if ($fiber && $fiber->isSuspended()) {
                        try {
                            // 准备返回结果，使用局部变量避免闭包引用
                            $returnResults = $results;
                            $returnExceptions = $exceptions;
                            $returnTaskNames = $taskNames;
                            
                            // 主动断开引用链，加速内存回收
                            $results = [];
                            $exceptions = [];
                            $firstException = null;
                            
                            if (!empty($returnExceptions)) {
                                // 根据策略处理异常
                                if ($strategy === \PfinalClub\Asyncio\Concurrency\GatherStrategy::RETURN_PARTIAL) {
                                    // 返回部分结果，不抛出异常
                                    ksort($returnResults);
                                    $fiber->resume(array_values($returnResults));
                                } else {
                                    // 抛出聚合异常，包含所有失败和成功的信息
                                    $fiber->throw(
                                        new GatherException($returnExceptions, $returnResults, $returnTaskNames)
                                    );
                                }
                            } else {
                                ksort($returnResults);
                                $fiber->resume(array_values($returnResults));
                            }
                        } catch (\Throwable $e) {
                            // 记录严重错误，但不吞掉异常
                            error_log("[CRITICAL] Error resuming fiber in gather: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                            // 重新抛出，确保错误不会被忽略
                            throw $e;
                        }
                    }
                }
            });
        }
        
        // 暂停等待所有任务完成
        $result = Fiber::suspend();
        
        // 策略4：弱引用监控 - 报告内存使用情况
        // 仅在开发环境或调试模式下启用
        if (defined('PFINAL_ASYNCIO_DEBUG') && PFINAL_ASYNCIO_DEBUG) {
            $gatherEndTime = microtime(true);
            $finalMemory = memory_get_usage(true);
            $memoryUsed = $finalMemory - $initialMemory;
            $duration = $gatherEndTime - $gatherStartTime;
            
            // 使用error_log避免影响正常输出
            error_log(
                "[PFINAL_ASYNCIO_DEBUG] gather() 监控: " .
                "耗时=" . round($duration * 1000, 2) . "ms, " .
                "内存使用=" . round($memoryUsed / 1024 / 1024, 2) . "MB, " .
                "任务数=" . count($taskIds) . ", " .
                "策略=" . $strategy->name
            );
        }
        
        // 主动清理，断开引用链
        unset($tasks, $results, $exceptions, $taskNames, $taskIds, $fiberWeakRef, $cancelRemainingTasks);
        
        return $result;
    }
    
    /**
     * 运行主协程
     * 完全事件驱动，无轮询
     * 
     * 此方法有两种执行路径：
     * 
     * 1. **新事件循环模式** (Worker::$globalEvent === null)：
     *    - 创建新的 Workerman 事件循环
     *    - 自动选择最优事件循环（Ev > Event > Select）
     *    - 执行主任务后停止事件循环
     *    - 适用场景：单进程应用、CLI 脚本、开发环境
     * 
     * 2. **已有事件循环模式** (Worker::$globalEvent !== null)：
     *    - 复用已存在的事件循环（通常在 Worker 进程中）
     *    - 使用事件驱动方式等待任务完成
     *    - 不会停止外部事件循环
     *    - 适用场景：MultiProcessMode、Workerman Worker 进程、嵌套异步环境
     *    - 注意：此模式使用短暂 usleep(100μs) 让出 CPU，让事件循环处理事件
     * 
     * @param callable $main 主协程函数
     * @return mixed 主协程的返回值
     * @throws \RuntimeException 如果在 Fiber 内部调用（嵌套调用）
     * 
     * @example
     * ```php
     * // 模式 1: 单进程应用
     * $result = run(function() {
     *     return "hello";
     * });
     * 
     * // 模式 2: MultiProcessMode（内部会调用 run）
     * MultiProcessMode::enable(function() {
     *     // 这里的代码在每个 Worker 进程中执行
     *     // Worker 进程已经有事件循环，所以这里走的是模式 2
     * });
     * ```
     */
    public function run(callable $main): mixed
    {
        // 检测嵌套调用：如果在 Fiber 中调用 run()，抛出异常
        if (\Fiber::getCurrent() !== null) {
            throw new \RuntimeException(
                "Cannot call run() from within a Fiber context. " .
                "Use create_task() or await() instead for nested async operations."
            );
        }
        
        $this->running = true;
        
        // 使用 Workerman 事件循环（纯事件驱动）
        if (!Worker::$globalEvent) {
            // 自动选择最优事件循环（Ev > Event > Select）
            Worker::$globalEvent = $this->selectBestEventLoop();
            Timer::init(Worker::$globalEvent);
            
            // 打印事件循环信息
            $this->printEventLoopInfo();
            
            // 创建主 Fiber（此时事件循环已初始化）
            $mainTask = $this->createFiber($main, 'main');
            
            // 设置完成回调，直接停止事件循环
            $mainTask->addDoneCallback(function () {
                $this->running = false;
                
                // 使用极短延迟确保当前回调栈完成后停止
                // 避免在回调执行过程中停止导致问题
                Timer::add(0.001, function () {
                    Timer::delAll();
                    Worker::stopAll();
                }, [], false);  // false = 只执行一次
            });
            
            // 直接使用事件循环（不使用 Worker::runAll()）
            // Worker::runAll() 是为多进程服务器设计的，这里我们只需要事件循环
            Worker::$globalEvent->loop();
            
        } else {
            // 事件循环已经在运行（通常是在 Worker 进程中）
            // 创建任务并使用事件驱动方式等待
            $mainTask = $this->createFiber($main, 'main');
            
            // 如果任务已经完成，直接返回
            if ($mainTask->isDone()) {
                $result = $mainTask->getResult();
                $this->cleanupTerminatedFibers();
                return $result;
            }
            
            // 使用事件驱动方式等待任务完成
            $completed = false;
            $taskResult = null;
            $taskError = null;
            
            $mainTask->addDoneCallback(function () use (&$completed, &$taskResult, &$taskError, $mainTask) {
                $completed = true;
                try {
                    $taskResult = $mainTask->getResult();
                } catch (\Throwable $e) {
                    $taskError = $e;
                }
            });
            
            // 等待任务完成（事件驱动，不轮询）
            // 注意：这里依赖于已存在的事件循环会继续运行
            // 通常这种情况只会在测试或特殊场景中出现
            while (!$completed) {
                // 让出 CPU，让事件循环有机会处理事件
                // 这里使用短暂的 usleep 是必要的，因为我们不在 Fiber 中
                usleep(100); // 0.1ms - 比之前的 1ms 快 10 倍
            }
            
            if ($taskError !== null) {
                throw $taskError;
            }
            
            $this->cleanupTerminatedFibers();
            return $taskResult;
        }
        
        $result = $mainTask->getResult();
        $this->cleanupTerminatedFibers(); // 清理所有已终止的 Fiber
        return $result;
    }
    
    /**
     * 清理已终止的 Fiber（按需调用）- 只清理语义已死的Fiber
     */
    private function cleanupTerminatedFibers(): void
    {
        foreach ($this->fibers as $fiberId => $info) {
            if ($info['fiber']->isTerminated()) {
                unset($this->fibers[$fiberId]);
            }
        }
    }
    
    /**
     * 高频清理：热路径只做O(1)/O(k)工作
     * 快速扫描并标记已终止的Fiber，延迟清理
     */
    private function highFrequencyCleanup(): void
    {
        $this->cleanupStats['high_freq_cleanups']++;
        
        // O(k)操作：快速扫描已终止的Fiber（k=当前活跃Fiber数）
        $terminatedCount = 0;
        $scannedCount = 0;
        foreach ($this->fibers as $fiberId => $info) {
            $scannedCount++;
            
            // 只清理语义已死的Fiber
            if ($info['fiber']->isTerminated()) {
                // 避免重复加入延迟清理池
                if (!in_array($fiberId, $this->deferredCleanupPool, true)) {
                    $this->deferredCleanupPool[] = $fiberId;
                    $terminatedCount++;
                }
            }
            
            // 限制扫描数量，避免O(n)操作（最多扫描50个）
            if ($scannedCount >= 50) {
                break;
            }
        }
        
        // 延迟清理：池满时批量清理
        if (count($this->deferredCleanupPool) >= self::DEFERRED_POOL_MAX_SIZE) {
            $this->processDeferredCleanupPool();
        }
    }
    
    /**
     * 处理延迟清理池：批量清理语义已死的Fiber
     */
    private function processDeferredCleanupPool(): void
    {
        $cleanedCount = 0;
        foreach ($this->deferredCleanupPool as $fiberId) {
            if (isset($this->fibers[$fiberId]) && $this->fibers[$fiberId]['fiber']->isTerminated()) {
                unset($this->fibers[$fiberId]);
                $cleanedCount++;
            }
        }
        
        $this->deferredCleanupPool = [];
        $this->cleanupStats['fibers_cleaned_total'] += $cleanedCount;
        $this->cleanupStats['last_cleanup_time'] = microtime(true);
    }
    
    /**
     * 内存压力检测：仅触发策略，不决定对象生死
     */
    private function hasMemoryPressure(): bool
    {
        // 基于Fiber数量压力检测
        $currentCount = count($this->fibers);
        $peakCount = $this->cleanupStats['peak_fiber_count'];
        
        // 如果当前Fiber数量超过峰值80%，认为有压力
        if ($peakCount > 0 && $currentCount > $peakCount * 0.8) {
            return true;
        }
        
        // 基于PHP内存使用压力检测
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit !== '-1') {
            $memoryUsage = memory_get_usage(true);
            $limitBytes = $this->parseMemoryLimit($memoryLimit);
            
            // 内存使用超过80%时触发策略
            if ($memoryUsage > $limitBytes * 0.8) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 触发内存压力清理策略：仅触发，不强制清理
     */
    private function triggerMemoryPressureCleanup(): void
    {
        // 内存压力下，更频繁地处理延迟清理池
        $this->processDeferredCleanupPool();
        
        // 触发低频深度清理（后台执行）
        Timer::add(0.1, function() { // 100ms后执行
            $this->lowFrequencyDeepCleanup();
        }, [], false);
    }
    
    /**
     * 低频深度清理：后台执行，不影响热路径
     */
    private function lowFrequencyDeepCleanup(): void
    {
        $this->cleanupStats['low_freq_cleanups']++;
        
        // 深度扫描所有Fiber，清理语义已死的
        $cleanedCount = 0;
        foreach ($this->fibers as $fiberId => $info) {
            if ($info['fiber']->isTerminated()) {
                unset($this->fibers[$fiberId]);
                $cleanedCount++;
            }
        }
        
        $this->cleanupStats['fibers_cleaned_total'] += $cleanedCount;
        $this->cleanupStats['last_cleanup_time'] = microtime(true);
    }
    
    /**
     * 解析内存限制字符串为字节数
     */
    private function parseMemoryLimit(string $limit): int
    {
        $value = (int)$limit;
        $unit = strtoupper(substr($limit, -1));
        
        return match($unit) {
            'G' => $value * 1024 * 1024 * 1024,
            'M' => $value * 1024 * 1024,
            'K' => $value * 1024,
            default => $value,
        };
    }
    
    /**
     * 执行智能清理检查
     */
    private function performSmartCleanupCheck(): void
    {
        // 热路径优化：只做O(1)操作
        $this->fiberCleanupCounter++;
        
        // O(1)统计：更新峰值Fiber数量
        $this->cleanupStats['peak_fiber_count'] = max(
            $this->cleanupStats['peak_fiber_count'], 
            count($this->fibers)
        );
        
        // 高频清理：每50个任务创建时触发快速扫描
        if ($this->fiberCleanupCounter >= self::CLEANUP_THRESHOLD) {
            $this->highFrequencyCleanup();
            $this->fiberCleanupCounter = 0;
        }
        
        // 内存压力检测：仅触发策略，不决定清理（降低检测频率）
        $currentTime = time();
        if ($currentTime - $this->lastMemoryCheck >= self::MEMORY_CHECK_INTERVAL) {
            $this->lastMemoryCheck = $currentTime;
            
            // 只在有活跃Fiber时检测内存压力
            if (!empty($this->fibers) && $this->hasMemoryPressure()) {
                $this->cleanupStats['memory_pressure_triggers']++;
                // 仅触发策略，清理仍基于Fiber状态
                $this->triggerMemoryPressureCleanup();
            }
        }
    }
    
    /**
     * 获取清理统计信息
     */
    public function getCleanupStats(): array
    {
        return $this->cleanupStats;
    }
    
    /**
     * 添加定时器
     */
    public function addTimer(float $interval, callable $callback, bool $persistent = true): int
    {
        $timerId = Timer::add($interval, $callback, [], $persistent);
        $this->timers[$timerId] = $timerId;
        return $timerId;
    }
    
    /**
     * 删除定时器
     */
    public function delTimer(int $timerId): void
    {
        Timer::del($timerId);
        unset($this->timers[$timerId]);
    }
    
    /**
     * 停止事件循环
     */
    public function stop(): void
    {
        $this->running = false;
        Worker::stopAll();
    }
    
    /**
     * 获取所有活跃的 Fiber
     * 
     * @internal 这是内部实现方法，仅供内部使用
     * @return array Fiber 信息数组
     */
    public function getActiveFibers(): array
    {
        return $this->fibers;
    }
}
