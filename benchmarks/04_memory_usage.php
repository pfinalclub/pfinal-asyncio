<?php
/**
 * 基准测试 4: 内存使用分析
 * 
 * 测试不同任务量的内存占用
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use function PfinalClub\Asyncio\{run, create_task, sleep};

$runner = new BenchmarkRunner(warmupRounds: 2, testRounds: 5);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 4: 内存使用分析                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 100 个长生命周期任务
$runner->run("100 个任务 (长生命周期)", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $tasks[] = create_task((function() use ($i) {
                // 分配一些数据
                $data = str_repeat("x", 1000);
                yield sleep(0.01);
                return strlen($data);
            })());
        }
        
        foreach ($tasks as $task) {
            yield $task;
        }
    })());
});

// 测试 2: 1000 个短生命周期任务
$runner->run("1000 个任务 (短生命周期)", function() {
    run((function(): \Generator {
        for ($i = 0; $i < 1000; $i++) {
            $task = create_task((function() use ($i) {
                $data = str_repeat("y", 100);
                yield sleep(0.001);
                return strlen($data);
            })());
            yield $task;
        }
    })());
});

// 测试 3: 任务with大数据
$runner->run("100 个任务 (每个 100KB 数据)", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $tasks[] = create_task((function() use ($i) {
                // 100KB 数据
                $data = str_repeat("z", 100 * 1024);
                yield sleep(0.01);
                return strlen($data);
            })());
        }
        
        foreach ($tasks as $task) {
            yield $task;
        }
    })());
});

// 测试 4: 循环创建和销毁任务
$runner->run("循环创建销毁 (100 轮，每轮 10 个任务)", function() {
    run((function(): \Generator {
        for ($round = 0; $round < 100; $round++) {
            $tasks = [];
            for ($i = 0; $i < 10; $i++) {
                $tasks[] = create_task((function() {
                    yield sleep(0.001);
                })());
            }
            
            foreach ($tasks as $task) {
                yield $task;
            }
            
            unset($tasks);
        }
    })());
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/04_memory_usage.txt');

