<?php

namespace PfinalClub\Asyncio\Resource;

/**
 * 上下文资源 - 管理上下文资源的生命周期
 * 
 * @internal
 */
class ContextResource implements AsyncResource
{
    private bool $closed = false;
    private array $contextData = [];
    private int $contextId;
    private int $fiberId;
    
    /**
     * 构造函数
     * 
     * @param int $fiberId Fiber ID
     * @param array $initialData 初始上下文数据
     */
    public function __construct(int $fiberId, array $initialData = [])
    {
        $this->fiberId = $fiberId;
        $this->contextData = $initialData;
        $this->contextId = spl_object_id($this);
        
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
        
        // 清理上下文数据
        $this->contextData = [];
        
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
     * 获取上下文数据
     * 
     * @return array 上下文数据
     */
    public function getData(): array
    {
        return $this->contextData;
    }
    
    /**
     * 设置上下文数据
     * 
     * @param array $data 上下文数据
     * @return void
     */
    public function setData(array $data): void
    {
        $this->contextData = $data;
    }
    
    /**
     * 更新上下文数据
     * 
     * @param array $data 要更新的上下文数据
     * @return void
     */
    public function updateData(array $data): void
    {
        $this->contextData = array_merge($this->contextData, $data);
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
     * 获取上下文 ID
     * 
     * @return int 上下文 ID
     */
    public function getContextId(): int
    {
        return $this->contextId;
    }
}