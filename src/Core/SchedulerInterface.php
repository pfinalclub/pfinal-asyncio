<?php

namespace PfinalClub\Asyncio\Core;

/**
 * 三级调度器接口
 * 
 * 基于优先级的三级调度模型：
 * 1. SYSTEM级：最高优先级，系统关键任务
 * 2. CONTROL级：中等优先级，控制面任务  
 * 3. WORK级：低优先级，业务任务
 * 
 * @api-stable
 */
interface SchedulerInterface
{
    /**
     * 调度优先级枚举
     */
    public const PRIORITY_SYSTEM = 0;    // 系统级：cancel/timeout/cleanup/signal
    public const PRIORITY_CONTROL = 1;   // 控制面：health check/metrics/RPC
    public const PRIORITY_WORK = 2;      // 业务级：HTTP/DB/IO操作
    
    /**
     * 调度任务
     * 
     * @param callable $callback 要执行的回调函数
     * @param int $priority 优先级（SYSTEM/CONTROL/WORK）
     * @param string $name 任务名称
     * @return Task 创建的任务对象
     */
    public function schedule(callable $callback, int $priority = self::PRIORITY_WORK, string $name = ''): Task;
    
    /**
     * 获取调度统计信息
     * 
     * @return array 调度统计
     */
    public function getSchedulerStats(): array;
    
    /**
     * 配置调度参数
     * 
     * @param array $config 配置参数
     */
    public function configure(array $config): void;
}