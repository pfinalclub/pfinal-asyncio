<?php
/**
 * 示例 4: HTTP 客户端
 * 
 * 演示异步 HTTP 请求
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather};
use PfinalClub\Asyncio\Http\AsyncHttpClient;

echo "=== HTTP 客户端示例 ===\n\n";

run(function() {
    $client = new AsyncHttpClient([
        'timeout' => 10,
        'use_connection_pool' => true,
    ]);
    
    // 【示例 1】单个请求
    echo "【示例 1】单个 HTTP 请求\n";
    
    try {
        $response = $client->get('http://httpbin.org/delay/1');
        echo "  状态码: {$response->getStatusCode()}\n";
        echo "  响应长度: " . strlen($response->getBody()) . " 字节\n";
    } catch (\Exception $e) {
        echo "  错误: {$e->getMessage()}\n";
    }
    
    echo "\n";
    
    // 【示例 2】并发请求
    echo "【示例 2】并发 HTTP 请求 - 3个请求\n";
    
    $start = microtime(true);
    
    $task1 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
    $task2 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
    $task3 = create_task(fn() => $client->get('http://httpbin.org/delay/1'));
    
    $responses = gather($task1, $task2, $task3);
    
    $elapsed = microtime(true) - $start;
    
    foreach ($responses as $i => $response) {
        echo "  请求" . ($i + 1) . ": {$response->getStatusCode()}\n";
    }
    echo "  总耗时: " . round($elapsed, 2) . "秒 (顺序执行需3秒)\n";
    
    echo "\n";
    
    // 【示例 3】POST 请求
    echo "【示例 3】POST 请求\n";
    
    try {
        $response = $client->post('http://httpbin.org/post', [
            'name' => 'AsyncIO',
            'version' => '2.0',
        ]);
        
        $data = $response->json();
        echo "  状态码: {$response->getStatusCode()}\n";
        echo "  表单数据: " . json_encode($data['form'] ?? []) . "\n";
    } catch (\Exception $e) {
        echo "  错误: {$e->getMessage()}\n";
    }
    
    // 连接池统计
    echo "\n【连接池统计】\n";
    $stats = $client->getConnectionPoolStats();
    foreach ($stats as $host => $stat) {
        echo "  {$host}: 总计{$stat['total']}, 使用中{$stat['in_use']}, 可用{$stat['available']}\n";
    }
});


