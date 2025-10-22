<?php
/**
 * 基准测试 3: 上下文切换延迟
 * 
 * 测试 Fiber suspend/resume 的开销
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use PfinalClub\Asyncio\EventLoop;

$runner = new BenchmarkRunner(warmupRounds: 3, testRounds: 10);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 3: 上下文切换延迟                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 简单 Fiber 创建
$runner->run("简单 Fiber 创建 (1000 个)", function() {
    $loop = EventLoop::getInstance();
    for ($i = 0; $i < 1000; $i++) {
        $loop->createFiber(function() {
            return true;
        });
    }
    return 1000;
});

// 测试 2: Fiber 创建with变量捕获
$runner->run("Fiber 创建with变量捕获 (1000 个)", function() {
    $loop = EventLoop::getInstance();
    for ($i = 0; $i < 1000; $i++) {
        $loop->createFiber(function() use ($i) {
            return $i;
        });
    }
    return 1000;
});

// 测试 3: 多层嵌套 Fiber
$runner->run("多层嵌套 Fiber (100 个，3 层)", function() {
    $loop = EventLoop::getInstance();
    for ($i = 0; $i < 100; $i++) {
        $loop->createFiber(function() use ($loop) {
            $loop->createFiber(function() use ($loop) {
                $loop->createFiber(function() {
                    return "nested";
                });
            });
        });
    }
    return 100;
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/03_context_switch.txt');

