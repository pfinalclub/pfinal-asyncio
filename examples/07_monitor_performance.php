<?php
/**
 * 示例 7: 性能监控
 * 
 * 演示如何使用监控工具
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep};
use PfinalClub\Asyncio\Monitor\{AsyncioMonitor, PerformanceMonitor};

echo "=== 性能监控示例 ===\n\n";

run(function() {
    $monitor = AsyncioMonitor::getInstance();
    $perfMonitor = PerformanceMonitor::getInstance();
    
    echo "【测试】运行一组任务...\n\n";
    
    // 创建不同类型的任务
    $tasks = [];
    
    // 快速任务
    for ($i = 0; $i < 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(0.1);
            return "快速任务-{$i}";
        });
    }
    
    // 慢速任务
    for ($i = 0; $i < 3; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(1.5);  // 超过慢任务阈值(1秒)
            return "慢速任务-{$i}";
        });
    }
    
    // 中速任务
    for ($i = 0; $i < 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(0.5);
            return "中速任务-{$i}";
        });
    }
    
    // 等待所有任务完成
    $results = gather(...$tasks);
    
    echo "完成 " . count($results) . " 个任务\n\n";
    
    // 获取性能指标
    echo "【性能指标】\n";
    $metrics = $perfMonitor->getMetrics();
    foreach ($metrics as $name => $data) {
        echo "  {$name}:\n";
        echo "    执行次数: {$data['count']}\n";
        echo "    平均耗时: " . round($data['avg_duration'] * 1000, 2) . " ms\n";
        echo "    最小耗时: " . round($data['min_duration'] * 1000, 2) . " ms\n";
        echo "    最大耗时: " . round($data['max_duration'] * 1000, 2) . " ms\n";
        echo "    平均内存: " . round($data['avg_memory'] / 1024, 2) . " KB\n";
    }
    
    // 获取慢任务
    echo "\n【慢任务列表】\n";
    $slowTasks = $perfMonitor->getSlowTasks();
    foreach ($slowTasks as $task) {
        echo "  任务 #{$task['task_id']}: {$task['name']}\n";
        echo "    耗时: " . round($task['duration'], 2) . " 秒\n";
        echo "    内存: " . round($task['memory_used'] / 1024, 2) . " KB\n";
    }
    
    // 完整快照
    echo "\n【系统快照】\n";
    $snapshot = $monitor->snapshot();
    echo "  PHP 内存: " . round($snapshot['php_memory']['usage'] / 1024 / 1024, 2) . " MB\n";
    echo "  Fiber 数量: {$snapshot['task_stats']['active_fibers']}\n";
    echo "  事件循环模式: {$snapshot['event_loop']['mode']}\n";
    
    // 导出 Prometheus 格式
    echo "\n【Prometheus 指标】\n";
    $prometheus = \PfinalClub\Asyncio\Monitor\export_metrics('prometheus');
    echo $prometheus;
});


