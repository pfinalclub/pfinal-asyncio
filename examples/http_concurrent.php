<?php
/**
 * 并发 HTTP 请求示例
 * 
 * 展示如何并发执行多个 HTTP 请求，大幅提升性能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather};
use PfinalClub\Asyncio\Http\AsyncHttpClient;

echo "=== 并发 HTTP 请求示例 ===\n\n";

run(function() {
    $client = new AsyncHttpClient(['timeout' => 10]);
    
    // 示例 1: 并发多个 GET 请求
    echo "【示例 1】并发多个 API 请求\n";
    $start = microtime(true);
    
    $task1 = create_task(fn() => $client->get('https://httpbin.org/delay/1'));
    $task2 = create_task(fn() => $client->get('https://httpbin.org/delay/2'));
    $task3 = create_task(fn() => $client->get('https://httpbin.org/delay/1'));
    
    try {
        $responses = gather($task1, $task2, $task3);
        $elapsed = round(microtime(true) - $start, 2);
        
        echo "完成 " . count($responses) . " 个请求，用时: {$elapsed}秒\n";
        echo "（顺序执行需要 4 秒，并发节省 " . round(4 - $elapsed, 1) . " 秒）\n";
        
        foreach ($responses as $i => $response) {
            echo "  请求 " . ($i + 1) . ": 状态码 {$response->getStatusCode()}\n";
        }
    } catch (\Throwable $e) {
        echo "请求失败: {$e->getMessage()}\n";
    }
    echo "\n";
    
    // 示例 2: 并发爬取多个页面
    echo "【示例 2】并发爬虫\n";
    $urls = [
        'https://httpbin.org/html',
        'https://httpbin.org/json',
        'https://httpbin.org/xml',
    ];
    
    $start = microtime(true);
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(fn() => $client->get($url));
    }
    
    try {
        $responses = gather(...$tasks);
        $elapsed = round(microtime(true) - $start, 2);
        
        echo "爬取 " . count($responses) . " 个页面，用时: {$elapsed}秒\n";
        foreach ($responses as $i => $response) {
            $size = strlen($response->getBody());
            echo "  页面 " . ($i + 1) . ": {$size} 字节\n";
        }
    } catch (\Throwable $e) {
        echo "爬取失败: {$e->getMessage()}\n";
    }
    echo "\n";
    
    // 示例 3: API 聚合（从多个 API 获取数据）
    echo "【示例 3】API 聚合\n";
    $start = microtime(true);
    
    $tasks = [
        'user' => create_task(fn() => $client->get('https://httpbin.org/uuid')),
        'ip' => create_task(fn() => $client->get('https://httpbin.org/ip')),
        'headers' => create_task(fn() => $client->get('https://httpbin.org/headers')),
    ];
    
    try {
        $results = gather(...$tasks);
        $elapsed = round(microtime(true) - $start, 2);
        
        echo "聚合 3 个 API 数据，用时: {$elapsed}秒\n";
        echo "数据已就绪，可以组合处理\n";
    } catch (\Throwable $e) {
        echo "API 聚合失败: {$e->getMessage()}\n";
    }
    echo "\n";
    
    // 示例 4: 大量并发请求
    echo "【示例 4】大量并发 (10 个请求)\n";
    $start = microtime(true);
    
    $tasks = [];
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task(fn() => $client->get('https://httpbin.org/get?id=' . $i));
    }
    
    try {
        $responses = gather(...$tasks);
        $elapsed = round(microtime(true) - $start, 2);
        $successCount = count(array_filter($responses, fn($r) => $r->isSuccess()));
        
        echo "完成 {$successCount}/{count($responses)} 个请求，用时: {$elapsed}秒\n";
        echo "平均每个请求: " . round($elapsed / count($responses), 2) . "秒\n";
    } catch (\Throwable $e) {
        echo "批量请求失败: {$e->getMessage()}\n";
    }
});

echo "\n✅ 并发 HTTP 请求示例完成\n";
echo "💡 性能提示: 并发请求可以大幅减少总耗时\n";

