<?php

namespace PfinalClub\Asyncio\Resource;

/**
 * Fiber 资源 - 管理 Fiber 资源的生命周期
 * 
 * @internal
 */
class FiberResource implements AsyncResource
{
    private bool $closed = false;
    private ?\Fiber $fiber;
    private int $fiberId;
    
    /**
     * 构造函数
     * 
     * @param \Fiber $fiber Fiber 对象
     */
    public function __construct(\Fiber $fiber)
    {
        $this->fiber = $fiber;
        $this->fiberId = spl_object_id($fiber);
        
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
        
        // 清理 Fiber 资源
        if ($this->fiber && !$this->fiber->isTerminated()) {
            try {
                // 尝试终止 Fiber
                $this->fiber = null;
            } catch (\Throwable $e) {
                // 记录错误，但不影响资源关闭
                error_log("Error closing fiber resource: " . $e->getMessage());
            }
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
     * 获取 Fiber ID
     * 
     * @return int Fiber ID
     */
    public function getFiberId(): int
    {
        return $this->fiberId;
    }
    
    /**
     * 获取 Fiber 对象
     * 
     * @return \Fiber|null Fiber 对象
     */
    public function getFiber(): ?\Fiber
    {
        return $this->fiber;
    }
}