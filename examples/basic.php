<?php
/**
 * 基础示例 - 展示基本的异步功能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task};

/**
 * 简单的异步函数
 */
function say_hello(string $name): \Generator
{
    echo "[" . date('H:i:s') . "] Hello, {$name}!\n";
    yield sleep(1);
    echo "[" . date('H:i:s') . "] Goodbye, {$name}!\n";
    return "Done with {$name}";
}

/**
 * 主函数
 */
function main(): \Generator
{
    echo "=== 基础异步示例 ===\n\n";
    
    // 示例 1: 简单的异步调用
    echo "示例 1: 简单异步\n";
    $result = yield say_hello("World");
    echo "结果: {$result}\n\n";
    
    // 示例 2: 创建任务并等待
    echo "示例 2: 创建任务\n";
    $task = create_task(say_hello("Task"));
    yield sleep(0.5); // 做其他事情
    echo "等待任务完成...\n";
    $result = yield $task;
    echo "任务结果: {$result}\n\n";
    
    // 示例 3: 多个顺序任务
    echo "示例 3: 顺序执行\n";
    yield say_hello("First");
    yield say_hello("Second");
    yield say_hello("Third");
}

// 运行主函数
run(main());

