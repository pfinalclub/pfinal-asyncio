<?php
/**
 * 示例 6: 真实案例 - 网页爬虫
 * 
 * 并发爬取多个网页
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather};
use PfinalClub\Asyncio\Http\AsyncHttpClient;

echo "=== 网页爬虫示例 ===\n\n";

run(function() {
    $client = new AsyncHttpClient(['timeout' => 10]);
    
    // 要爬取的 URL 列表
    $urls = [
        'http://httpbin.org/get?page=1',
        'http://httpbin.org/get?page=2',
        'http://httpbin.org/get?page=3',
        'http://httpbin.org/delay/1',
        'http://httpbin.org/headers',
    ];
    
    echo "开始爬取 " . count($urls) . " 个页面...\n\n";
    
    $start = microtime(true);
    
    // 创建并发任务
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(function() use ($client, $url) {
            echo "  正在请求: {$url}\n";
            try {
                $response = $client->get($url);
                $size = strlen($response->getBody());
                return [
                    'url' => $url,
                    'status' => $response->getStatusCode(),
                    'size' => $size,
                    'success' => true,
                ];
            } catch (\Exception $e) {
                return [
                    'url' => $url,
                    'error' => $e->getMessage(),
                    'success' => false,
                ];
            }
        });
    }
    
    // 等待所有任务完成
    $results = gather(...$tasks);
    
    $elapsed = microtime(true) - $start;
    
    // 显示结果
    echo "\n【爬取结果】\n";
    $successCount = 0;
    $totalSize = 0;
    
    foreach ($results as $result) {
        if ($result['success']) {
            echo "  ✓ {$result['url']}\n";
            echo "    状态码: {$result['status']}, 大小: {$result['size']} 字节\n";
            $successCount++;
            $totalSize += $result['size'];
        } else {
            echo "  ✗ {$result['url']}\n";
            echo "    错误: {$result['error']}\n";
        }
    }
    
    echo "\n【统计】\n";
    echo "  成功: {$successCount}/" . count($urls) . "\n";
    echo "  总大小: {$totalSize} 字节\n";
    echo "  总耗时: " . round($elapsed, 2) . " 秒\n";
    echo "  平均速度: " . round(count($urls) / $elapsed, 2) . " 页/秒\n";
});


