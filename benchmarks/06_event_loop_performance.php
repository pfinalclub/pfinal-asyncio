<?php
/**
 * 事件循环性能对比测试
 * 
 * 测试不同事件循环（Select vs Event vs Ev）的性能差异
 */

require __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;
use function PfinalClub\Asyncio\sleep;
use PfinalClub\Asyncio\EventLoop;

echo "=== 事件循环性能对比测试 ===\n\n";

// 测试参数
$taskCount = 100;      // 并发任务数
$sleepDuration = 0.01; // 每个任务睡眠时间（秒）

echo "测试配置:\n";
echo "- 并发任务数: {$taskCount}\n";
echo "- 每个任务睡眠: {$sleepDuration}s\n\n";

// 运行性能测试（同时检测事件循环类型）
echo "开始性能测试...\n\n";
$startTime = microtime(true);
$startMemory = memory_get_usage();

run(function() use ($taskCount, $sleepDuration) {
    $tasks = [];
    
    for ($i = 0; $i < $taskCount; $i++) {
        $tasks[] = create_task(function() use ($i, $sleepDuration) {
            sleep($sleepDuration);
            return $i;
        }, "task-{$i}");
    }
    
    $results = gather(...$tasks);
    
    echo "完成 " . count($results) . " 个任务\n\n";
});

$endTime = microtime(true);
$endMemory = memory_get_usage();
$eventLoopType = EventLoop::getEventLoopType();

$duration = $endTime - $startTime;
$memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;
$throughput = $taskCount / $duration;

echo "\n性能结果:\n";
echo "- 总耗时: " . number_format($duration, 3) . "s\n";
echo "- 内存使用: " . number_format($memoryUsed, 2) . " MB\n";
echo "- 吞吐量: " . number_format($throughput, 0) . " tasks/s\n";

echo "\n性能评估:\n";
if ($eventLoopType === 'Select') {
    echo "⚠️  当前使用 Select 事件循环\n";
    echo "💡 预期性能提升:\n";
    echo "   - 使用 Event (libevent): 3-5 倍\n";
    echo "   - 使用 Ev (libev): 10-20 倍\n";
    echo "\n安装方法:\n";
    echo "   pecl install ev      # 推荐，最高性能\n";
    echo "   pecl install event   # 次选，高性能\n";
} elseif ($eventLoopType === 'Event') {
    echo "⚡ 当前使用 Event (libevent) 事件循环 - 高性能\n";
    echo "💡 进一步提升:\n";
    echo "   - 使用 Ev (libev): 2-4 倍\n";
    echo "\n安装方法:\n";
    echo "   pecl install ev      # 最高性能\n";
} else {
    echo "🚀 当前使用 Ev (libev) 事件循环 - 最佳性能！\n";
    echo "✓  已达到最优性能配置\n";
}

echo "\n性能对比（理论值）:\n";
echo "┌──────────────┬─────────────┬──────────────┐\n";
echo "│ 事件循环     │ 并发能力    │ 相对性能     │\n";
echo "├──────────────┼─────────────┼──────────────┤\n";
echo "│ Select       │ < 1K        │ 1x (基准)    │\n";
echo "│ Event        │ > 10K       │ 3-5x         │\n";
echo "│ Ev           │ > 100K      │ 10-20x       │\n";
echo "└──────────────┴─────────────┴──────────────┘\n";

