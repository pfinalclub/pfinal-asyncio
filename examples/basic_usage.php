<?php
/**
 * 基础用法示例
 * 
 * 展示 AsyncIO 的核心 API：
 * - run() 启动事件循环
 * - sleep() 异步睡眠
 * - create_task() 创建任务
 * - await() 等待任务完成
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, await};

echo "=== AsyncIO 基础用法 ===\n\n";

// 1. 最简单的异步函数
function simple_async(): string
{
    echo "开始执行\n";
    sleep(1);  // 异步睡眠 1 秒
    echo "执行完成\n";
    return "完成";
}

echo "【示例 1】基本的 run() 和 sleep()\n";
$result = run(simple_async(...));
echo "返回值: {$result}\n\n";

// 2. 创建和等待任务
function fetch_data(int $id): array
{
    echo "获取数据 #{$id}...\n";
    sleep(0.5);
    return ['id' => $id, 'data' => "Data-{$id}"];
}

echo "【示例 2】create_task() 和 await()\n";
run(function() {
    // 创建任务（立即开始执行）
    $task = create_task(fn() => fetch_data(1));
    
    // 可以做其他事情
    echo "任务已创建，可以并行做其他事...\n";
    sleep(0.2);
    
    // 等待任务完成
    $data = await($task);
    echo "获取到数据: " . json_encode($data) . "\n";
});
echo "\n";

// 3. 多个并发任务
echo "【示例 3】并发执行多个任务\n";
$start = microtime(true);
run(function() {
    $task1 = create_task(fn() => fetch_data(1));
    $task2 = create_task(fn() => fetch_data(2));
    $task3 = create_task(fn() => fetch_data(3));
    
    // 等待所有任务
    $data1 = await($task1);
    $data2 = await($task2);
    $data3 = await($task3);
    
    echo "所有数据获取完成\n";
});
$elapsed = microtime(true) - $start;
echo "并发耗时: " . round($elapsed, 2) . "秒 (顺序执行需要 1.5 秒)\n\n";

// 4. 嵌套异步调用
function parent_task(): string
{
    echo "父任务开始\n";
    
    $child = create_task(function() {
        echo "子任务开始\n";
        sleep(0.3);
        echo "子任务完成\n";
        return "子结果";
    });
    
    $result = await($child);
    echo "父任务收到: {$result}\n";
    
    return "父结果";
}

echo "【示例 4】嵌套任务\n";
run(parent_task(...));
echo "\n";

echo "✅ 基础用法示例完成\n";

