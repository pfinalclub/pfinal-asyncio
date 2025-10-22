<?php

namespace PfinalClub\Asyncio\Production;

use PfinalClub\Asyncio\Production\MultiProcessMode;
use PfinalClub\Asyncio\Production\HealthCheck;
use PfinalClub\Asyncio\Production\GracefulShutdown;
use PfinalClub\Asyncio\Production\ResourceLimits;

/**
 * 启用多进程模式
 *
 * @param callable $callback 主回调函数
 * @param array $config 配置选项
 */
function run_multiprocess(callable $callback, array $config = []): void
{
    MultiProcessMode::enable($callback, $config);
}

/**
 * 创建健康检查实例
 *
 * @return HealthCheck
 */
function health_check(): HealthCheck
{
    return HealthCheck::getInstance();
}

/**
 * 创建优雅关闭实例
 *
 * @param int $gracePeriod 优雅期（秒）
 * @return GracefulShutdown
 */
function graceful_shutdown(int $gracePeriod = 30): GracefulShutdown
{
    return GracefulShutdown::getInstance($gracePeriod);
}

/**
 * 创建资源限制实例
 *
 * @param array $config 配置选项
 * @return ResourceLimits
 */
function resource_limits(array $config = []): ResourceLimits
{
    return ResourceLimits::getInstance($config);
}

