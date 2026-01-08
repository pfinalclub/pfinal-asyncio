<?php

namespace PfinalClub\Asyncio\Resource;

/**
 * 异步资源接口 - 定义 Runtime 资源的基本行为
 * 
 * 用于管理和清理异步运行时资源，如文件句柄、网络连接、定时器等
 * 
 * @api-stable
 */
interface AsyncResource
{
    /**
     * 关闭资源
     * 
     * @return void
     */
    public function close(): void;
    
    /**
     * 检查资源是否已关闭
     * 
     * @return bool 是否已关闭
     */
    public function isClosed(): bool;
    
    /**
     * 处理资源取消事件
     * 
     * 当资源所在的 CancellationScope 被取消时调用
     * 
     * @return void
     */
    public function onCancellation(): void;
}