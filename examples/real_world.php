<?php
/**
 * 真实应用示例 - API 聚合服务
 * 
 * 一个完整的例子，展示如何构建生产级的异步应用
 * 包含：错误处理、超时控制、监控、性能优化
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, wait_for};
use PfinalClub\Asyncio\Http\AsyncHttpClient;
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;
use PfinalClub\Asyncio\TimeoutException;

echo "=== 真实应用示例：API 聚合服务 ===\n\n";

/**
 * API 聚合服务
 * 从多个数据源获取数据并聚合返回
 */
class ApiAggregator
{
    private AsyncHttpClient $client;
    private AsyncioMonitor $monitor;
    
    public function __construct()
    {
        $this->client = new AsyncHttpClient([
            'timeout' => 10,
            'use_connection_pool' => true,
        ]);
        $this->monitor = AsyncioMonitor::getInstance();
    }
    
    /**
     * 获取用户完整信息（从多个 API 聚合）
     */
    public function getUserProfile(int $userId): array
    {
        echo "开始聚合用户 #{$userId} 的数据...\n";
        
        // 并发请求多个 API
        $tasks = [
            'basic' => create_task(fn() => $this->fetchUserBasic($userId)),
            'posts' => create_task(fn() => $this->fetchUserPosts($userId)),
            'friends' => create_task(fn() => $this->fetchUserFriends($userId)),
        ];
        
        try {
            // 使用 gather 并发等待所有任务
            $results = gather(...$tasks);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'data' => $results,
                'timestamp' => time(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * 获取用户基本信息（带超时和错误处理）
     */
    private function fetchUserBasic(int $userId): array
    {
        try {
            $task = create_task(function() use ($userId) {
                // 模拟 API 请求
                $url = "https://jsonplaceholder.typicode.com/users/{$userId}";
                $response = $this->client->get($url);
                
                if (!$response->isSuccess()) {
                    throw new \Exception("API 返回错误: " . $response->getStatusCode());
                }
                
                return json_decode($response->getBody(), true);
            });
            
            // 设置 5 秒超时
            return wait_for($task, 5.0);
            
        } catch (TimeoutException $e) {
            echo "  [警告] 用户基本信息请求超时\n";
            return ['error' => 'timeout'];
        } catch (\Throwable $e) {
            echo "  [错误] 用户基本信息请求失败: {$e->getMessage()}\n";
            return ['error' => $e->getMessage()];
        }
    }
    
    /**
     * 获取用户帖子
     */
    private function fetchUserPosts(int $userId): array
    {
        try {
            $url = "https://jsonplaceholder.typicode.com/users/{$userId}/posts";
            $response = $this->client->get($url);
            
            if ($response->isSuccess()) {
                $posts = json_decode($response->getBody(), true);
                return array_slice($posts, 0, 5);  // 只返回前 5 条
            }
            
            return [];
        } catch (\Throwable $e) {
            echo "  [错误] 用户帖子请求失败: {$e->getMessage()}\n";
            return [];
        }
    }
    
    /**
     * 获取用户好友（模拟）
     */
    private function fetchUserFriends(int $userId): array
    {
        // 模拟获取好友列表
        return [
            ['id' => $userId + 1, 'name' => 'Friend 1'],
            ['id' => $userId + 2, 'name' => 'Friend 2'],
        ];
    }
    
    /**
     * 获取监控统计
     */
    public function getStats(): array
    {
        $snapshot = $this->monitor->snapshot();
        return [
            'memory_mb' => $snapshot['memory']['current_mb'],
            'active_fibers' => $snapshot['event_loop']['active_fibers'],
            'connection_pool' => $snapshot['connection_pool'] ?? [],
        ];
    }
}

// 运行服务
run(function() {
    $aggregator = new ApiAggregator();
    
    echo "【场景 1】单个用户查询\n";
    $start = microtime(true);
    $result = $aggregator->getUserProfile(1);
    $elapsed = microtime(true) - $start;
    
    if ($result['success']) {
        echo "✅ 聚合成功\n";
        echo "  用户名: " . ($result['data']['basic']['name'] ?? 'N/A') . "\n";
        echo "  帖子数: " . count($result['data']['posts']) . "\n";
        echo "  好友数: " . count($result['data']['friends']) . "\n";
    } else {
        echo "❌ 聚合失败: {$result['error']}\n";
    }
    echo "  耗时: " . round($elapsed, 2) . "秒\n\n";
    
    echo "【场景 2】批量查询（3 个用户）\n";
    $start = microtime(true);
    $tasks = [];
    for ($i = 1; $i <= 3; $i++) {
        $tasks[] = create_task(fn() => $aggregator->getUserProfile($i));
    }
    
    $results = gather(...$tasks);
    $elapsed = microtime(true) - $start;
    
    $successCount = count(array_filter($results, fn($r) => $r['success']));
    echo "✅ 完成 {$successCount}/3 个查询\n";
    echo "  并发耗时: " . round($elapsed, 2) . "秒\n\n";
    
    echo "【场景 3】系统监控\n";
    $stats = $aggregator->getStats();
    echo "  内存使用: {$stats['memory_mb']}MB\n";
    echo "  活跃 Fiber: {$stats['active_fibers']}\n";
    if (!empty($stats['connection_pool'])) {
        echo "  连接池:\n";
        foreach ($stats['connection_pool'] as $host => $pool) {
            echo "    {$host}: {$pool['in_use']}/{$pool['total']} 使用中\n";
        }
    }
});

echo "\n✅ API 聚合服务示例完成\n";
echo "💡 这个示例展示了生产级应用的关键要素：\n";
echo "   - 并发请求提升性能\n";
echo "   - 超时控制防止阻塞\n";
echo "   - 错误处理保证健壮性\n";
echo "   - 监控统计便于运维\n";

