<?php

namespace PfinalClub\Asyncio\Observable\Events;

/**
 * 运行时状态事件 - 表示运行时状态的变化
 * 
 * @api-stable
 */
class RuntimeStateEvent
{
    public const EVENT_LOOP_STARTED = 'event_loop_started';
    public const EVENT_LOOP_STOPPED = 'event_loop_stopped';
    public const MEMORY_WARNING = 'memory_warning';
    public const HIGH_LOAD = 'high_load';
    public const SHUTDOWN_INITIATED = 'shutdown_initiated';
    public const SHUTDOWN_COMPLETED = 'shutdown_completed';
    
    private string $type;
    private float $timestamp;
    private array $data;
    
    /**
     * 构造函数
     * 
     * @param string $type 事件类型
     * @param array $data 事件数据
     */
    public function __construct(string $type, array $data = [])
    {
        $this->type = $type;
        $this->timestamp = microtime(true);
        $this->data = $data;
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
     * 获取事件时间戳
     * 
     * @return float 事件时间戳
     */
    public function getTimestamp(): float
    {
        return $this->timestamp;
    }
    
    /**
     * 获取事件数据
     * 
     * @return array 事件数据
     */
    public function getData(): array
    {
        return $this->data;
    }
    
    /**
     * 检查是否为事件循环启动事件
     * 
     * @return bool 是否为事件循环启动事件
     */
    public function isEventLoopStarted(): bool
    {
        return $this->type === self::EVENT_LOOP_STARTED;
    }
    
    /**
     * 检查是否为事件循环停止事件
     * 
     * @return bool 是否为事件循环停止事件
     */
    public function isEventLoopStopped(): bool
    {
        return $this->type === self::EVENT_LOOP_STOPPED;
    }
    
    /**
     * 检查是否为内存警告事件
     * 
     * @return bool 是否为内存警告事件
     */
    public function isMemoryWarning(): bool
    {
        return $this->type === self::MEMORY_WARNING;
    }
    
    /**
     * 检查是否为高负载事件
     * 
     * @return bool 是否为高负载事件
     */
    public function isHighLoad(): bool
    {
        return $this->type === self::HIGH_LOAD;
    }
    
    /**
     * 检查是否为关闭初始化事件
     * 
     * @return bool 是否为关闭初始化事件
     */
    public function isShutdownInitiated(): bool
    {
        return $this->type === self::SHUTDOWN_INITIATED;
    }
    
    /**
     * 检查是否为关闭完成事件
     * 
     * @return bool 是否为关闭完成事件
     */
    public function isShutdownCompleted(): bool
    {
        return $this->type === self::SHUTDOWN_COMPLETED;
    }
}