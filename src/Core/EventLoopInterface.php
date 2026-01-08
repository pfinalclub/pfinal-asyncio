<?php

namespace PfinalClub\Asyncio\Core;

/**
 * 事件循环接口 - 稳定 API
 * 
 * @api-stable
 */
interface EventLoopInterface
{
    /**
     * 获取事件循环单例
     * 
     * @return self
     */
    public static function getInstance(): self;
    
    /**
     * 运行主协程
     * 
     * @param callable $main 要运行的主函数
     * @return mixed 主协程的返回值
     */
    public function run(callable $main): mixed;
    
    /**
     * 添加定时器
     * 
     * @param float $interval 时间间隔（秒）
     * @param callable $callback 回调函数
     * @param bool $persistent 是否持续运行
     * @return int 定时器 ID
     */
    public function addTimer(float $interval, callable $callback, bool $persistent = true): int;
    
    /**
     * 删除定时器
     * 
     * @param int $timerId 定时器 ID
     */
    public function delTimer(int $timerId): void;
    
    /**
     * 停止事件循环
     */
    public function stop(): void;
    
    /**
     * 获取当前使用的事件循环类型
     * 
     * @return string|null
     */
    public static function getEventLoopType(): ?string;
}