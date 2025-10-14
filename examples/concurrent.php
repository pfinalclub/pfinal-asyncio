<?php
/**
 * 并发示例 - 展示多任务并发执行
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, gather};

/**
 * 模拟下载文件
 */
function download_file(string $filename, float $duration): \Generator
{
    $start = microtime(true);
    echo "[开始] 下载 {$filename}...\n";
    
    yield sleep($duration);
    
    $elapsed = round(microtime(true) - $start, 2);
    echo "[完成] {$filename} 下载完成 (用时: {$elapsed}秒)\n";
    
    return [
        'filename' => $filename,
        'size' => rand(1000, 9999) . 'KB',
        'duration' => $elapsed
    ];
}

/**
 * 处理数据
 */
function process_data(int $id): \Generator
{
    echo "处理数据 #{$id} - 第1步\n";
    yield sleep(0.5);
    
    echo "处理数据 #{$id} - 第2步\n";
    yield sleep(0.5);
    
    echo "处理数据 #{$id} - 完成\n";
    return "数据 #{$id} 已处理";
}

/**
 * 主函数 - 并发示例
 */
function main(): \Generator
{
    echo "=== 并发执行示例 ===\n\n";
    
    // 示例 1: 并发下载多个文件
    echo "示例 1: 并发下载文件\n";
    $start = microtime(true);
    
    $task1 = create_task(download_file('file1.zip', 2));
    $task2 = create_task(download_file('file2.zip', 1.5));
    $task3 = create_task(download_file('file3.zip', 1));
    
    // 使用 gather 等待所有任务完成
    $results = yield gather($task1, $task2, $task3);
    
    $total_time = round(microtime(true) - $start, 2);
    echo "\n所有下载完成！总用时: {$total_time}秒\n";
    echo "下载结果:\n";
    foreach ($results as $result) {
        echo "  - {$result['filename']}: {$result['size']}\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 示例 2: 并发处理数据
    echo "示例 2: 并发处理数据\n";
    $tasks = [];
    for ($i = 1; $i <= 3; $i++) {
        $tasks[] = create_task(process_data($i));
    }
    
    $results = yield gather(...$tasks);
    echo "\n处理结果:\n";
    foreach ($results as $result) {
        echo "  - {$result}\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 示例 3: 对比顺序执行和并发执行
    echo "示例 3: 性能对比\n";
    
    // 顺序执行
    echo "顺序执行:\n";
    $start = microtime(true);
    yield download_file('seq1.zip', 1);
    yield download_file('seq2.zip', 1);
    yield download_file('seq3.zip', 1);
    $sequential_time = round(microtime(true) - $start, 2);
    echo "顺序执行用时: {$sequential_time}秒\n\n";
    
    // 并发执行
    echo "并发执行:\n";
    $start = microtime(true);
    $tasks = [
        create_task(download_file('par1.zip', 1)),
        create_task(download_file('par2.zip', 1)),
        create_task(download_file('par3.zip', 1))
    ];
    yield gather(...$tasks);
    $concurrent_time = round(microtime(true) - $start, 2);
    echo "并发执行用时: {$concurrent_time}秒\n\n";
    
    $speedup = round($sequential_time / $concurrent_time, 2);
    echo "性能提升: {$speedup}x 倍\n";
}

// 运行主函数
run(main());

