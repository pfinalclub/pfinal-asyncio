<?php

namespace PfinalClub\Asyncio;

/**
 * 任务 - 对 Fiber 的封装
 */
class Task
{
    private mixed $callable;
    private int $id;
    private string $name;
    private bool $done = false;
    private mixed $result = null;
    private ?\Throwable $exception = null;
    private array $callbacks = [];
    
    public function __construct(callable $callable, int $id, string $name)
    {
        $this->callable = $callable;
        $this->id = $id;
        $this->name = $name;
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
        return $this->done;
    }
    
    public function setResult(mixed $result): void
    {
        if ($this->done) {
            throw new \RuntimeException("Task {$this->name} already done");
        }
        
        $this->result = $result;
        $this->done = true;
        $this->runCallbacks();
    }
    
    public function setException(\Throwable $exception): void
    {
        if ($this->done) {
            throw new \RuntimeException(
                "Cannot set exception on completed task '{$this->name}': Task already " . 
                ($this->exception ? 'failed with ' . get_class($this->exception) : 'completed with result')
            );
        }
        
        $this->exception = $exception;
        $this->done = true;
        $this->runCallbacks();
    }
    
    public function getResult(): mixed
    {
        if (!$this->done) {
            throw new \RuntimeException("Task {$this->name} not done yet");
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
        if ($this->done) {
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
        if ($this->done) {
            return false;
        }
        
        $this->setException(new TaskCancelledException("Task {$this->name} cancelled"));
        return true;
    }
    
    public function __toString(): string
    {
        $status = $this->done ? 'done' : 'pending';
        return "Task({$this->name}, {$status})";
    }
}
