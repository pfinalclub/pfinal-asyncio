<?php
/**
 * 基准测试 4: 内存使用分析
 * 
 * 测试不同任务量的内存占用
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use PfinalClub\Asyncio\EventLoop;

$runner = new BenchmarkRunner(warmupRounds: 2, testRounds: 5);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 4: 内存使用分析                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 100 个任务with数据
$runner->run("100 个任务with数据", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            // 分配一些数据
            $data = str_repeat("x", 1000);
            return strlen($data);
        });
    }
    return count($tasks);
});

// 测试 2: 1000 个轻量任务
$runner->run("1000 个轻量任务", function() {
    $loop = EventLoop::getInstance();
    for ($i = 0; $i < 1000; $i++) {
        $loop->createFiber(function() use ($i) {
            $data = str_repeat("y", 100);
            return strlen($data);
        });
    }
    return 1000;
});

// 测试 3: 100 个任务with大数据
$runner->run("100 个任务with大数据 (100KB)", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            // 100KB 数据
            $data = str_repeat("z", 100 * 1024);
            return strlen($data);
        });
    }
    return count($tasks);
});

// 测试 4: 循环创建和销毁任务
$runner->run("循环创建销毁 (100 轮，每轮 10 个)", function() {
    $loop = EventLoop::getInstance();
    for ($round = 0; $round < 100; $round++) {
        $tasks = [];
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = $loop->createFiber(function() {
                return true;
            });
        }
        unset($tasks);
    }
    return 1000;
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/04_memory_usage.txt');

