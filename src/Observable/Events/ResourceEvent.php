<?php

namespace PfinalClub\Asyncio\Observable\Events;

use PfinalClub\Asyncio\Resource\AsyncResource;

/**
 * 资源事件 - 表示资源生命周期中的各种事件
 * 
 * @api-stable
 */
class ResourceEvent
{
    public const REGISTERED = 'registered';
    public const DEREGISTERED = 'deregistered';
    public const CLOSED = 'closed';
    public const CANCELLED = 'cancelled';
    
    private string $type;
    private AsyncResource $resource;
    private float $timestamp;
    private string $resourceType;
    
    /**
     * 构造函数
     * 
     * @param string $type 事件类型
     * @param AsyncResource $resource 资源对象
     */
    public function __construct(string $type, AsyncResource $resource)
    {
        $this->type = $type;
        $this->resource = $resource;
        $this->timestamp = microtime(true);
        $this->resourceType = get_class($resource);
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
     * 获取资源对象
     * 
     * @return AsyncResource 资源对象
     */
    public function getResource(): AsyncResource
    {
        return $this->resource;
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
     * 获取资源类型
     * 
     * @return string 资源类型（类名）
     */
    public function getResourceType(): string
    {
        return $this->resourceType;
    }
    
    /**
     * 检查是否为注册事件
     * 
     * @return bool 是否为注册事件
     */
    public function isRegistered(): bool
    {
        return $this->type === self::REGISTERED;
    }
    
    /**
     * 检查是否为注销事件
     * 
     * @return bool 是否为注销事件
     */
    public function isDeregistered(): bool
    {
        return $this->type === self::DEREGISTERED;
    }
    
    /**
     * 检查是否为关闭事件
     * 
     * @return bool 是否为关闭事件
     */
    public function isClosed(): bool
    {
        return $this->type === self::CLOSED;
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