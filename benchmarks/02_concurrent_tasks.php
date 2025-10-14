<?php
/**
 * 基准测试 2: 并发任务性能
 * 
 * 测试不同并发量下的性能表现
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use function PfinalClub\Asyncio\{run, create_task, gather, sleep};

$runner = new BenchmarkRunner(warmupRounds: 2, testRounds: 5);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 2: 并发任务性能                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 10 个并发任务
$runner->run("10 个并发任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = create_task((function() use ($i) {
                yield sleep(0.01);
                return $i * 2;
            })());
        }
        
        yield gather(...$tasks);
    })());
});

// 测试 2: 50 个并发任务
$runner->run("50 个并发任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 50; $i++) {
            $tasks[] = create_task((function() use ($i) {
                yield sleep(0.01);
                return $i * 2;
            })());
        }
        
        yield gather(...$tasks);
    })());
});

// 测试 3: 100 个并发任务
$runner->run("100 个并发任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $tasks[] = create_task((function() use ($i) {
                yield sleep(0.01);
                return $i * 2;
            })());
        }
        
        yield gather(...$tasks);
    })());
});

// 测试 4: 500 个并发任务
$runner->run("500 个并发任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 500; $i++) {
            $tasks[] = create_task((function() use ($i) {
                yield sleep(0.01);
                return $i * 2;
            })());
        }
        
        yield gather(...$tasks);
    })());
});

// 测试 5: 1000 个并发任务
$runner->run("1000 个并发任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 1000; $i++) {
            $tasks[] = create_task((function() use ($i) {
                yield sleep(0.01);
                return $i * 2;
            })());
        }
        
        yield gather(...$tasks);
    })());
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/02_concurrent_tasks.txt');

