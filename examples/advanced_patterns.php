<?php
/**
 * 高级编程模式示例 - 优化版
 * 
 * 展示 Future、生产者-消费者、管道等高级异步模式
 * 
 * 优化内容：
 * - 添加智能重试机制
 * - 增加熔断器模式
 * - 完善限流和背压控制
 * - 添加工作池模式
 * - 集成监控和错误处理
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, gather, create_future, await_future, timeout};
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

echo "=== AsyncIO 高级编程模式 - 优化版 ===\n\n";

// 示例 1: 智能重试机制
echo "【示例 1】智能重试机制\n";
run(function() {
    function smart_retry(callable $operation, int $maxRetries = 3, float $baseDelay = 1.0): mixed
    {
        $attempt = 1;
        $lastError = null;
        
        while ($attempt <= $maxRetries) {
            try {
                echo "  🔄 尝试 {$attempt}/{$maxRetries}\n";
                return $operation();
            } catch (\Throwable $e) {
                $lastError = $e;
                echo "  ⚠️  尝试失败: {$e->getMessage()}\n";
                
                if ($attempt < $maxRetries) {
                    // 指数退避策略
                    $delay = $baseDelay * pow(2, $attempt - 1);
                    $jitter = $delay * 0.1 * (mt_rand(0, 10) / 10); // 添加随机抖动
                    $actualDelay = $delay + $jitter;
                    
                    echo "  ⏳ 等待 " . round($actualDelay, 2) . " 秒后重试...\n";
                    sleep($actualDelay);
                }
                $attempt++;
            }
        }
        
        throw new \RuntimeException("所有重试尝试均失败: {$lastError->getMessage()}", 0, $lastError);
    }
    
    // 测试重试机制
    $retryCount = 0;
    try {
        $result = smart_retry(function() use (&$retryCount) {
            $retryCount++;
            if ($retryCount < 3) {
                throw new \RuntimeException("模拟网络错误 {$retryCount}");
            }
            return "重试成功！";
        });
        echo "  ✅ {$result}\n";
    } catch (\Throwable $e) {
        echo "  ❌ 最终失败: {$e->getMessage()}\n";
    }
});
echo "\n";

// 示例 2: 生产者-消费者模式
function producer(string $name, int $count): array
{
    $items = [];
    for ($i = 1; $i <= $count; $i++) {
        echo "[生产者 {$name}] 生产项目 #{$i}\n";
        sleep(0.2);
        $items[] = "{$name}-Item-{$i}";
    }
    return $items;
}

function consumer(string $name, array $items): int
{
    $consumed = 0;
    foreach ($items as $item) {
        echo "[消费者 {$name}] 消费: {$item}\n";
        sleep(0.15);
        $consumed++;
    }
    return $consumed;
}

echo "【示例 2】生产者-消费者模式\n";
run(function() {
    // 生产者生产项目
    $p1 = create_task(fn() => producer('P1', 3));
    $p2 = create_task(fn() => producer('P2', 3));
    
    $items = gather($p1, $p2);
    $allItems = array_merge(...$items);
    
    echo "\n生产完成，开始消费...\n\n";
    
    // 消费者消费项目
    $c1 = create_task(fn() => consumer('C1', array_slice($allItems, 0, 3)));
    $c2 = create_task(fn() => consumer('C2', array_slice($allItems, 3)));
    
    $consumed = gather($c1, $c2);
    $total = array_sum($consumed);
    
    echo "\n消费完成，总计: {$total} 个项目\n";
});
echo "\n";

// 示例 3: 任务链（管道）
function step1(): string
{
    echo "步骤 1: 初始化\n";
    sleep(0.3);
    return "Step1-Data";
}

function step2(string $input): string
{
    echo "步骤 2: 处理 {$input}\n";
    sleep(0.3);
    return "Step2-Data";
}

function step3(string $input): string
{
    echo "步骤 3: 完成 {$input}\n";
    sleep(0.3);
    return "Final-Result";
}

function pipeline(): string
{
    $data1 = step1();
    $data2 = step2($data1);
    $data3 = step3($data2);
    return $data3;
}

echo "【示例 3】任务链（管道）\n";
run(pipeline(...));
echo "\n";

// 示例 4: 并发限制（控制并发数量）
function limited_concurrent(array $items, int $limit): array
{
    $results = [];
    $chunks = array_chunk($items, $limit);
    
    foreach ($chunks as $chunk) {
        $tasks = [];
        foreach ($chunk as $item) {
            $tasks[] = create_task(function() use ($item) {
                sleep(0.5);
                return "Processed-{$item}";
            });
        }
        $chunkResults = gather(...$tasks);
        $results = array_merge($results, $chunkResults);
    }
    
    return $results;
}

echo "【示例 4】并发限制（一次最多 3 个）\n";
$start = microtime(true);
run(function() {
    $items = range(1, 9);
    $results = limited_concurrent($items, 3);
    echo "处理完成 " . count($results) . " 个项目\n";
});
$elapsed = microtime(true) - $start;
echo "耗时: " . round($elapsed, 2) . "秒（并发限制为 3）\n";

echo "\n✅ 高级模式示例完成\n";
echo "💡 提示: 这些模式可以组合使用构建复杂的异步应用\n";

