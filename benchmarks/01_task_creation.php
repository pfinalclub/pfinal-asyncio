<?php
/**
 * 基准测试 1: 任务创建开销
 * 
 * 测试创建不同数量任务的性能（仅测试创建，不测试执行）
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use PfinalClub\Asyncio\EventLoop;

$runner = new BenchmarkRunner(warmupRounds: 3, testRounds: 10);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 1: 任务创建开销                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 创建 100 个任务
$runner->run("创建 100 个空任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = $loop->createFiber(function() {
            return true;
        });
    }
    return count($tasks);
});

// 测试 2: 创建 1000 个任务
$runner->run("创建 1000 个空任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 1000; $i++) {
        $tasks[] = $loop->createFiber(function() {
            return true;
        });
    }
    return count($tasks);
});

// 测试 3: 创建 5000 个任务
$runner->run("创建 5000 个空任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 5000; $i++) {
        $tasks[] = $loop->createFiber(function() {
            return true;
        });
    }
    return count($tasks);
});

// 测试 4: 嵌套任务创建
$runner->run("嵌套任务创建 (100 个，3 层)", function() {
    $loop = EventLoop::getInstance();
    $level1Tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $level1Tasks[] = $loop->createFiber(function() use ($loop) {
            // 第二层
            $level2Task = $loop->createFiber(function() use ($loop) {
                // 第三层
                $level3Task = $loop->createFiber(function() {
                    return "level3";
                });
                return $level3Task->getId();
            });
            return $level2Task->getId();
        });
    }
    return count($level1Tasks);
});

// 生成并保存报告
echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/01_task_creation.txt');

