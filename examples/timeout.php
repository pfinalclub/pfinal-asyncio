<?php
/**
 * 超时控制示例（基于 Fiber）
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, wait_for};
use PfinalClub\Asyncio\TimeoutException;

/**
 * 快速任务
 */
function fast_task(): string
{
    echo "快速任务开始...\n";
    sleep(1);
    echo "快速任务完成!\n";
    return "快速任务结果";
}

/**
 * 慢速任务
 */
function slow_task(): string
{
    echo "慢速任务开始...\n";
    sleep(5);
    echo "慢速任务完成!\n";
    return "慢速任务结果";
}

/**
 * 主函数
 */
function main(): mixed
{
    echo "=== 超时控制示例 (Fiber) ===\n\n";
    
    // 示例 1: 快速任务，不会超时
    echo "示例 1: 快速任务（超时限制 2 秒）\n";
    try {
        $result = wait_for(fast_task(...), 2.0);
        echo "结果: {$result}\n";
    } catch (TimeoutException $e) {
        echo "超时: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 示例 2: 慢速任务，会超时
    echo "示例 2: 慢速任务（超时限制 2 秒）\n";
    try {
        $result = wait_for(slow_task(...), 2.0);
        echo "结果: {$result}\n";
    } catch (TimeoutException $e) {
        echo "超时: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 示例 3: 多个任务，有些超时
    echo "示例 3: 多个任务，设置不同超时\n";
    
    $task1 = create_task(fn() => {
        echo "任务1开始\n";
        sleep(1);
        echo "任务1完成\n";
        return "任务1结果";
    }, 'task-1');
    
    $task2 = create_task(fn() => {
        echo "任务2开始\n";
        sleep(3);
        echo "任务2完成\n";
        return "任务2结果";
    }, 'task-2');
    
    // 任务1应该成功
    try {
        $result1 = wait_for(fn() => \PfinalClub\Asyncio\await($task1), 2.0);
        echo "任务1结果: {$result1}\n";
    } catch (TimeoutException $e) {
        echo "任务1超时\n";
    }
    
    // 任务2应该超时
    try {
        $result2 = wait_for(fn() => \PfinalClub\Asyncio\await($task2), 2.0);
        echo "任务2结果: {$result2}\n";
    } catch (TimeoutException $e) {
        echo "任务2超时: {$e->getMessage()}\n";
    }
    
    return "Timeout examples complete";
}

// 运行主函数
run(main(...));
