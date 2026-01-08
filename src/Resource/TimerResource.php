<?php

namespace PfinalClub\Asyncio\Resource;

use Workerman\Timer;

/**
 * 定时器资源 - 管理定时器资源的生命周期
 * 
 * @internal
 */
class TimerResource implements AsyncResource
{
    private bool $closed = false;
    private ?int $timerId;
    private float $interval;
    private bool $persistent;
    
    /**
     * 构造函数
     * 
     * @param int $timerId 定时器 ID
     * @param float $interval 时间间隔（秒）
     * @param bool $persistent 是否持续运行
     */
    public function __construct(int $timerId, float $interval, bool $persistent = true)
    {
        $this->timerId = $timerId;
        $this->interval = $interval;
        $this->persistent = $persistent;
        
        // 注册到资源管理器
        AsyncResourceManager::register($this);
    }
    
    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        
        $this->closed = true;
        
        // 删除定时器
        if ($this->timerId !== null) {
            try {
                Timer::del($this->timerId);
            } catch (\Throwable $e) {
                // 记录错误，但不影响资源关闭
                error_log("Error closing timer resource: " . $e->getMessage());
            }
            $this->timerId = null;
        }
        
        // 从资源管理器中注销
        AsyncResourceManager::deregister($this);
    }
    
    /**
     * {@inheritDoc}
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
    
    /**
     * {@inheritDoc}
     */
    public function onCancellation(): void
    {
        $this->close();
    }
    
    /**
     * 获取定时器 ID
     * 
     * @return int|null 定时器 ID
     */
    public function getTimerId(): ?int
    {
        return $this->timerId;
    }
    
    /**
     * 获取时间间隔
     * 
     * @return float 时间间隔（秒）
     */
    public function getInterval(): float
    {
        return $this->interval;
    }
    
    /**
     * 是否持续运行
     * 
     * @return bool 是否持续运行
     */
    public function isPersistent(): bool
    {
        return $this->persistent;
    }
}