<?php

namespace PfinalClub\Asyncio;

use Fiber;
use Workerman\Timer;
use Workerman\Worker;
use PfinalClub\Asyncio\Monitor\PerformanceMonitor;

/**
 * 事件循环 - 基于 Fiber 的异步调度器
 * 负责调度和执行异步任务
 */
class EventLoop
{
    private static ?EventLoop $instance = null;
    private array $fibers = [];
    private bool $running = false;
    private int $fiberIdCounter = 0;
    private array $timers = [];
    private int $fiberCleanupCounter = 0;
    private const CLEANUP_THRESHOLD = 100;
    private static ?string $eventLoopType = null;
    
    private function __construct()
    {
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
     */
    public function createFiber(callable $callback, string $name = ''): Task
    {
        $fiberId = ++$this->fiberIdCounter;
        $task = new Task($callback, $fiberId, $name ?: "Fiber-{$fiberId}");
        
        // 启动性能监控
        $monitor = PerformanceMonitor::getInstance();
        $monitor->startTask($fiberId, $task->getName());
        
        $fiber = new Fiber(function () use ($task, $callback, $fiberId, $monitor) {
            try {
                $result = $callback();
                $task->setResult($result);
            } catch (\Throwable $e) {
                $task->setException($e);
            } finally {
                // 结束性能监控
                $monitor->endTask($fiberId);
            }
        });
        
        $this->fibers[$fiberId] = [
            'fiber' => $fiber,
            'task' => $task,
            'name' => $task->getName(),
        ];
        
        // 立即启动 Fiber
        if (!$fiber->isStarted()) {
            try {
                $fiber->start();
            } catch (\Throwable $e) {
                $task->setException($e);
            }
        }
        
        // 每 100 个 Fiber 清理一次
        $this->fiberCleanupCounter++;
        if ($this->fiberCleanupCounter >= self::CLEANUP_THRESHOLD) {
            $this->cleanupTerminatedFibers();
            $this->fiberCleanupCounter = 0;
        }
        
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
     * 优化: 在所有任务完成后清理回调，释放内存
     */
    public function gather(array $tasks): array
    {
        if (empty($tasks)) {
            return [];
        }
        
        $currentFiber = Fiber::getCurrent();
        
        if (!$currentFiber) {
            throw new \RuntimeException("gather() can only be called within a Fiber");
        }
        
        $results = [];
        $remaining = count($tasks);
        $hasError = false;
        $error = null;
        
        // 创建回调，但避免捕获不必要的变量
        foreach ($tasks as $index => $task) {
            if (!($task instanceof Task)) {
                throw new \InvalidArgumentException("gather() expects an array of Task objects");
            }
            
            // 使用局部变量减少闭包捕获
            $task->addDoneCallback(function () use ($task, $index, &$results, &$remaining, &$hasError, &$error, $currentFiber, $tasks) {
                if ($hasError) {
                    return;
                }
                
                try {
                    if ($task->hasException()) {
                        $hasError = true;
                        $error = $task->getException();
                    } else {
                        $results[$index] = $task->getResult();
                    }
                } catch (\Throwable $e) {
                    $hasError = true;
                    $error = $e;
                }
                
                $remaining--;
                
                // 所有任务完成，立即恢复 Fiber
                if ($remaining === 0 && $currentFiber->isSuspended()) {
                    try {
                        if ($hasError) {
                            $currentFiber->throw($error);
                        } else {
                            ksort($results);
                            $currentFiber->resume(array_values($results));
                        }
                    } catch (\Throwable $e) {
                        error_log("Error resuming fiber in gather: " . $e->getMessage());
                    }
                }
            });
        }
        
        // 暂停等待所有任务完成
        return Fiber::suspend();
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
     * 清理已终止的 Fiber（按需调用）
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
     */
    public function getActiveFibers(): array
    {
        return $this->fibers;
    }
}
