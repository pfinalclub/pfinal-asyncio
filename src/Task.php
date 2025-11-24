<?php

namespace PfinalClub\Asyncio;

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
    
    public function setResult(mixed $result): void
    {
        if ($this->state->isTerminal()) {
            throw new \RuntimeException(
                "Task {$this->name} already in terminal state: {$this->state->value}"
            );
        }
        
        $this->result = $result;
        $this->state = TaskState::COMPLETED;
        $this->completedAt = microtime(true);
        $this->runCallbacks();
    }
    
    public function setException(\Throwable $exception): void
    {
        if ($this->state->isTerminal()) {
            throw new \RuntimeException(
                "Cannot set exception on task '{$this->name}' in terminal state: {$this->state->value}"
            );
        }
        
        $this->exception = $exception;
        $this->state = $exception instanceof TaskCancelledException 
            ? TaskState::CANCELLED 
            : TaskState::FAILED;
        $this->completedAt = microtime(true);
        $this->runCallbacks();
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
            $this->state = TaskState::RUNNING;
            $this->startedAt = microtime(true);
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
