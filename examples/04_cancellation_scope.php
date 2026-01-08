<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Concurrency\CancellationScope;
use function PfinalClub\Asyncio\{run, create_task, sleep, await};

// 示例：演示 CancellationScope 的使用
// CancellationScope 用于管理任务的生命周期和取消

echo "=== CancellationScope 示例 ===\n";

// 示例 1: 基本使用
run(function() {
    echo "1. 基本使用：\n";
    
    $result = CancellationScope::run(function() {
        $task = create_task(function() {
            sleep(0.1);
            return '任务完成';
        });
        
        $taskResult = await($task);
        return "作用域结果: {$taskResult}";
    });
    
    echo "   结果: {$result}\n";
});

// 示例 2: 取消作用域
echo "\n2. 取消作用域：\n";
run(function() {
    $scope = null;
    $taskCompleted = false;
    
    // 启动一个带有长时间运行任务的作用域
    $scope = CancellationScope::run(function() use (&$taskCompleted) {
        $task = create_task(function() {
            try {
                echo "   长时间任务开始执行...\n";
                sleep(2); // 模拟长时间运行
                echo "   长时间任务完成！\n";
                return '任务完成';
            } catch (\PfinalClub\Asyncio\TaskCancelledException $e) {
                echo "   长时间任务被取消！\n";
                return '任务被取消';
            }
        });
        
        // 等待一小会儿，然后取消作用域
        sleep(0.5);
        
        return $task;
    });
    
    // 取消作用域
    echo "   取消作用域...\n";
    $scope->cancel();
    
    // 等待任务完成
    sleep(0.5);
});

// 示例 3: 嵌套作用域
echo "\n3. 嵌套作用域：\n";
run(function() {
    CancellationScope::run(function() {
        echo "   外部作用域开始\n";
        
        // 内部作用域
        $innerResult = CancellationScope::run(function() {
            echo "   内部作用域开始\n";
            $task = create_task(function() {
                sleep(0.2);
                return '内部任务结果';
            });
            
            $result = await($task);
            echo "   内部作用域结束\n";
            return $result;
        });
        
        echo "   内部作用域结果: {$innerResult}\n";
        echo "   外部作用域结束\n";
    });
});

// 示例 4: 作用域间隔离
echo "\n4. 作用域间隔离：\n";
run(function() {
    $scope1 = CancellationScope::run(function() {
        $task = create_task(function() {
            sleep(1);
            return 'scope1-task';
        });
        return $task;
    });
    
    $scope2 = CancellationScope::run(function() {
        $task = create_task(function() {
            sleep(1);
            return 'scope2-task';
        });
        return $task;
    });
    
    echo "   取消 scope1...\n";
    $scope1->cancel();
    
    echo "   scope1 被取消，scope2 继续执行...\n";
    
    sleep(1.5);
    
    echo "   scope2 状态: " . ($scope2->isCancelled() ? "已取消" : "正常") . "\n";
});

echo "\n=== 示例结束 ===\n";
