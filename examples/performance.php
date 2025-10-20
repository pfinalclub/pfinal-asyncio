<?php
/**
 * 性能监控示例 (v2.0.2)
 * 
 * 展示如何使用 PerformanceMonitor 进行性能分析
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, gather};
use function PfinalClub\Asyncio\Monitor\{export_metrics, set_slow_task_threshold};
use PfinalClub\Asyncio\Monitor\PerformanceMonitor;

echo "=== AsyncIO 性能监控示例 (v2.0.2) ===\n\n";

// 设置慢任务阈值
set_slow_task_threshold(1.0);  // 1 秒

run(function() {
    // 示例 1: 执行一些任务
    echo "【示例 1】执行混合任务\n";
    $tasks = [];
    
    // 快速任务
    for ($i = 1; $i <= 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(0.1);
            return "Fast-{$i}";
        }, "fast-task-{$i}");
    }
    
    // 慢任务
    for ($i = 1; $i <= 2; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(1.5);
            return "Slow-{$i}";
        }, "slow-task-{$i}");
    }
    
    $results = gather(...$tasks);
    echo "完成 " . count($results) . " 个任务\n\n";
    
    // 示例 2: 查看性能指标
    echo "【示例 2】性能指标\n";
    $monitor = PerformanceMonitor::getInstance();
    $metrics = $monitor->getMetrics();
    
    foreach ($metrics as $taskName => $stats) {
        echo "{$taskName}:\n";
        echo "  执行次数: {$stats['count']}\n";
        echo "  平均耗时: " . round($stats['avg_duration'] * 1000) . "ms\n";
        echo "  最大耗时: " . round($stats['max_duration'] * 1000) . "ms\n";
    }
    echo "\n";
    
    // 示例 3: 慢任务追踪
    echo "【示例 3】慢任务追踪\n";
    $slowTasks = $monitor->getSlowTasks();
    
    if (empty($slowTasks)) {
        echo "没有检测到慢任务\n";
    } else {
        echo "检测到 " . count($slowTasks) . " 个慢任务:\n";
        foreach ($slowTasks as $task) {
            echo "  - {$task['name']}: " . round($task['duration'], 2) . "s\n";
        }
    }
    echo "\n";
    
    // 示例 4: 导出 JSON 格式
    echo "【示例 4】导出 JSON 格式\n";
    $json = export_metrics('json');
    $data = json_decode($json, true);
    echo "指标数量: " . count($data['metrics']) . "\n";
    echo "慢任务数: " . count($data['slow_tasks']) . "\n\n";
    
    // 示例 5: 导出 Prometheus 格式
    echo "【示例 5】导出 Prometheus 格式\n";
    $prometheus = export_metrics('prometheus');
    $lines = explode("\n", trim($prometheus));
    echo "导出了 " . count($lines) . " 行 Prometheus 指标\n";
    echo "示例输出:\n";
    echo implode("\n", array_slice($lines, 0, 5)) . "\n...\n";
});

echo "\n✅ 性能监控示例完成\n";
echo "💡 提示: 在生产环境中可以将指标导出到 Prometheus 或日志系统\n";

