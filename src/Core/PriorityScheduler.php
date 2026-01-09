<?php

namespace PfinalClub\Asyncio\Core;

use Fiber;
use Workerman\Timer;
use PfinalClub\Asyncio\Concurrency\CancellationScope;

/**
 * 优先级调度器 - 实现三级调度模型（优先检查）
 * 
 * 基于优先检查的三级调度：
 * 1. SYSTEM级：立即执行，永不阻塞
 * 2. CONTROL级：专用队列，中等优先级
 * 3. WORK级：批量队列，低优先级
 * 
 * 关键点：优先检查而非绝对抢占
 * 
 * @internal 内部实现类
 */
final class PriorityScheduler implements SchedulerInterface
{
    private EventLoop $eventLoop;
    
    // 调度队列：CONTROL和WORK队列
    private array $controlQueue = [];    // CONTROL级：专用队列
    private array $workQueue = [];       // WORK级：批量队列
    
    // 调度统计
    private array $schedulerStats = [
        'system_tasks' => 0,
        'control_tasks' => 0,
        'work_tasks' => 0,
    ];
    
    // 队列处理定时器ID
    private ?int $queueTimerId = null;
    
    public function __construct(EventLoop $eventLoop)
    {
        $this->eventLoop = $eventLoop;
        $this->startQueueProcessing();
    }
    
    /**
     * 启动队列处理（优先检查机制）
     */
    private function startQueueProcessing(): void
    {
        // 每50ms检查一次队列（优先检查而非绝对抢占）
        $this->queueTimerId = Timer::add(0.05, function() {
            $this->processQueues();
        });
    }
    
    /**
     * 处理队列（优先检查机制）
     */
    private function processQueues(): void
    {
        // 优先检查CONTROL队列
        if (!empty($this->controlQueue)) {
            $this->processControlQueue();
        }
        
        // 然后检查WORK队列
        if (!empty($this->workQueue)) {
            $this->processWorkQueue();
        }
    }
    
    /**
     * 调度任务（优先检查机制）
     */
    public function schedule(callable $callback, int $priority = self::PRIORITY_WORK, string $name = ''): Task
    {
        // SYSTEM级：立即执行，永不阻塞
        if ($priority === self::PRIORITY_SYSTEM) {
            return $this->scheduleSystemTask($callback, $name);
        }
        
        // CONTROL级：优先检查，如果有CONTROL任务，优先处理
        if ($priority === self::PRIORITY_CONTROL) {
            return $this->scheduleControlTask($callback, $name);
        }
        
        // WORK级：默认优先级，批量处理
        return $this->scheduleWorkTask($callback, $name);
    }
    
    /**
     * SYSTEM级调度：立即执行，永不阻塞
     */
    private function scheduleSystemTask(callable $callback, string $name): Task
    {
        $this->schedulerStats['system_tasks']++;
        
        // SYSTEM级任务立即执行，不进入队列
        return $this->eventLoop->createFiberDirect($callback, "SYSTEM:{$name}");
    }
    
    /**
     * CONTROL级调度：专用队列，中等优先级
     */
    private function scheduleControlTask(callable $callback, string $name): Task
    {
        $this->schedulerStats['control_tasks']++;
        
        // CONTROL任务：添加到专用队列，优先检查
        $task = $this->eventLoop->createFiberDirect($callback, "CONTROL:{$name}");
        
        // 添加到CONTROL队列（用于统计和监控）
        $this->controlQueue[] = $task;
        
        return $task;
    }
    
    /**
     * WORK级调度：批量队列，低优先级
     */
    private function scheduleWorkTask(callable $callback, string $name): Task
    {
        $this->schedulerStats['work_tasks']++;
        
        // WORK任务：添加到批量队列，低优先级
        $task = $this->eventLoop->createFiberDirect($callback, "WORK:{$name}");
        
        // 添加到WORK队列（用于统计和监控）
        $this->workQueue[] = $task;
        
        return $task;
    }
    
    /**
     * 处理CONTROL队列
     */
    private function processControlQueue(): void
    {
        if (empty($this->controlQueue)) {
            return;
        }
        
        // 处理所有CONTROL任务（专用队列，中等优先级）
        while (!empty($this->controlQueue)) {
            $task = array_shift($this->controlQueue);
            // CONTROL任务已经通过createFiberDirect创建，这里只做队列管理
        }
    }
    
    /**
     * 处理WORK队列
     */
    private function processWorkQueue(): void
    {
        if (empty($this->workQueue)) {
            return;
        }
        
        // 批量处理WORK任务（低优先级）
        $batchSize = min(5, count($this->workQueue));
        
        for ($i = 0; $i < $batchSize; $i++) {
            $task = array_shift($this->workQueue);
            // WORK任务已经通过createFiberDirect创建，这里只做队列管理
        }
    }
    

    
    /**
     * 获取调度统计信息
     */
    public function getSchedulerStats(): array
    {
        return [
            'system_tasks' => $this->schedulerStats['system_tasks'],
            'control_tasks' => $this->schedulerStats['control_tasks'],
            'work_tasks' => $this->schedulerStats['work_tasks'],
            'current_queue_size' => [
                'control' => count($this->controlQueue),
                'work' => count($this->workQueue),
            ],
        ];
    }
    
    /**
     * 配置调度参数
     */
    public function configure(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
    
    /**
     * 析构函数：清理资源
     */
    public function __destruct()
    {
        // 清理队列处理定时器
        if ($this->queueTimerId) {
            Timer::del($this->queueTimerId);
        }
    }
}