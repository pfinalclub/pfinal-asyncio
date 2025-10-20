<?php
/**
 * 并发示例 - 展示多任务并发执行（基于 Fiber）
 * 
 * 优化内容：
 * - 添加完整的错误处理机制
 * - 集成性能监控和统计
 * - 改进任务命名和状态追踪
 * - 添加并发限制和资源管理
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, gather, wait_for};
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;
use PfinalClub\Asyncio\TimeoutException;

/**
 * 模拟下载文件（带错误处理）
 */
function download_file(string $filename, float $duration): array
{
    $start = microtime(true);
    echo "[开始] 下载 {$filename}...\n";
    
    // 模拟可能的网络错误
    if (rand(1, 10) === 1) { // 10% 概率模拟网络错误
        throw new \RuntimeException("网络连接失败: {$filename}");
    }
    
    sleep($duration);
    
    $elapsed = round(microtime(true) - $start, 2);
    echo "[完成] {$filename} 下载完成 (用时: {$elapsed}秒)\n";
    
    return [
        'filename' => $filename,
        'size' => rand(1000, 9999) . 'KB',
        'duration' => $elapsed
    ];
}

/**
 * 处理数据（带进度报告）
 */
function process_data(int $id): string
{
    echo "处理数据 #{$id} - 第1步\n";
    sleep(0.5);
    
    echo "处理数据 #{$id} - 第2步\n";
    sleep(0.5);
    
    echo "处理数据 #{$id} - 完成\n";
    return "数据 #{$id} 已处理";
}

/**
 * 并发限制执行器
 */
function execute_with_concurrency_limit(array $tasks, int $concurrency = 3): array
{
    $results = [];
    $chunks = array_chunk($tasks, $concurrency);
    
    foreach ($chunks as $chunkIndex => $chunk) {
        echo "执行批次 #" . ($chunkIndex + 1) . " (并发数: {$concurrency})\n";
        $chunkResults = gather(...$chunk);
        $results = array_merge($results, $chunkResults);
    }
    
    return $results;
}

/**
 * 主函数 - 并发示例（优化版）
 */
function main(): mixed
{
    $monitor = AsyncioMonitor::getInstance();
    
    echo "=== 并发执行示例 (Fiber) - 优化版 ===\n\n";
    
    // 示例 1: 并发下载多个文件（带错误处理）
    echo "示例 1: 并发下载文件（带错误处理）\n";
    $start = microtime(true);
    
    $tasks = [
        create_task(fn() => download_file('file1.zip', 2), 'download-file1'),
        create_task(fn() => download_file('file2.zip', 1.5), 'download-file2'),
        create_task(fn() => download_file('file3.zip', 1), 'download-file3'),
        create_task(fn() => download_file('file4.zip', 2.5), 'download-file4'),
    ];
    
    try {
        // 使用 gather 等待所有任务完成，自动处理错误
        $results = gather(...$tasks);
        
        $total_time = round(microtime(true) - $start, 2);
        echo "\n✅ 所有下载完成！总用时: {$total_time}秒\n";
        echo "下载结果:\n";
        foreach ($results as $result) {
            echo "  - {$result['filename']}: {$result['size']} (耗时: {$result['duration']}秒)\n";
        }
    } catch (\Throwable $e) {
        echo "\n❌ 下载过程中出现错误: {$e->getMessage()}\n";
        
        // 统计成功和失败的任务
        $successCount = 0;
        foreach ($tasks as $task) {
            if ($task->isDone() && !$task->hasException()) {
                $successCount++;
            }
        }
        echo "成功下载: {$successCount}/" . count($tasks) . " 个文件\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 2: 并发处理数据（带并发限制）
    echo "示例 2: 并发处理数据（并发限制为 2）\n";
    $tasks = [];
    for ($i = 1; $i <= 6; $i++) {
        $tasks[] = create_task(fn() => process_data($i), "process-data-{$i}");
    }
    
    $results = execute_with_concurrency_limit($tasks, 2);
    echo "\n✅ 处理结果:\n";
    foreach ($results as $result) {
        echo "  - {$result}\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 3: 带超时的并发任务
    echo "示例 3: 带超时的并发任务\n";
    $tasks = [];
    for ($i = 1; $i <= 3; $i++) {
        $tasks[] = create_task(function() use ($i) {
            $duration = $i * 1.5; // 不同任务不同时长
            echo "任务 {$i} 开始 (预计耗时: {$duration}秒)\n";
            sleep($duration);
            return "任务 {$i} 完成";
        }, "timeout-task-{$i}");
    }
    
    $completed = [];
    $timeout = [];
    
    foreach ($tasks as $index => $task) {
        try {
            $result = wait_for(fn() => \PfinalClub\Asyncio\await($task), 2.0);
            $completed[] = $result;
        } catch (TimeoutException $e) {
            $timeout[] = "任务 " . ($index + 1);
            echo "⚠️  任务 " . ($index + 1) . " 超时\n";
        }
    }
    
    echo "\n✅ 完成的任务: " . implode(', ', $completed) . "\n";
    if (!empty($timeout)) {
        echo "❌ 超时的任务: " . implode(', ', $timeout) . "\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 4: 性能对比和监控统计
    echo "示例 4: 性能对比和监控统计\n";
    
    // 顺序执行
    echo "顺序执行:\n";
    $start = microtime(true);
    download_file('seq1.zip', 1);
    download_file('seq2.zip', 1);
    download_file('seq3.zip', 1);
    $sequential_time = round(microtime(true) - $start, 2);
    echo "顺序执行用时: {$sequential_time}秒\n\n";
    
    // 并发执行
    echo "并发执行:\n";
    $start = microtime(true);
    $tasks = [
        create_task(fn() => download_file('par1.zip', 1), 'bench-par1'),
        create_task(fn() => download_file('par2.zip', 1), 'bench-par2'),
        create_task(fn() => download_file('par3.zip', 1), 'bench-par3')
    ];
    gather(...$tasks);
    $concurrent_time = round(microtime(true) - $start, 2);
    echo "并发执行用时: {$concurrent_time}秒\n\n";
    
    $speedup = round($sequential_time / $concurrent_time, 2);
    echo "🎯 性能提升: {$speedup}x 倍\n";
    
    // 显示监控统计
    $snapshot = $monitor->snapshot();
    echo "\n📊 监控统计:\n";
    echo "  内存使用: {$snapshot['memory']['current_mb']}MB\n";
    echo "  活跃 Fiber: {$snapshot['event_loop']['active_fibers']}\n";
    if (isset($snapshot['performance'])) {
        echo "  任务统计: " . count($snapshot['performance']) . " 个任务被监控\n";
    }
    
    return "并发示例优化完成";
}

// 运行主函数
run(main(...));
