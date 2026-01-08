<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Concurrency\TaskGroup;
use function PfinalClub\Asyncio\{run, create_task, sleep, await};

// 示例：演示 TaskGroup 的使用
// TaskGroup 用于管理一组相关任务的生命周期

echo "=== TaskGroup 示例 ===\n";

// 示例 1: 基本使用
run(function() {
    echo "1. 基本使用：\n";
    
    $group = new TaskGroup();
    
    // 添加3个任务
    for ($i = 0; $i < 3; $i++) {
        $taskId = $i + 1;
        $task = create_task(function() use ($taskId) {
            echo "   任务 {$taskId} 开始\n";
            sleep(0.2);
            echo "   任务 {$taskId} 完成\n";
            return "结果 {$taskId}";
        });
        
        $group->addTask($task);
    }
    
    echo "   所有任务已添加到组中，等待完成...\n";
    echo "   运行中的任务数: {$group->getRunningTaskCount()}\n";
    
    // 等待所有任务完成
    $group->wait();
    
    echo "   所有任务已完成！\n";
    echo "   运行中的任务数: {$group->getRunningTaskCount()}\n";
});

// 示例 2: 取消任务组
echo "\n2. 取消任务组：\n";
run(function() {
    $group = new TaskGroup();
    
    // 添加5个长时间运行的任务
    for ($i = 0; $i < 5; $i++) {
        $taskId = $i + 1;
        $task = create_task(function() use ($taskId) {
            try {
                echo "   长时间任务 {$taskId} 开始执行\n";
                sleep(1);
                echo "   长时间任务 {$taskId} 完成\n";
                return "结果 {$taskId}";
            } catch (\PfinalClub\Asyncio\TaskCancelledException $e) {
                echo "   长时间任务 {$taskId} 被取消\n";
                return "被取消 {$taskId}";
            }
        });
        
        $group->addTask($task);
    }
    
    echo "   5个长时间任务已添加到组中\n";
    echo "   运行中的任务数: {$group->getRunningTaskCount()}\n";
    
    // 等待一小会儿
    sleep(0.3);
    
    echo "   取消任务组...\n";
    $group->cancel();
    
    // 等待所有任务完成
    $group->wait();
    
    echo "   所有任务已处理（取消或完成）\n";
    echo "   运行中的任务数: {$group->getRunningTaskCount()}\n";
});

// 示例 3: 带异常处理的任务组
echo "\n3. 带异常处理的任务组：\n";
run(function() {
    $group = new TaskGroup();
    
    // 添加正常任务
    for ($i = 0; $i < 2; $i++) {
        $taskId = $i + 1;
        $task = create_task(function() use ($taskId) {
            echo "   正常任务 {$taskId} 开始\n";
            sleep(0.2);
            echo "   正常任务 {$taskId} 完成\n";
            return "正常结果 {$taskId}";
        });
        
        $group->addTask($task);
    }
    
    // 添加会抛出异常的任务
    $exceptionTask = create_task(function() {
        echo "   异常任务开始\n";
        sleep(0.1);
        echo "   异常任务抛出异常\n";
        throw new \RuntimeException("测试异常");
    });
    
    $group->addTask($exceptionTask);
    
    // 添加另一个正常任务
    $lastTask = create_task(function() {
        echo "   最后一个任务开始\n";
        sleep(0.3);
        echo "   最后一个任务完成\n";
        return "最后一个结果";
    });
    
    $group->addTask($lastTask);
    
    echo "   混合任务已添加到组中\n";
    
    // 等待所有任务完成，即使有异常
    $group->wait();
    
    echo "   所有任务已处理，即使有异常\n";
});

// 示例 4: 异步等待任务组结果
echo "\n4. 异步等待任务组结果：\n";
run(function() {
    $group = new TaskGroup();
    $results = [];
    
    // 添加任务并收集结果
    for ($i = 0; $i < 4; $i++) {
        $taskId = $i + 1;
        $task = create_task(function() use ($taskId, &$results) {
            sleep(0.1 * $taskId);
            $result = "结果 {$taskId}";
            $results[] = $result;
            return $result;
        });
        
        $group->addTask($task);
    }
    
    // 异步等待并做其他事情
    $waitTask = create_task(function() use ($group) {
        $group->wait();
        return true;
    });
    
    // 在等待期间做其他工作
    for ($i = 0; $i < 5; $i++) {
        echo "   等待期间执行其他工作... {$i}\n";
        sleep(0.1);
    }
    
    // 确保任务组已完成
    await($waitTask);
    
    echo "   所有任务结果: " . implode(", ", $results) . "\n";
});

echo "\n=== 示例结束 ===\n";
