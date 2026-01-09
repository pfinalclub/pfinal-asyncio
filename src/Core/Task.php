<?php

namespace PfinalClub\Asyncio\Core;

use PfinalClub\Asyncio\Exception\TaskCancelledException;
use PfinalClub\Asyncio\Concurrency\CancellationScope;
use PfinalClub\Asyncio\Observable\Observable;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;

/**
 * 任务 - 对 Fiber 的封装
 * 
 * @template T
 */
class Task
{
    private mixed $callable;
    private int $id;
    private string $name;
    private TaskState $state;
    private ?CancellationScope $scope = null;
    private mixed $result = null;
    private ?\Throwable $exception = null;
    private array $callbacks = [];
    private float $createdAt;
    private ?float $startedAt = null;
    private ?float $completedAt = null;
    
    public function __construct(callable $callable, int $id, string $name)
    {
        $this->callable = $callable;
        $this->id = $id;
        $this->name = $name;
        $this->state = TaskState::PENDING;
        $this->createdAt = microtime(true);
    }
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getCallable(): callable
    {
        return $this->callable;
    }
    
    public function isDone(): bool
    {
        return $this->state->isTerminal();
    }
    
    /**
     * 设置任务状态，验证转换合法性
     * 
     * @param TaskState $newState 新状态
     * @throws \RuntimeException 如果状态转换不合法
     */
    private function setState(TaskState $newState): void
    {
        if (!$this->state->canTransitionTo($newState)) {
            throw new \RuntimeException(
                "Invalid state transition from {$this->state->value} to {$newState->value} for task {$this->name}"
            );
        }
        $this->state = $newState;
    }
    
    public function setResult(mixed $result): void
    {
        $this->result = $result;
        $this->setState(TaskState::COMPLETED);
        $this->completedAt = microtime(true);
        $this->runCallbacks();
        
        // 开发模式：检测孤儿 Task（没有 Scope 的 Task）
        if ($this->scope === null && (getenv('ASYNCIO_DEBUG') || defined('ASYNCIO_DEBUG'))) {
            error_log(
                "WARNING: Orphan Task detected - Task '{$this->name}' (#{$this->id}) completed without a CancellationScope. " .
                "This may indicate a resource leak. Ensure all tasks are created within a CancellationScope::run() context."
            );
        }
        
        // 发送可观测性事件
        if (Observable::getInstance()->isEnabled()) {
            Observable::getInstance()->emitTaskEvent(
                new TaskEvent(TaskEvent::COMPLETED, $this)
            );
        }
    }
    
    public function setException(\Throwable $exception): void
    {
        $this->exception = $exception;
        $isCancelled = $exception instanceof TaskCancelledException;
        $this->setState(
            $isCancelled 
                ? TaskState::CANCELLED 
                : TaskState::FAILED
        );
        $this->completedAt = microtime(true);
        $this->runCallbacks();
        
        // 开发模式：检测孤儿 Task（没有 Scope 的 Task，但排除取消的 Task）
        if (!$isCancelled && $this->scope === null && (getenv('ASYNCIO_DEBUG') || defined('ASYNCIO_DEBUG'))) {
            error_log(
                "WARNING: Orphan Task detected - Task '{$this->name}' (#{$this->id}) failed without a CancellationScope. " .
                "This may indicate a resource leak. Ensure all tasks are created within a CancellationScope::run() context."
            );
        }
        
        // 发送可观测性事件
        if (Observable::getInstance()->isEnabled()) {
            $eventType = $isCancelled ? TaskEvent::CANCELLED : TaskEvent::FAILED;
            Observable::getInstance()->emitTaskEvent(
                new TaskEvent($eventType, $this, $exception)
            );
        }
    }
    
    /**
     * 设置任务的 CancellationScope
     * 
     * @param CancellationScope $scope
     */
    public function setScope(CancellationScope $scope): void
    {
        $this->scope = $scope;
    }
    
    /**
     * 获取任务的 CancellationScope
     * 
     * @return CancellationScope|null
     */
    public function getScope(): ?CancellationScope
    {
        return $this->scope;
    }
    
    /**
     * @return T
     */
    public function getResult(): mixed
    {
        if (!$this->state->isTerminal()) {
            throw new \RuntimeException(
                "Task {$this->name} not completed yet (state: {$this->state->value})"
            );
        }
        
        if ($this->exception) {
            throw $this->exception;
        }
        
        return $this->result;
    }
    
    public function hasException(): bool
    {
        return $this->exception !== null;
    }
    
    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
    
    public function addDoneCallback(callable $callback): void
    {
        if ($this->isDone()) {
            $callback($this);
        } else {
            $this->callbacks[] = $callback;
        }
    }
    
    private function runCallbacks(): void
    {
        foreach ($this->callbacks as $callback) {
            try {
                $callback($this);
            } catch (\Throwable $e) {
                // 记录回调错误，但不影响任务状态
                error_log("Callback error in task {$this->name}: " . $e->getMessage());
            }
        }
        $this->callbacks = [];
    }
    
    public function cancel(): bool
    {
        if ($this->state->isTerminal()) {
            return false;
        }
        
        $this->setException(new TaskCancelledException("Task {$this->name} cancelled"));
        return true;
    }
    
    /**
     * 获取任务状态
     */
    public function getState(): TaskState
    {
        return $this->state;
    }
    
    /**
     * 标记任务开始运行
     * 
     * @internal 由 EventLoop 调用
     */
    public function markAsRunning(): void
    {
        if ($this->state === TaskState::PENDING) {
            $this->setState(TaskState::RUNNING);
            $this->startedAt = microtime(true);
            
            // 发送可观测性事件
            if (Observable::getInstance()->isEnabled()) {
                Observable::getInstance()->emitTaskEvent(
                    new TaskEvent(TaskEvent::STARTED, $this)
                );
            }
        }
    }
    
    /**
     * 获取任务持续时间（秒）
     */
    public function getDuration(): ?float
    {
        if ($this->completedAt === null) {
            // 任务未完成，返回当前已运行时间
            if ($this->startedAt !== null) {
                return microtime(true) - $this->startedAt;
            }
            return null;
        }
        
        $start = $this->startedAt ?? $this->createdAt;
        return $this->completedAt - $start;
    }
    
    /**
     * 获取任务等待时间（从创建到开始执行的时间）
     */
    public function getWaitTime(): ?float
    {
        if ($this->startedAt === null) {
            return null;
        }
        return $this->startedAt - $this->createdAt;
    }
    
    /**
     * 获取任务统计信息
     */
    public function getStats(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'state' => $this->state->value,
            'created_at' => $this->createdAt,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
            'wait_time' => $this->getWaitTime(),
            'duration' => $this->getDuration(),
            'has_exception' => $this->exception !== null,
        ];
    }
    
    public function __toString(): string
    {
        return "Task({$this->name}, {$this->state->format()})";
    }
}
