<?php
/**
 * 示例 2: 并发任务
 * 
 * 演示如何创建和执行多个并发任务
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep};

echo "=== 并发任务示例 ===\n\n";

run(function() {
    echo "【示例 1】基础并发 - 3个任务同时执行\n";
    
    $start = microtime(true);
    
    // 创建 3 个任务
    $task1 = create_task(function() {
        echo "  任务1开始\n";
        sleep(1);
        echo "  任务1完成\n";
        return "结果1";
    });
    
    $task2 = create_task(function() {
        echo "  任务2开始\n";
        sleep(1);
        echo "  任务2完成\n";
        return "结果2";
    });
    
    $task3 = create_task(function() {
        echo "  任务3开始\n";
        sleep(1);
        echo "  任务3完成\n";
        return "结果3";
    });
    
    // 等待所有任务完成
    $results = gather($task1, $task2, $task3);
    
    $elapsed = microtime(true) - $start;
    echo "  总耗时: " . round($elapsed, 2) . "秒 (顺序执行需3秒)\n";
    echo "  结果: " . implode(", ", $results) . "\n\n";
    
    // 【示例 2】批量任务
    echo "【示例 2】批量任务 - 10个任务\n";
    $start = microtime(true);
    
    $tasks = [];
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(0.5);
            return "Task-{$i}";
        });
    }
    
    $results = gather(...$tasks);
    $elapsed = microtime(true) - $start;
    
    echo "  完成 " . count($results) . " 个任务\n";
    echo "  总耗时: " . round($elapsed, 2) . "秒 (顺序执行需5秒)\n";
});


