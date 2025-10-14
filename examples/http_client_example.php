<?php
/**
 * AsyncIO HTTP 客户端示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task};
use PfinalClub\Asyncio\Http\AsyncHttpClient;

function main(): \Generator {
    $client = new AsyncHttpClient([
        'timeout' => 10,
        'follow_redirects' => true,
    ]);
    
    echo "=== AsyncIO HTTP 客户端示例 ===\n\n";
    
    // 示例 1: 单个 GET 请求
    echo "1. 发送单个 GET 请求...\n";
    $future1 = $client->get('https://httpbin.org/get?key=value');
    $response1 = yield $future1;
    
    echo "状态码: {$response1->getStatusCode()}\n";
    echo "响应体: " . substr($response1->getBody(), 0, 100) . "...\n\n";
    
    // 示例 2: POST 请求
    echo "2. 发送 POST 请求...\n";
    $future2 = $client->post('https://httpbin.org/post', [
        'name' => 'AsyncIO',
        'type' => 'PHP Library',
    ]);
    $response2 = yield $future2;
    
    echo "状态码: {$response2->getStatusCode()}\n";
    if ($response2->isSuccess()) {
        $json = $response2->json();
        echo "返回的表单数据: " . json_encode($json['form'] ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";
    }
    
    // 示例 3: 并发请求
    echo "3. 并发发送多个请求...\n";
    $urls = [
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/2',
        'https://httpbin.org/delay/1',
    ];
    
    $tasks = [];
    foreach ($urls as $i => $url) {
        $tasks[] = create_task((function() use ($client, $url, $i) {
            $startTime = microtime(true);
            $future = $client->get($url);
            $response = yield $future;
            $duration = microtime(true) - $startTime;
            
            echo "  请求 {$i} 完成 (耗时: " . round($duration, 2) . "s, 状态: {$response->getStatusCode()})\n";
            return $response;
        })());
    }
    
    $startTime = microtime(true);
    $responses = yield $tasks;
    $totalDuration = microtime(true) - $startTime;
    
    echo "  所有请求完成！总耗时: " . round($totalDuration, 2) . "s\n\n";
    
    // 示例 4: 带自定义请求头
    echo "4. 使用自定义请求头...\n";
    $future4 = $client->get('https://httpbin.org/headers', [
        'X-Custom-Header' => 'AsyncIO-Test',
        'Authorization' => 'Bearer test-token',
    ]);
    $response4 = yield $future4;
    
    if ($response4->isSuccess()) {
        $json = $response4->json();
        echo "服务器收到的请求头: \n";
        foreach ($json['headers'] ?? [] as $key => $value) {
            if (str_starts_with($key, 'X-') || $key === 'Authorization') {
                echo "  {$key}: {$value}\n";
            }
        }
    }
    
    echo "\n所有示例完成！\n";
}

// 运行（使用 Workerman 模式）
run(main(), useWorkerman: true);

