<?php
/**
 * 示例 5: 错误处理
 * 
 * 演示异步任务中的异常处理
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep};

echo "=== 错误处理示例 ===\n\n";

run(function() {
    // 【示例 1】单个任务异常
    echo "【示例 1】捕获单个任务异常\n";
    
    try {
        $task = create_task(function() {
            sleep(0.5);
            throw new \RuntimeException("任务执行失败");
        });
        
        \PfinalClub\Asyncio\await($task);
    } catch (\RuntimeException $e) {
        echo "  ✓ 捕获到异常: {$e->getMessage()}\n";
    }
    
    echo "\n";
    
    // 【示例 2】并发任务中的异常
    echo "【示例 2】并发任务异常处理\n";
    
    $task1 = create_task(function() {
        sleep(1);
        return "任务1成功";
    });
    
    $task2 = create_task(function() {
        sleep(0.5);
        throw new \Exception("任务2失败");
    });
    
    $task3 = create_task(function() {
        sleep(1.5);
        return "任务3成功";
    });
    
    try {
        $results = gather($task1, $task2, $task3);
        echo "  结果: " . implode(", ", $results) . "\n";
    } catch (\Exception $e) {
        echo "  ✓ gather 捕获异常: {$e->getMessage()}\n";
        echo "  (一个任务失败会导致整个 gather 失败)\n";
    }
    
    echo "\n";
    
    // 【示例 3】单独处理每个任务
    echo "【示例 3】单独处理每个任务的结果\n";
    
    $tasks = [
        create_task(function() { 
            sleep(0.5); 
            return "任务A成功"; 
        }),
        create_task(function() { 
            sleep(0.5); 
            throw new \Exception("任务B失败"); 
        }),
        create_task(function() { 
            sleep(0.5); 
            return "任务C成功"; 
        }),
    ];
    
    // 等待所有任务完成
    sleep(1);
    
    foreach ($tasks as $i => $task) {
        try {
            $result = $task->getResult();
            echo "  任务" . chr(65 + $i) . ": ✓ {$result}\n";
        } catch (\Exception $e) {
            echo "  任务" . chr(65 + $i) . ": ✗ {$e->getMessage()}\n";
        }
    }
});

