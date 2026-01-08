<?php

namespace PfinalClub\Asyncio\Observable\Observers;

use PfinalClub\Asyncio\Observable\Observer;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;
use PfinalClub\Asyncio\Observable\Events\ScopeEvent;
use PfinalClub\Asyncio\Observable\Events\ResourceEvent;
use PfinalClub\Asyncio\Observable\Events\RuntimeStateEvent;

/**
 * 调用链观察者 - 跟踪任务调用链
 * 
 * @api-stable
 */
class TraceObserver implements Observer
{
    private array $traces = [];
    private array $currentTrace = [];
    private array $taskToTraceId = [];
    private int $traceCounter = 0;
    
    /**
     * {@inheritDoc}
     */
    public function onTaskEvent(TaskEvent $event): void
    {
        $task = $event->getTask();
        $taskId = $task->getId();
        $taskName = $task->getName();
        $eventType = $event->getType();
        
        // 创建任务跟踪记录
        if ($event->isCreated()) {
            $traceId = ++$this->traceCounter;
            $this->taskToTraceId[$taskId] = $traceId;
            
            $this->traces[$traceId] = [
                'id' => $traceId,
                'task_id' => $taskId,
                'task_name' => $taskName,
                'created_at' => $event->getTimestamp(),
                'events' => [$eventType => $event->getTimestamp()],
                'parent_trace_id' => $this->getCurrentTraceId(),
                'stack' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),
                'exception' => null,
            ];
            
            // 设置当前跟踪
            $this->currentTrace[] = $traceId;
        } 
        // 更新任务事件
        else {
            $traceId = $this->taskToTraceId[$taskId] ?? null;
            if ($traceId && isset($this->traces[$traceId])) {
                $this->traces[$traceId]['events'][$eventType] = $event->getTimestamp();
                
                // 记录异常信息
                if ($event->isFailed() && $event->getException()) {
                    $exception = $event->getException();
                    $this->traces[$traceId]['exception'] = [
                        'message' => $exception->getMessage(),
                        'type' => get_class($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => $exception->getTraceAsString(),
                    ];
                }
                
                // 移除当前跟踪
                if ($event->isCompleted() || $event->isFailed() || $event->isCancelled()) {
                    $lastTrace = array_pop($this->currentTrace);
                    if ($lastTrace !== $traceId) {
                        // 跟踪栈不匹配，可能是异步执行导致的
                        array_push($this->currentTrace, $lastTrace);
                    }
                }
            }
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function onScopeEvent(ScopeEvent $event): void
    {
        // 作用域事件可以在这里处理，用于跟踪作用域的进入和退出
    }
    
    /**
     * {@inheritDoc}
     */
    public function onResourceEvent(ResourceEvent $event): void
    {
        // 资源事件可以在这里处理，用于跟踪资源的创建和销毁
    }
    
    /**
     * {@inheritDoc}
     */
    public function onRuntimeStateEvent(RuntimeStateEvent $event): void
    {
        // 运行时事件可以在这里处理，用于跟踪运行时状态变化
    }
    
    /**
     * 获取当前跟踪 ID
     * 
     * @return int|null 当前跟踪 ID，如果没有则返回 null
     */
    private function getCurrentTraceId(): ?int
    {
        return end($this->currentTrace) ?: null;
    }
    
    /**
     * 获取所有跟踪记录
     * 
     * @return array 所有跟踪记录
     */
    public function getTraces(): array
    {
        return $this->traces;
    }
    
    /**
     * 获取指定任务的跟踪记录
     * 
     * @param int $taskId 任务 ID
     * @return array|null 跟踪记录，如果没有则返回 null
     */
    public function getTraceByTaskId(int $taskId): ?array
    {
        $traceId = $this->taskToTraceId[$taskId] ?? null;
        return $traceId ? $this->traces[$traceId] : null;
    }
    
    /**
     * 获取指定跟踪 ID 的跟踪记录
     * 
     * @param int $traceId 跟踪 ID
     * @return array|null 跟踪记录，如果没有则返回 null
     */
    public function getTraceById(int $traceId): ?array
    {
        return $this->traces[$traceId] ?? null;
    }
    
    /**
     * 获取调用链树结构
     * 
     * @return array 调用链树结构
     */
    public function getTraceTree(): array
    {
        $tree = [];
        
        // 首先创建根节点
        foreach ($this->traces as $trace) {
            if ($trace['parent_trace_id'] === null) {
                $tree[$trace['id']] = $this->buildTraceNode($trace);
            }
        }
        
        // 然后添加子节点
        foreach ($this->traces as $trace) {
            if ($trace['parent_trace_id'] !== null && isset($tree[$trace['parent_trace_id']])) {
                $tree[$trace['parent_trace_id']]['children'][] = $this->buildTraceNode($trace);
            }
        }
        
        return array_values($tree);
    }
    
    /**
     * 构建跟踪节点
     * 
     * @param array $trace 跟踪记录
     * @return array 跟踪节点
     */
    private function buildTraceNode(array $trace): array
    {
        return [
            'id' => $trace['id'],
            'task_id' => $trace['task_id'],
            'task_name' => $trace['task_name'],
            'created_at' => $trace['created_at'],
            'events' => $trace['events'],
            'has_exception' => $trace['exception'] !== null,
            'children' => [],
        ];
    }
    
    /**
     * 重置跟踪记录
     * 
     * @return void
     */
    public function resetTraces(): void
    {
        $this->traces = [];
        $this->currentTrace = [];
        $this->taskToTraceId = [];
        $this->traceCounter = 0;
    }
    
    /**
     * 获取当前跟踪栈
     * 
     * @return array 当前跟踪栈
     */
    public function getCurrentTraceStack(): array
    {
        return $this->currentTrace;
    }
}