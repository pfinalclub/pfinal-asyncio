<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Core\SchedulerInterface;
use function PfinalClub\Asyncio\{run, create_task, sleep, await};

/**
 * 三级调度模型使用示例
 * 
 * 演示SYSTEM/CONTROL/WORK三个优先级级别的任务调度
 */

echo "=== 三级调度模型示例 ===\n\n";

run(function() {
    $eventLoop = \PfinalClub\Asyncio\Core\EventLoop::getInstance();
    
    echo "🚀 开始三级调度演示...\n\n";
    
    // 1. SYSTEM级任务：最高优先级，立即执行
    echo "📊 创建SYSTEM级任务（cancel/timeout/cleanup）...\n";
    $systemTask = $eventLoop->schedule(
        function() {
            echo "  [SYSTEM] 系统级任务执行 - 立即处理\n";
            sleep(0.1);
            return "系统任务完成";
        },
        SchedulerInterface::PRIORITY_SYSTEM,
        'system_cleanup'
    );
    
    // 2. CONTROL级任务：中等优先级，专用队列
    echo "🎛️  创建CONTROL级任务（health check/metrics）...\n";
    $controlTasks = [];
    for ($i = 0; $i < 3; $i++) {
        $controlTasks[] = $eventLoop->schedule(
            function() use ($i) {
                echo "  [CONTROL] 控制面任务 {$i} 执行 - 中等优先级\n";
                sleep(0.2);
                return "控制任务 {$i} 完成";
            },
            SchedulerInterface::PRIORITY_CONTROL,
            "control_metric_{$i}"
        );
    }
    
    // 3. WORK级任务：低优先级，批量处理
    echo "💼 创建WORK级任务（HTTP/DB/IO操作）...\n";
    $workTasks = [];
    for ($i = 0; $i < 5; $i++) {
        $workTasks[] = $eventLoop->schedule(
            function() use ($i) {
                echo "  [WORK] 业务任务 {$i} 执行 - 低优先级\n";
                sleep(0.3);
                return "业务任务 {$i} 完成";
            },
            SchedulerInterface::PRIORITY_WORK,
            "work_io_{$i}"
        );
    }
    
    echo "\n⏳ 等待所有任务完成...\n\n";
    
    // 等待SYSTEM任务完成
    $systemResult = await($systemTask);
    echo "✅ SYSTEM任务结果: {$systemResult}\n";
    
    // 等待CONTROL任务完成
    foreach ($controlTasks as $index => $task) {
        $result = await($task);
        echo "✅ CONTROL任务 {$index} 结果: {$result}\n";
    }
    
    // 等待WORK任务完成
    foreach ($workTasks as $index => $task) {
        $result = await($task);
        echo "✅ WORK任务 {$index} 结果: {$result}\n";
    }
    
    echo "\n📈 获取调度统计信息...\n";
    
    // 获取调度统计
    $stats = $eventLoop->getScheduler()->getSchedulerStats();
    echo "调度统计:\n";
    echo "- SYSTEM任务数: {$stats['system_tasks']}\n";
    echo "- CONTROL任务数: {$stats['control_tasks']}\n";
    echo "- WORK任务数: {$stats['work_tasks']}\n";
    echo "- 当前队列大小: CONTROL={$stats['current_queue_size']['control']}, WORK={$stats['current_queue_size']['work']}\n";
    
    echo "\n🎯 三级调度模型演示完成！\n";
});

/**
 * 性能对比示例：传统调度 vs 三级调度
 */
echo "\n=== 性能对比示例 ===\n\n";

run(function() {
    $eventLoop = \PfinalClub\Asyncio\Core\EventLoop::getInstance();
    
    echo "🔍 性能对比：传统调度 vs 三级调度\n\n";
    
    // 传统调度：所有任务同等优先级
    $startTime = microtime(true);
    $traditionalTasks = [];
    
    for ($i = 0; $i < 10; $i++) {
        $traditionalTasks[] = create_task(function() use ($i) {
            sleep(0.1);
            return "传统任务 {$i}";
        });
    }
    
    foreach ($traditionalTasks as $task) {
        await($task);
    }
    
    $traditionalTime = microtime(true) - $startTime;
    
    // 三级调度：优先级区分
    $startTime = microtime(true);
    $priorityTasks = [];
    
    // SYSTEM任务（最高优先级）
    $priorityTasks[] = $eventLoop->schedule(function() {
        sleep(0.05);
        return "SYSTEM任务";
    }, SchedulerInterface::PRIORITY_SYSTEM);
    
    // CONTROL任务（中等优先级）
    for ($i = 0; $i < 3; $i++) {
        $priorityTasks[] = $eventLoop->schedule(function() use ($i) {
            sleep(0.1);
            return "CONTROL任务 {$i}";
        }, SchedulerInterface::PRIORITY_CONTROL);
    }
    
    // WORK任务（低优先级）
    for ($i = 0; $i < 6; $i++) {
        $priorityTasks[] = $eventLoop->schedule(function() use ($i) {
            sleep(0.15);
            return "WORK任务 {$i}";
        }, SchedulerInterface::PRIORITY_WORK);
    }
    
    foreach ($priorityTasks as $task) {
        await($task);
    }
    
    $priorityTime = microtime(true) - $startTime;
    
    echo "性能对比结果:\n";
    echo "- 传统调度时间: " . round($traditionalTime * 1000, 2) . "ms\n";
    echo "- 三级调度时间: " . round($priorityTime * 1000, 2) . "ms\n";
    echo "- 性能提升: " . round(($traditionalTime - $priorityTime) / $traditionalTime * 100, 2) . "%\n";
    
    echo "\n💡 三级调度模型可以更好地处理不同优先级的任务，提高系统响应性！\n";
});