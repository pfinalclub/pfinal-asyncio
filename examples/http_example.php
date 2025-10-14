<?php
/**
 * HTTP 异步请求示例
 * 演示如何使用异步 HTTP 客户端
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep};
use function PfinalClub\Asyncio\Http\{fetch_url, http_get, http_post};

echo "
╔════════════════════════════════════════════════════════════╗
║           PHP AsyncIO - HTTP 异步请求示例                   ║
╚════════════════════════════════════════════════════════════╝
\n";

/**
 * 示例 1: 简单的异步 HTTP GET 请求
 */
function example_simple_get(): \Generator
{
    echo "\n【示例 1】简单的 GET 请求\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        echo "正在请求 httpbin.org...\n";
        $response = yield fetch_url('http://httpbin.org/get');
        
        echo "状态码: {$response['status_code']}\n";
        echo "状态文本: {$response['status_text']}\n";
        echo "响应体长度: " . strlen($response['body']) . " 字节\n";
        echo "✓ 请求成功\n";
    } catch (\Exception $e) {
        echo "✗ 请求失败: {$e->getMessage()}\n";
    }
}

/**
 * 示例 2: 并发请求多个 URL
 */
function example_concurrent_requests(): \Generator
{
    echo "\n【示例 2】并发请求多个 URL\n";
    echo str_repeat("-", 60) . "\n";
    
    $urls = [
        'http://httpbin.org/delay/1',
        'http://httpbin.org/delay/2',
        'http://httpbin.org/delay/1',
    ];
    
    echo "开始并发请求 " . count($urls) . " 个 URL...\n";
    $start = microtime(true);
    
    // 创建并发任务
    $tasks = [];
    foreach ($urls as $i => $url) {
        $tasks[] = create_task((function() use ($url, $i) {
            echo "  → 请求 URL " . ($i + 1) . "...\n";
            try {
                $response = yield fetch_url($url);
                echo "  ✓ URL " . ($i + 1) . " 完成 (状态码: {$response['status_code']})\n";
                return $response;
            } catch (\Exception $e) {
                echo "  ✗ URL " . ($i + 1) . " 失败: {$e->getMessage()}\n";
                return null;
            }
        })());
    }
    
    // 等待所有请求完成
    $results = yield gather(...$tasks);
    
    $elapsed = round(microtime(true) - $start, 2);
    $successCount = count(array_filter($results, fn($r) => $r !== null));
    
    echo "\n总结:\n";
    echo "  - 总请求数: " . count($urls) . "\n";
    echo "  - 成功: {$successCount}\n";
    echo "  - 失败: " . (count($urls) - $successCount) . "\n";
    echo "  - 总耗时: {$elapsed} 秒\n";
    echo "  - 平均耗时: " . round($elapsed / count($urls), 2) . " 秒/请求\n";
}

/**
 * 示例 3: POST 请求
 */
function example_post_request(): \Generator
{
    echo "\n【示例 3】POST 请求\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        $data = [
            'name' => 'PHP AsyncIO',
            'version' => '1.0.0',
            'description' => '基于 Workerman 的异步 IO 库'
        ];
        
        echo "发送 POST 请求...\n";
        echo "数据: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
        
        $response = yield http_post('http://httpbin.org/post', $data);
        
        echo "状态码: {$response['status_code']}\n";
        echo "✓ POST 请求成功\n";
    } catch (\Exception $e) {
        echo "✗ POST 请求失败: {$e->getMessage()}\n";
    }
}

/**
 * 示例 4: 自定义请求头
 */
function example_custom_headers(): \Generator
{
    echo "\n【示例 4】自定义请求头\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        $headers = [
            'X-Custom-Header' => 'PHP-AsyncIO',
            'Accept' => 'application/json',
        ];
        
        echo "发送带自定义头的请求...\n";
        $response = yield http_get('http://httpbin.org/headers', $headers);
        
        echo "状态码: {$response['status_code']}\n";
        echo "✓ 请求成功\n";
    } catch (\Exception $e) {
        echo "✗ 请求失败: {$e->getMessage()}\n";
    }
}

/**
 * 示例 5: 实际应用 - 获取多个 API 的数据
 */
function example_real_world(): \Generator
{
    echo "\n【示例 5】实际应用 - API 数据聚合\n";
    echo str_repeat("-", 60) . "\n";
    
    echo "模拟从多个 API 获取用户数据...\n";
    
    // 模拟不同的 API 端点
    $apis = [
        'profile' => 'http://httpbin.org/delay/1',
        'posts' => 'http://httpbin.org/delay/2',
        'friends' => 'http://httpbin.org/delay/1',
    ];
    
    $start = microtime(true);
    
    // 并发请求所有 API
    $tasks = [];
    foreach ($apis as $name => $url) {
        $tasks[$name] = create_task((function() use ($url, $name) {
            echo "  → 获取 {$name} 数据...\n";
            try {
                $response = yield fetch_url($url);
                echo "  ✓ {$name} 数据获取成功\n";
                return $response;
            } catch (\Exception $e) {
                echo "  ✗ {$name} 数据获取失败\n";
                return null;
            }
        })());
    }
    
    // 等待所有 API 响应
    $results = yield gather(...array_values($tasks));
    
    $elapsed = round(microtime(true) - $start, 2);
    
    echo "\n数据聚合完成:\n";
    $i = 0;
    foreach (array_keys($apis) as $name) {
        $status = $results[$i] ? '✓' : '✗';
        echo "  {$status} {$name}\n";
        $i++;
    }
    echo "  总耗时: {$elapsed} 秒 (顺序执行需要约 4 秒)\n";
}

/**
 * 主函数
 */
function main(): \Generator
{
    // 运行所有示例
    yield example_simple_get();
    yield example_concurrent_requests();
    yield example_post_request();
    yield example_custom_headers();
    yield example_real_world();
    
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "🎉 所有 HTTP 示例完成！\n";
    echo str_repeat("=", 60) . "\n";
}

// 运行示例
try {
    run(main());
} catch (\Throwable $e) {
    echo "\n❌ 错误: {$e->getMessage()}\n";
    echo "堆栈跟踪:\n{$e->getTraceAsString()}\n";
}

