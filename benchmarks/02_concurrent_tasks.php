<?php
/**
 * 基准测试 2: 并发任务性能
 * 
 * 测试不同并发量下的性能表现
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use PfinalClub\Asyncio\EventLoop;

$runner = new BenchmarkRunner(warmupRounds: 2, testRounds: 5);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 2: 并发任务性能                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 10 个并发任务
$runner->run("10 个并发任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 10; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            return $i * 2;
        });
    }
    return count($tasks);
});

// 测试 2: 50 个并发任务
$runner->run("50 个并发任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 50; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            return $i * 2;
        });
    }
    return count($tasks);
});

// 测试 3: 100 个并发任务
$runner->run("100 个并发任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            return $i * 2;
        });
    }
    return count($tasks);
});

// 测试 4: 500 个并发任务
$runner->run("500 个并发任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 500; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            return $i * 2;
        });
    }
    return count($tasks);
});

// 测试 5: 1000 个并发任务
$runner->run("1000 个并发任务", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 1000; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            return $i * 2;
        });
    }
    return count($tasks);
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/02_concurrent_tasks.txt');

