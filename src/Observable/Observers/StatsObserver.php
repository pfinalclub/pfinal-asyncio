<?php

namespace PfinalClub\Asyncio\Observable\Observers;

use PfinalClub\Asyncio\Observable\Observer;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;
use PfinalClub\Asyncio\Observable\Events\ScopeEvent;
use PfinalClub\Asyncio\Observable\Events\ResourceEvent;
use PfinalClub\Asyncio\Observable\Events\RuntimeStateEvent;

/**
 * 统计观察者 - 收集事件统计信息
 * 
 * @api-stable
 */
class StatsObserver implements Observer
{
    private array $stats = [
        'task' => [
            'created' => 0,
            'started' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ],
        'scope' => [
            'created' => 0,
            'entered' => 0,
            'exited' => 0,
            'cancelled' => 0,
        ],
        'resource' => [
            'registered' => 0,
            'deregistered' => 0,
            'closed' => 0,
            'cancelled' => 0,
            'by_type' => [],
        ],
        'runtime' => [
            'events' => [],
            'start_time' => null,
            'end_time' => null,
        ],
        'timing' => [
            'total_time' => 0,
            'task_durations' => [],
        ],
    ];
    
    /**
     * {@inheritDoc}
     */
    public function onTaskEvent(TaskEvent $event): void
    {
        $task = $event->getTask();
        $taskId = $task->getId();
        
        // 更新任务统计
        $this->stats['task'][$event->getType()]++;
        
        // 记录任务开始时间
        if ($event->isStarted()) {
            $this->stats['timing']['task_durations'][$taskId]['start'] = $event->getTimestamp();
        }
        
        // 计算任务持续时间
        if (($event->isCompleted() || $event->isFailed() || $event->isCancelled()) && 
            isset($this->stats['timing']['task_durations'][$taskId]['start'])) {
            $start = $this->stats['timing']['task_durations'][$taskId]['start'];
            $duration = $event->getTimestamp() - $start;
            $this->stats['timing']['task_durations'][$taskId]['duration'] = $duration;
            $this->stats['timing']['task_durations'][$taskId]['end_type'] = $event->getType();
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function onScopeEvent(ScopeEvent $event): void
    {
        // 更新作用域统计
        $this->stats['scope'][$event->getType()]++;
        
        // 记录运行时开始时间
        if ($event->isCreated() && $this->stats['runtime']['start_time'] === null) {
            $this->stats['runtime']['start_time'] = $event->getTimestamp();
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function onResourceEvent(ResourceEvent $event): void
    {
        $resourceType = $event->getResourceType();
        
        // 更新资源统计
        $this->stats['resource'][$event->getType()]++;
        
        // 更新资源类型统计
        if (!isset($this->stats['resource']['by_type'][$resourceType])) {
            $this->stats['resource']['by_type'][$resourceType] = [
                'registered' => 0,
                'deregistered' => 0,
                'closed' => 0,
                'cancelled' => 0,
            ];
        }
        
        $this->stats['resource']['by_type'][$resourceType][$event->getType()]++;
    }
    
    /**
     * {@inheritDoc}
     */
    public function onRuntimeStateEvent(RuntimeStateEvent $event): void
    {
        $eventType = $event->getType();
        
        // 更新运行时事件统计
        if (!isset($this->stats['runtime']['events'][$eventType])) {
            $this->stats['runtime']['events'][$eventType] = 0;
        }
        
        $this->stats['runtime']['events'][$eventType]++;
        
        // 记录运行时结束时间
        if ($event->isShutdownCompleted()) {
            $this->stats['runtime']['end_time'] = $event->getTimestamp();
            $this->stats['timing']['total_time'] = $event->getTimestamp() - $this->stats['runtime']['start_time'];
        }
    }
    
    /**
     * 获取当前统计信息
     * 
     * @return array 统计信息
     */
    public function getStats(): array
    {
        return $this->stats;
    }
    
    /**
     * 重置统计信息
     * 
     * @return void
     */
    public function resetStats(): void
    {
        $this->stats = [
            'task' => [
                'created' => 0,
                'started' => 0,
                'completed' => 0,
                'failed' => 0,
                'cancelled' => 0,
            ],
            'scope' => [
                'created' => 0,
                'entered' => 0,
                'exited' => 0,
                'cancelled' => 0,
            ],
            'resource' => [
                'registered' => 0,
                'deregistered' => 0,
                'closed' => 0,
                'cancelled' => 0,
                'by_type' => [],
            ],
            'runtime' => [
                'events' => [],
                'start_time' => null,
                'end_time' => null,
            ],
            'timing' => [
                'total_time' => 0,
                'task_durations' => [],
            ],
        ];
    }
    
    /**
     * 获取任务统计信息
     * 
     * @return array 任务统计信息
     */
    public function getTaskStats(): array
    {
        return $this->stats['task'];
    }
    
    /**
     * 获取资源统计信息
     * 
     * @return array 资源统计信息
     */
    public function getResourceStats(): array
    {
        return $this->stats['resource'];
    }
    
    /**
     * 获取运行时统计信息
     * 
     * @return array 运行时统计信息
     */
    public function getRuntimeStats(): array
    {
        return $this->stats['runtime'];
    }
    
    /**
     * 获取时间统计信息
     * 
     * @return array 时间统计信息
     */
    public function getTimingStats(): array
    {
        return $this->stats['timing'];
    }
}