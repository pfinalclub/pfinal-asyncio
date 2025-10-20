<?php
/**
 * HTTP 客户端示例 - 优化版
 * 
 * 展示如何使用 AsyncHttpClient 进行异步 HTTP 请求
 * 
 * 优化内容：
 * - 添加连接池配置和使用示例
 * - 增加 SSL/TLS 安全配置
 * - 添加重试机制和错误处理
 * - 集成性能监控和统计
 * - 展示更多 HTTP 方法和高级功能
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep};
use PfinalClub\Asyncio\Http\AsyncHttpClient;
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

echo "=== AsyncHttpClient 使用示例 - 优化版 ===\n\n";

run(function() {
    $monitor = AsyncioMonitor::getInstance();
    
    // 创建 HTTP 客户端（带连接池和 SSL 配置）
    $client = new AsyncHttpClient([
        'timeout' => 15,
        'connect_timeout' => 5,
        'follow_redirects' => true,
        'max_redirects' => 5,
        'use_connection_pool' => true,
        'connection_pool_size' => 10,
        'ssl_verify_peer' => true,
        'ssl_allow_self_signed' => false,
        'user_agent' => 'AsyncIO-HTTP-Client/2.0.2',
        'keep_alive' => true,
        'keep_alive_timeout' => 60,
    ]);
    
    echo "🔧 客户端配置：连接池启用，SSL 验证开启\n\n";
    
    // 示例 1: 基础 GET 请求（带重试机制）
    echo "【示例 1】GET 请求（带重试机制）\n";
    
    function retry_get(AsyncHttpClient $client, string $url, int $maxRetries = 3): mixed
    {
        $attempt = 1;
        while ($attempt <= $maxRetries) {
            try {
                echo "  尝试 {$attempt}/{$maxRetries}: {$url}\n";
                $response = $client->get($url);
                echo "  ✅ 请求成功 (状态码: {$response->getStatusCode()})\n";
                return $response;
            } catch (\Throwable $e) {
                echo "  ⚠️  请求失败: {$e->getMessage()}\n";
                if ($attempt < $maxRetries) {
                    echo "  🔄 等待 1 秒后重试...\n";
                    sleep(1);
                }
                $attempt++;
            }
        }
        throw new \RuntimeException("所有重试尝试均失败");
    }
    
    try {
        $response = retry_get($client, 'https://httpbin.org/get?name=AsyncIO');
        $data = json_decode($response->getBody(), true);
        echo "  响应数据: " . json_encode($data['args'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    } catch (\Throwable $e) {
        echo "  ❌ 最终失败: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 2: 多种 HTTP 方法演示
    echo "【示例 2】多种 HTTP 方法演示\n";
    
    $methods = [
        'POST' => fn() => $client->post('https://httpbin.org/post', ['data' => 'test']),
        'PUT' => fn() => $client->put('https://httpbin.org/put', ['update' => 'data']),
        'PATCH' => fn() => $client->request('PATCH', 'https://httpbin.org/patch', ['patch' => 'data']),
        'DELETE' => fn() => $client->delete('https://httpbin.org/delete'),
    ];
    
    foreach ($methods as $method => $request) {
        try {
            echo "  {$method} 请求...\n";
            $response = $request();
            echo "  ✅ {$method} 成功 (状态码: {$response->getStatusCode()})\n";
        } catch (\Throwable $e) {
            echo "  ❌ {$method} 失败: {$e->getMessage()}\n";
        }
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 3: 高级请求配置
    echo "【示例 3】高级请求配置\n";
    
    try {
        // 自定义请求头
        $response = $client->get('https://httpbin.org/headers', [
            'X-Custom-Header' => 'AsyncIO-Client',
            'X-Request-ID' => uniqid(),
            'Accept' => 'application/json',
            'Accept-Encoding' => 'gzip, deflate',
        ]);
        
        echo "  ✅ 自定义请求头成功\n";
        $headers = $response->getHeaders();
        echo "  响应头数量: " . count($headers) . "\n";
        
        // 显示部分响应头
        $sampleHeaders = array_slice($headers, 0, 3);
        foreach ($sampleHeaders as $name => $value) {
            echo "    {$name}: {$value[0]}\n";
        }
        
    } catch (\Throwable $e) {
        echo "  ❌ 高级配置失败: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 4: 文件上传和表单数据
    echo "【示例 4】文件上传和表单数据\n";
    
    try {
        // 模拟表单数据
        $formData = [
            'username' => 'async_user',
            'email' => 'user@example.com',
            'file' => [
                'name' => 'test.txt',
                'type' => 'text/plain',
                'content' => 'This is a test file content',
            ]
        ];
        
        $response = $client->post('https://httpbin.org/post', $formData, [
            'Content-Type' => 'multipart/form-data',
        ]);
        
        echo "  ✅ 表单提交成功 (状态码: {$response->getStatusCode()})\n";
        
    } catch (\Throwable $e) {
        echo "  ❌ 表单提交失败: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 5: 连接池和性能统计
    echo "【示例 5】连接池和性能统计\n";
    
    // 执行一组并发请求来展示连接池效果
    $urls = [
        'https://httpbin.org/delay/1',
        'https://httpbin.org/delay/2', 
        'https://httpbin.org/delay/1',
        'https://httpbin.org/bytes/1024',
        'https://httpbin.org/status/200',
    ];
    
    $start = microtime(true);
    $tasks = [];
    
    foreach ($urls as $index => $url) {
        $tasks[] = create_task(function() use ($client, $url, $index) {
            echo "  请求 {$index}: {$url}\n";
            $response = $client->get($url);
            return [
                'url' => $url,
                'status' => $response->getStatusCode(),
                'size' => strlen($response->getBody()),
            ];
        }, "http-request-{$index}");
    }
    
    $results = gather(...$tasks);
    $totalTime = round(microtime(true) - $start, 2);
    
    echo "\n  📊 并发请求结果:\n";
    foreach ($results as $result) {
        echo "    {$result['url']} -> 状态: {$result['status']}, 大小: {$result['size']}B\n";
    }
    echo "  总耗时: {$totalTime}秒\n";
    
    // 显示连接池统计
    $snapshot = $monitor->snapshot();
    if (isset($snapshot['connection_pool'])) {
        echo "\n  🔗 连接池状态:\n";
        foreach ($snapshot['connection_pool'] as $host => $pool) {
            echo "    {$host}: {$pool['in_use']}/{$pool['total']} 连接使用中\n";
            echo "      可用: {$pool['available']}, 等待: {$pool['waiting']}\n";
        }
    }
    
    echo "\n" . str_repeat("-", 60) . "\n\n";
    
    // 示例 6: 错误处理和异常情况
    echo "【示例 6】错误处理和异常情况\n";
    
    $testCases = [
        '超时请求' => 'https://httpbin.org/delay/10',
        '不存在的域名' => 'https://invalid-domain-that-does-not-exist-12345.com/',
        'SSL 错误' => 'https://expired.badssl.com/',
        '404 页面' => 'https://httpbin.org/status/404',
        '500 错误' => 'https://httpbin.org/status/500',
    ];
    
    foreach ($testCases as $desc => $url) {
        try {
            echo "  测试: {$desc}\n";
            $response = $client->get($url, [], 3); // 3秒超时
            echo "  ✅ 请求成功 (状态码: {$response->getStatusCode()})\n";
        } catch (\Throwable $e) {
            echo "  ❌ 请求失败: " . get_class($e) . " - {$e->getMessage()}\n";
        }
    }
});

echo "\n✅ HTTP 客户端示例优化完成\n";
echo "💡 新特性展示：\n";
echo "  - 连接池管理和统计\n";
echo "  - SSL/TLS 安全配置\n";
echo "  - 自动重试机制\n";
echo "  - 多种 HTTP 方法支持\n";
echo "  - 高级请求配置选项\n";
echo "  - 完整的错误处理体系\n";

