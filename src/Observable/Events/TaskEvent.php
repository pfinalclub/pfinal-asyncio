<?php

namespace PfinalClub\Asyncio\Observable\Events;

use PfinalClub\Asyncio\Task;

/**
 * 任务事件 - 表示任务生命周期中的各种事件
 * 
 * @api-stable
 */
class TaskEvent
{
    public const CREATED = 'created';
    public const STARTED = 'started';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';
    
    private string $type;
    private Task $task;
    private float $timestamp;
    private ?\Throwable $exception = null;
    
    /**
     * 构造函数
     * 
     * @param string $type 事件类型
     * @param Task $task 任务对象
     * @param ?\Throwable $exception 异常对象，仅在失败事件中使用
     */
    public function __construct(string $type, Task $task, ?\Throwable $exception = null)
    {
        $this->type = $type;
        $this->task = $task;
        $this->timestamp = microtime(true);
        $this->exception = $exception;
    }
    
    /**
     * 获取事件类型
     * 
     * @return string 事件类型
     */
    public function getType(): string
    {
        return $this->type;
    }
    
    /**
     * 获取任务对象
     * 
     * @return Task 任务对象
     */
    public function getTask(): Task
    {
        return $this->task;
    }
    
    /**
     * 获取事件时间戳
     * 
     * @return float 事件时间戳
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
    
    /**
     * 获取异常对象
     * 
     * @return ?\Throwable 异常对象，仅在失败事件中使用
     */
    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
    
    /**
     * 检查是否为创建事件
     * 
     * @return bool 是否为创建事件
     */
    public function isCreated(): bool
    {
        return $this->type === self::CREATED;
    }
    
    /**
     * 检查是否为开始事件
     * 
     * @return bool 是否为开始事件
     */
    public function isStarted(): bool
    {
        return $this->type === self::STARTED;
    }
    
    /**
     * 检查是否为完成事件
     * 
     * @return bool 是否为完成事件
     */
    public function isCompleted(): bool
    {
        return $this->type === self::COMPLETED;
    }
    
    /**
     * 检查是否为失败事件
     * 
     * @return bool 是否为失败事件
     */
    public function isFailed(): bool
    {
        return $this->type === self::FAILED;
    }
    
    /**
     * 检查是否为取消事件
     * 
     * @return bool 是否为取消事件
     */
    public function isCancelled(): bool
    {
        return $this->type === self::CANCELLED;
    }
}