<?php
/**
 * 基准测试 3: 上下文切换延迟
 * 
 * 测试任务切换的开销
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use function PfinalClub\Asyncio\{run, create_task, sleep};

$runner = new BenchmarkRunner(warmupRounds: 3, testRounds: 10);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 3: 上下文切换延迟                         ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 简单 yield
$runner->run("简单 yield (1000 次)", function() {
    run((function(): \Generator {
        for ($i = 0; $i < 1000; $i++) {
            yield sleep(0);
        }
    })());
});

// 测试 2: yield from 调用
$runner->run("yield from 调用 (1000 次)", function() {
    $innerCoro = function(): \Generator {
        yield sleep(0);
    };
    
    run((function() use ($innerCoro): \Generator {
        for ($i = 0; $i < 1000; $i++) {
            yield from $innerCoro();
        }
    })());
});

// 测试 3: 任务切换
$runner->run("任务切换 (100 个任务，每个 yield 10 次)", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $tasks[] = create_task((function() {
                for ($j = 0; $j < 10; $j++) {
                    yield sleep(0);
                }
            })());
        }
        
        foreach ($tasks as $task) {
            yield $task;
        }
    })());
});

// 测试 4: 多级任务嵌套 (简化版本)
$runner->run("多级任务嵌套 (3 级，共 10 个任务)", function() {
    run((function(): \Generator {
        // 第一级：3 个任务
        $level1 = [];
        for ($i = 0; $i < 3; $i++) {
            $level1[] = create_task((function() use ($i) {
                yield;
                // 第二级：每个任务创建 2 个子任务
                $level2 = [];
                for ($j = 0; $j < 2; $j++) {
                    $level2[] = create_task((function() use ($i, $j) {
                        yield;
                        return "L1-{$i}-L2-{$j}";
                    })());
                }
                $results = yield $level2;
                return $results;
            })());
        }
        $results = yield $level1;
        return count($results, COUNT_RECURSIVE);
    })());
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/03_context_switch.txt');

