<?php
/**
 * 基准测试 1: 任务创建开销
 * 
 * 测试创建不同数量任务的性能
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use function PfinalClub\Asyncio\{run, create_task, sleep};

$runner = new BenchmarkRunner(warmupRounds: 3, testRounds: 10);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 1: 任务创建开销                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 测试 1: 创建 100 个任务
$runner->run("创建 100 个空任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $tasks[] = create_task((function() {
                yield sleep(0.001);
                return true;
            })());
        }
        
        // 等待所有任务完成
        foreach ($tasks as $task) {
            yield $task;
        }
    })());
});

// 测试 2: 创建 1000 个任务
$runner->run("创建 1000 个空任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 1000; $i++) {
            $tasks[] = create_task((function() {
                yield sleep(0.001);
                return true;
            })());
        }
        
        foreach ($tasks as $task) {
            yield $task;
        }
    })());
});

// 测试 3: 创建 5000 个任务
$runner->run("创建 5000 个空任务", function() {
    run((function(): \Generator {
        $tasks = [];
        for ($i = 0; $i < 5000; $i++) {
            $tasks[] = create_task((function() {
                yield sleep(0.001);
                return true;
            })());
        }
        
        foreach ($tasks as $task) {
            yield $task;
        }
    })());
});

// 测试 4: 嵌套任务创建
$runner->run("嵌套任务创建 (100 个，3 层)", function() {
    run((function(): \Generator {
        $level1Tasks = [];
        for ($i = 0; $i < 100; $i++) {
            $level1Tasks[] = create_task((function() {
                // 第二层
                $level2Task = create_task((function() {
                    // 第三层
                    $level3Task = create_task((function() {
                        yield sleep(0.001);
                        return "level3";
                    })());
                    yield $level3Task;
                    return "level2";
                })());
                yield $level2Task;
                return "level1";
            })());
        }
        
        foreach ($level1Tasks as $task) {
            yield $task;
        }
    })());
});

// 生成并保存报告
echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/01_task_creation.txt');

