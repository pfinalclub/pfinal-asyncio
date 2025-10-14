<?php
/**
 * 超时控制示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, wait_for, create_task};
use PfinalClub\Asyncio\TimeoutException;

/**
 * 快速任务
 */
function quick_task(): \Generator
{
    echo "快速任务开始...\n";
    yield sleep(1);
    echo "快速任务完成!\n";
    return "快速结果";
}

/**
 * 慢速任务
 */
function slow_task(): \Generator
{
    echo "慢速任务开始...\n";
    yield sleep(5);
    echo "慢速任务完成!\n";
    return "慢速结果";
}

/**
 * 可能失败的任务
 */
function unreliable_task(bool $shouldFail = false): \Generator
{
    echo "不可靠任务开始...\n";
    yield sleep(2);
    
    if ($shouldFail) {
        throw new \Exception("任务失败!");
    }
    
    echo "不可靠任务完成!\n";
    return "成功结果";
}

/**
 * 主函数
 */
function main(): \Generator
{
    echo "=== 超时控制示例 ===\n\n";
    
    // 示例 1: 任务在超时前完成
    echo "示例 1: 快速任务（超时时间充足）\n";
    try {
        $result = yield wait_for(quick_task(), 3.0);
        echo "结果: {$result}\n";
    } catch (TimeoutException $e) {
        echo "超时: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 示例 2: 任务超时
    echo "示例 2: 慢速任务（会超时）\n";
    try {
        $result = yield wait_for(slow_task(), 2.0);
        echo "结果: {$result}\n";
    } catch (TimeoutException $e) {
        echo "捕获超时: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 示例 3: 多个任务，部分超时
    echo "示例 3: 多个任务的超时处理\n";
    
    $tasks = [
        ['name' => '任务1', 'coro' => quick_task(), 'timeout' => 2.0],
        ['name' => '任务2', 'coro' => slow_task(), 'timeout' => 2.0],
        ['name' => '任务3', 'coro' => quick_task(), 'timeout' => 3.0],
    ];
    
    $results = [];
    foreach ($tasks as $taskInfo) {
        try {
            echo "执行 {$taskInfo['name']}...\n";
            $result = yield wait_for($taskInfo['coro'], $taskInfo['timeout']);
            $results[$taskInfo['name']] = ['status' => 'success', 'result' => $result];
        } catch (TimeoutException $e) {
            $results[$taskInfo['name']] = ['status' => 'timeout', 'error' => $e->getMessage()];
        }
    }
    
    echo "\n任务结果汇总:\n";
    foreach ($results as $name => $result) {
        $status = $result['status'];
        if ($status === 'success') {
            echo "  ✓ {$name}: {$result['result']}\n";
        } else {
            echo "  ✗ {$name}: {$result['error']}\n";
        }
    }
    
    echo "\n" . str_repeat("-", 50) . "\n\n";
    
    // 示例 4: 异常处理与超时结合
    echo "示例 4: 异常和超时\n";
    
    try {
        echo "尝试执行可能失败的任务...\n";
        $result = yield wait_for(unreliable_task(true), 5.0);
        echo "结果: {$result}\n";
    } catch (TimeoutException $e) {
        echo "超时: {$e->getMessage()}\n";
    } catch (\Exception $e) {
        echo "任务异常: {$e->getMessage()}\n";
    }
}

// 运行主函数
run(main());

