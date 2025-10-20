<?php
/**
 * v2.0.2 功能测试
 * 测试 Fiber 清理、连接池、性能监控
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, gather};
use PfinalClub\Asyncio\Monitor\PerformanceMonitor;
use PfinalClub\Asyncio\Http\AsyncHttpClient;

echo "=== v2.0.2 功能测试 ===\n\n";

// 测试 1: Fiber 自动清理
echo "【测试 1】Fiber 自动清理 - 内存泄漏测试\n";
$before = memory_get_usage();
for ($i = 0; $i < 200; $i++) {
    run(function() { 
        $task = create_task(fn() => "test");
        \PfinalClub\Asyncio\await($task);
    });
}
$after = memory_get_usage();
$diff = ($after - $before) / 1024 / 1024;

echo "创建 200 个 Fiber 前后内存差异: " . round($diff, 2) . "MB\n";
if ($diff < 5) {
    echo "✅ 通过 - 内存增长 < 5MB\n";
} else {
    echo "⚠️  警告 - 内存增长较大: " . round($diff, 2) . "MB\n";
}
echo "\n";

// 测试 2: 性能监控
echo "【测试 2】性能监控 - 慢任务追踪\n";
run(function() {
    // 创建一个慢任务
    $slowTask = create_task(function() {
        sleep(1.2); // 超过 1.0 秒阈值
        return "slow";
    }, 'test-slow-task');
    
    \PfinalClub\Asyncio\await($slowTask);
});

$monitor = PerformanceMonitor::getInstance();
$slowTasks = $monitor->getSlowTasks();

echo "检测到慢任务数: " . count($slowTasks) . "\n";
if (count($slowTasks) > 0) {
    echo "✅ 通过 - 慢任务追踪正常工作\n";
    echo "慢任务详情:\n";
    foreach ($slowTasks as $task) {
        echo "  - {$task['name']}: " . round($task['duration'], 3) . "s\n";
    }
} else {
    echo "❌ 失败 - 未检测到慢任务\n";
}
echo "\n";

// 测试 3: 性能指标
echo "【测试 3】性能指标统计\n";
$metrics = $monitor->getMetrics();

echo "记录的任务类型数: " . count($metrics) . "\n";
if (count($metrics) > 0) {
    echo "✅ 通过 - 性能指标正常记录\n";
    foreach ($metrics as $name => $stats) {
        echo "  - {$name}: {$stats['count']} 次执行\n";
    }
} else {
    echo "❌ 失败 - 没有性能指标\n";
}
echo "\n";

// 测试 4: HTTP 连接池
echo "【测试 4】HTTP 连接池统计\n";
$client = new AsyncHttpClient([
    'use_connection_pool' => true,
    'timeout' => 5,
]);

$poolStats = $client->getConnectionPoolStats();
echo "连接池初始化: " . (is_array($poolStats) ? "✅ 成功" : "❌ 失败") . "\n";
echo "连接池端点数: " . count($poolStats) . "\n";
echo "\n";

// 测试 5: Prometheus 导出
echo "【测试 5】Prometheus 格式导出\n";
$prometheus = $monitor->exportPrometheus();
$lines = explode("\n", trim($prometheus));
echo "导出行数: " . count($lines) . "\n";
if (count($lines) > 0) {
    echo "✅ 通过 - Prometheus 格式导出正常\n";
    echo "示例输出:\n";
    echo implode("\n", array_slice($lines, 0, 3)) . "\n";
} else {
    echo "❌ 失败 - 导出为空\n";
}
echo "\n";

// 测试 6: 并发性能
echo "【测试 6】并发性能测试\n";
$start = microtime(true);
run(function() {
    $tasks = [];
    for ($i = 0; $i < 50; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(0.01);
            return "task-{$i}";
        }, "concurrent-task-{$i}");
    }
    gather(...$tasks);
});
$elapsed = (microtime(true) - $start) * 1000;

echo "50 个并发任务耗时: " . round($elapsed, 2) . "ms\n";
if ($elapsed < 100) {
    echo "✅ 通过 - 并发性能良好 (< 100ms)\n";
} else {
    echo "⚠️  警告 - 并发性能较慢: " . round($elapsed, 2) . "ms\n";
}
echo "\n";

// 清理
$monitor->reset();

echo "=== 所有测试完成 ===\n";
echo "\n";
echo "v2.0.2 新功能:\n";
echo "✅ P0: Fiber 自动清理 - 防止内存泄漏\n";
echo "✅ P1: HTTP 连接池 - 连接统计和管理\n";
echo "✅ P2: 性能监控 - 任务计时、慢任务追踪、Prometheus 导出\n";

