<?php
/**
 * 基准测试 5: 真实场景模拟
 * 
 * 模拟真实应用场景的性能测试
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BenchmarkRunner.php';

use PfinalClub\Asyncio\Benchmarks\BenchmarkRunner;
use function PfinalClub\Asyncio\{run, create_task, gather, wait_for, sleep};

$runner = new BenchmarkRunner(warmupRounds: 2, testRounds: 5);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          基准测试 5: 真实场景模拟                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 场景 1: API 聚合（并发请求多个 API）
$runner->run("场景 1: API 聚合 (10 个并发请求)", function() {
    $mockApiCall = function(string $url): \Generator {
        // 模拟 API 延迟
        yield sleep(rand(10, 50) / 1000);
        return ['url' => $url, 'data' => str_repeat('x', rand(100, 1000))];
    };
    
    run((function() use ($mockApiCall): \Generator {
        $tasks = [];
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = create_task($mockApiCall("https://api{$i}.example.com"));
        }
        
        $results = yield $tasks; // gather 通过 yield 数组实现
        return count($results);
    })());
});

// 场景 2: 数据处理流水线
$runner->run("场景 2: 数据处理流水线 (50 项数据)", function() {
    $fetchData = function($id): \Generator {
        yield sleep(0.005);
        return ['id' => $id, 'raw' => rand(1, 100)];
    };
    
    $processData = function($data): \Generator {
        yield sleep(0.01);
        return ['id' => $data['id'], 'processed' => $data['raw'] * 2];
    };
    
    $saveData = function($data): \Generator {
        yield sleep(0.005);
        return true;
    };
    
    run((function() use ($fetchData, $processData, $saveData): \Generator {
        for ($i = 0; $i < 50; $i++) {
            $data = yield from $fetchData($i);
            $processed = yield from $processData($data);
            yield from $saveData($processed);
        }
    })());
});

// 场景 3: 批量任务with超时控制
$runner->run("场景 3: 批量任务with超时 (20 个任务，5s 超时)", function() {
    $unreliableTask = function($id): \Generator {
        // 模拟不稳定任务
        $delay = rand(1, 10) / 1000;
        yield sleep($delay);
        return ['id' => $id, 'success' => true];
    };
    
    run((function() use ($unreliableTask): \Generator {
        $tasks = [];
        for ($i = 0; $i < 20; $i++) {
            $task = create_task($unreliableTask($i));
            $tasks[] = create_task((function() use ($task) {
                try {
                    return yield wait_for($task, 5.0);
                } catch (\Exception $e) {
                    return ['error' => $e->getMessage()];
                }
            })());
        }
        
        $results = yield $tasks;
        return count($results);
    })());
});

// 场景 4: 混合负载（读写、计算、等待）
$runner->run("场景 4: 混合负载 (30 个混合任务)", function() {
    run((function(): \Generator {
        $tasks = [];
        
        // 10 个 I/O 任务
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = create_task((function() use ($i) {
                yield sleep(0.01);
                return "io-{$i}";
            })());
        }
        
        // 10 个计算任务
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = create_task((function() use ($i) {
                // 模拟计算
                $sum = 0;
                for ($j = 0; $j < 1000; $j++) {
                    $sum += $j;
                }
                yield sleep(0.001);
                return "compute-{$i}:{$sum}";
            })());
        }
        
        // 10 个快速任务
        for ($i = 0; $i < 10; $i++) {
            $tasks[] = create_task((function() use ($i) {
                yield sleep(0.001);
                return "fast-{$i}";
            })());
        }
        
        yield gather(...$tasks);
    })());
});

echo $runner->generateReport();
$runner->saveReport(__DIR__ . '/reports/05_real_world.txt');

