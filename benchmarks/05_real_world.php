<?php
/**
 * 基准测试 5: 真实场景模拟
 * 
 * 模拟真实世界中的异步应用场景
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use PfinalClub\Asyncio\EventLoop;

$runner = new BenchmarkRunner(warmupRounds: 2, testRounds: 5);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 5: 真实场景模拟                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 场景 1: 模拟并发 API 请求
$runner->run("场景 1: 并发 API 请求 (10 个)", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 10; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            return ['id' => $i, 'data' => "response_{$i}"];
        });
    }
    return count($tasks);
});

// 场景 2: 数据处理流水线
$runner->run("场景 2: 数据处理流水线 (50 项)", function() {
    $loop = EventLoop::getInstance();
    for ($i = 0; $i < 50; $i++) {
        $loop->createFiber(function() use ($i) {
            $data = ['id' => $i, 'raw' => rand(1, 100)];
            $processed = ['id' => $data['id'], 'processed' => $data['raw'] * 2];
            return $processed;
        });
    }
    return 50;
});

// 场景 3: 批量任务处理
$runner->run("场景 3: 批量任务处理 (20 个)", function() {
    $loop = EventLoop::getInstance();
    $tasks = [];
    for ($i = 0; $i < 20; $i++) {
        $tasks[] = $loop->createFiber(function() use ($i) {
            return "task_{$i}";
        });
    }
    return count($tasks);
});

// 场景 4: 生产者-消费者模式
$runner->run("场景 4: 生产者-消费者 (5+3)", function() {
    $loop = EventLoop::getInstance();
    $queue = [];
    
    // 5个生产者
    for ($i = 0; $i < 5; $i++) {
        $loop->createFiber(function() use (&$queue, $i) {
            for ($j = 0; $j < 10; $j++) {
                $queue[] = "item_{$i}_{$j}";
            }
        });
    }
    
    // 3个消费者
    for ($i = 0; $i < 3; $i++) {
        $loop->createFiber(function() use (&$queue, $i) {
            $processed = 0;
            while ($processed < 17 && !empty($queue)) {
                array_shift($queue);
                $processed++;
            }
            return $processed;
        });
    }
    
    return 8; // 5 producers + 3 consumers
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/05_real_world.txt');

