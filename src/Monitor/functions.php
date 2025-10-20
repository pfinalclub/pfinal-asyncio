<?php

namespace PfinalClub\Asyncio\Monitor;

/**
 * 导出性能指标
 * 
 * @param string $format 导出格式：json 或 prometheus
 * @return string 格式化的指标数据
 */
function export_metrics(string $format = 'json'): string
{
    $monitor = PerformanceMonitor::getInstance();
    
    return match($format) {
        'prometheus' => $monitor->exportPrometheus(),
        'json' => json_encode([
            'metrics' => $monitor->getMetrics(),
            'slow_tasks' => $monitor->getSlowTasks(),
            'timestamp' => time(),
        ], JSON_PRETTY_PRINT),
        default => throw new \InvalidArgumentException("Unsupported format: {$format}"),
    };
}

/**
 * 获取性能快照
 * 
 * @return array 完整的性能数据
 */
function get_performance_snapshot(): array
{
    $monitor = PerformanceMonitor::getInstance();
    $asyncioMonitor = AsyncioMonitor::getInstance();
    
    return [
        'asyncio' => $asyncioMonitor->snapshot(),
        'performance' => [
            'metrics' => $monitor->getMetrics(),
            'slow_tasks' => $monitor->getSlowTasks(),
        ],
    ];
}

/**
 * 重置性能统计
 */
function reset_performance_stats(): void
{
    PerformanceMonitor::getInstance()->reset();
}

/**
 * 设置慢任务阈值
 * 
 * @param float $seconds 阈值（秒）
 */
function set_slow_task_threshold(float $seconds): void
{
    PerformanceMonitor::getInstance()->setSlowTaskThreshold($seconds);
}

