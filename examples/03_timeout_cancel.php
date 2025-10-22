<?php
/**
 * 示例 3: 超时和取消
 * 
 * 演示任务超时控制和取消机制
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, wait_for, sleep};
use PfinalClub\Asyncio\{TimeoutException, TaskCancelledException};

echo "=== 超时和取消示例 ===\n\n";

run(function() {
    // 【示例 1】超时控制
    echo "【示例 1】超时控制\n";
    
    // 快速任务 - 不会超时
    try {
        $result = wait_for(function() {
            sleep(1);
            return "快速完成";
        }, timeout: 2.0);
        echo "  ✓ 任务完成: {$result}\n";
    } catch (TimeoutException $e) {
        echo "  ✗ 超时: {$e->getMessage()}\n";
    }
    
    // 慢速任务 - 会超时
    try {
        $result = wait_for(function() {
            sleep(3);
            return "太慢了";
        }, timeout: 1.0);
        echo "  ✓ 任务完成: {$result}\n";
    } catch (TimeoutException $e) {
        echo "  ✗ 超时: {$e->getMessage()}\n";
    }
    
    echo "\n";
    
    // 【示例 2】取消任务
    echo "【示例 2】取消任务\n";
    
    $task = create_task(function() {
        echo "  任务开始执行...\n";
        sleep(5);
        echo "  任务完成\n";
        return "完成";
    });
    
    sleep(0.1);  // 等待一小段时间
    
    if ($task->cancel()) {
        echo "  ✓ 任务已取消\n";
    }
    
    try {
        $result = \PfinalClub\Asyncio\await($task);
    } catch (TaskCancelledException $e) {
        echo "  ✗ 捕获取消异常: {$e->getMessage()}\n";
    }
});


