<?php

namespace PfinalClub\Asyncio\Observable\Events;

use PfinalClub\Asyncio\Concurrency\CancellationScope;

/**
 * 作用域事件 - 表示 CancellationScope 生命周期中的各种事件
 * 
 * @api-stable
 */
class ScopeEvent
{
    public const CREATED = 'created';
    public const ENTERED = 'entered';
    public const EXITED = 'exited';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';
    
    private string $type;
    private CancellationScope $scope;
    private float $timestamp;
    
    /**
     * 构造函数
     * 
     * @param string $type 事件类型
     * @param CancellationScope $scope 作用域对象
     */
    public function __construct(string $type, CancellationScope $scope)
    {
        $this->type = $type;
        $this->scope = $scope;
        $this->timestamp = microtime(true);
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
     * 获取作用域对象
     * 
     * @return CancellationScope 作用域对象
     */
    public function getScope(): CancellationScope
    {
        return $this->scope;
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
     * 检查是否为创建事件
     * 
     * @return bool 是否为创建事件
     */
    public function isCreated(): bool
    {
        return $this->type === self::CREATED;
    }
    
    /**
     * 检查是否为进入事件
     * 
     * @return bool 是否为进入事件
     */
    public function isEntered(): bool
    {
        return $this->type === self::ENTERED;
    }
    
    /**
     * 检查是否为退出事件
     * 
     * @return bool 是否为退出事件
     */
    public function isExited(): bool
    {
        return $this->type === self::EXITED;
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
     * 检查是否为取消事件
     * 
     * @return bool 是否为取消事件
     */
    public function isCancelled(): bool
    {
        return $this->type === self::CANCELLED;
    }
}