<?php
/**
 * 直观展示异步执行效果
 * 
 * 通过时间戳和执行顺序，清晰看到任务的并发执行
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, gather};

echo "=== 如何看出异步执行？===\n\n";

// 辅助函数：打印带时间戳的消息
function log_msg(string $msg): void
{
    echo sprintf("[%s] %s\n", date('H:i:s.') . substr(microtime(), 2, 3), $msg);
}

// 模拟任务
function task(string $name, float $duration): string
{
    log_msg("{$name} - 开始执行");
    sleep($duration);
    log_msg("{$name} - 执行完成");
    return "{$name} 结果";
}

// ========================================
// 对比 1: 顺序执行 vs 并发执行
// ========================================

echo "【对比 1】顺序执行 vs 并发执行\n";
echo "任务：3 个任务，每个耗时 1 秒\n\n";

echo "--- 顺序执行（一个接一个）---\n";
$start = microtime(true);
run(function() {
    task('任务A', 1.0);
    task('任务B', 1.0);
    task('任务C', 1.0);
});
$sequential_time = microtime(true) - $start;
echo "顺序执行总耗时: " . round($sequential_time, 2) . " 秒\n\n";

echo "--- 并发执行（同时运行）---\n";
$start = microtime(true);
run(function() {
    // 创建3个任务，它们会同时执行
    $t1 = create_task(fn() => task('任务A', 1.0));
    $t2 = create_task(fn() => task('任务B', 1.0));
    $t3 = create_task(fn() => task('任务C', 1.0));
    
    // 等待所有任务完成
    gather($t1, $t2, $t3);
});
$concurrent_time = microtime(true) - $start;
echo "并发执行总耗时: " . round($concurrent_time, 2) . " 秒\n";
echo "⚡ 速度提升: " . round($sequential_time / $concurrent_time, 1) . "x 倍\n\n";

echo str_repeat("=", 60) . "\n\n";

// ========================================
// 对比 2: 看执行顺序
// ========================================

echo "【对比 2】观察任务交错执行\n";
echo "注意：任务会交错执行，不是一个完成再执行下一个\n\n";

run(function() {
    log_msg("主程序开始");
    
    // 创建任务（立即开始执行）
    $t1 = create_task(function() {
        log_msg("  任务1 - 开始");
        sleep(0.3);
        log_msg("  任务1 - 中间");
        sleep(0.3);
        log_msg("  任务1 - 完成");
        return "结果1";
    });
    
    $t2 = create_task(function() {
        log_msg("    任务2 - 开始");
        sleep(0.2);
        log_msg("    任务2 - 中间");
        sleep(0.2);
        log_msg("    任务2 - 完成");
        return "结果2";
    });
    
    $t3 = create_task(function() {
        log_msg("      任务3 - 开始");
        sleep(0.15);
        log_msg("      任务3 - 中间");
        sleep(0.15);
        log_msg("      任务3 - 完成");
        return "结果3";
    });
    
    log_msg("主程序：3个任务已创建，等待完成...");
    
    $results = gather($t1, $t2, $t3);
    
    log_msg("主程序：所有任务完成");
    echo "\n看到了吗？任务是交错执行的，不是一个接一个！\n";
});

echo "\n" . str_repeat("=", 60) . "\n\n";

// ========================================
// 对比 3: 实际应用场景
// ========================================

echo "【对比 3】实际场景：并发 HTTP 请求\n";
echo "模拟：请求 5 个 API，每个耗时 0.5 秒\n\n";

echo "--- 顺序请求 ---\n";
$start = microtime(true);
run(function() {
    for ($i = 1; $i <= 5; $i++) {
        log_msg("请求 API-{$i}");
        sleep(0.5);
        log_msg("API-{$i} 返回");
    }
});
$seq = microtime(true) - $start;
echo "顺序请求耗时: " . round($seq, 2) . " 秒\n\n";

echo "--- 并发请求 ---\n";
$start = microtime(true);
run(function() {
    $tasks = [];
    for ($i = 1; $i <= 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            log_msg("请求 API-{$i}");
            sleep(0.5);
            log_msg("API-{$i} 返回");
            return "API-{$i} 数据";
        });
    }
    gather(...$tasks);
});
$con = microtime(true) - $start;
echo "并发请求耗时: " . round($con, 2) . " 秒\n";
echo "⚡ 节省时间: " . round($seq - $con, 2) . " 秒\n\n";

// ========================================
// 总结
// ========================================

echo str_repeat("=", 60) . "\n";
echo "✅ 如何判断是否异步？\n\n";
echo "1. 看时间戳 - 任务会在相同/相近的时间开始\n";
echo "2. 看总耗时 - 并发执行时间 ≈ 最慢任务的时间，不是所有任务时间之和\n";
echo "3. 看输出顺序 - 任务会交错执行，而不是一个完成再执行下一个\n";
echo "4. 看性能提升 - 并发执行通常比顺序执行快数倍\n\n";

echo "💡 关键理解：\n";
echo "- 异步 = 不等待，立即返回，任务在后台执行\n";
echo "- 并发 = 多个任务同时进行（在等待 I/O 时切换）\n";
echo "- PHP Fiber = 协作式并发，不是多线程并行\n";

