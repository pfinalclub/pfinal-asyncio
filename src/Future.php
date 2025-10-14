<?php

namespace Pfinal\Async;

/**
 * Future - 表示一个未来的结果
 */
class Future
{
    private bool $done = false;
    private mixed $result = null;
    private ?\Throwable $exception = null;
    private array $callbacks = [];
    
    public function isDone(): bool
    {
        return $this->done;
    }
    
    public function setResult(mixed $result): void
    {
        if ($this->done) {
            throw new \RuntimeException("Future already done");
        }
        
        $this->result = $result;
        $this->done = true;
        $this->runCallbacks();
    }
    
    public function setException(\Throwable $exception): void
    {
        if ($this->done) {
            throw new \RuntimeException("Future already done");
        }
        
        $this->exception = $exception;
        $this->done = true;
        $this->runCallbacks();
    }
    
    public function getResult(): mixed
    {
        if (!$this->done) {
            throw new \RuntimeException("Future not done yet");
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
                error_log("Callback error in Future: " . $e->getMessage());
            }
        }
        $this->callbacks = [];
    }
}

