<?php

/**
 * Redis 连接池使用示例
 * 
 * 演示:
 * 1. 初始化 Redis 连接池
 * 2. 基本 key-value 操作
 * 3. 列表、哈希表、集合、有序集合操作
 * 4. 并发操作
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;
use function PfinalClub\Asyncio\Cache\redis_init;
use function PfinalClub\Asyncio\Cache\cache_set;
use function PfinalClub\Asyncio\Cache\cache_get;
use function PfinalClub\Asyncio\Cache\cache_delete;
use function PfinalClub\Asyncio\Cache\cache_exists;
use PfinalClub\Asyncio\Cache\RedisPool;

echo "=== Redis 连接池示例 ===\n\n";

// 主函数
function main() {
    // 检查 Redis 扩展
    if (!extension_loaded('redis')) {
        echo "错误: Redis 扩展未安装\n";
        echo "安装方法: pecl install redis\n";
        return;
    }
    
    echo "1. 初始化 Redis 连接池...\n";
    
    // 初始化连接池
    try {
        redis_init([
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,  // 如果有密码,请填写
            'database' => 0,
        ]);
        echo "   ✓ 连接池初始化完成\n\n";
    } catch (\Exception $e) {
        echo "   ✗ 连接失败: {$e->getMessage()}\n";
        echo "   提示: 请确保 Redis 服务正在运行\n";
        echo "   启动命令: redis-server\n";
        return;
    }
    
    // 示例 2: 基本 key-value 操作
    echo "2. 基本 key-value 操作...\n";
    cache_set('test:name', 'AsyncIO');
    cache_set('test:version', '2.1.0', 60);  // 60秒过期
    
    $name = cache_get('test:name');
    $version = cache_get('test:version');
    
    echo "   - Name: {$name}\n";
    echo "   - Version: {$version}\n";
    echo "   - Exists: " . (cache_exists('test:name') ? 'Yes' : 'No') . "\n\n";
    
    // 示例 3: 计数器
    echo "3. 原子计数器...\n";
    RedisPool::set('test:counter', 0);
    for ($i = 0; $i < 5; $i++) {
        $count = RedisPool::incr('test:counter');
        echo "   - Count: {$count}\n";
    }
    echo "\n";
    
    // 示例 4: 列表操作
    echo "4. 列表操作 (队列)...\n";
    RedisPool::delete('test:queue');  // 清空
    RedisPool::lPush('test:queue', 'task1', 'task2', 'task3');
    $len = RedisPool::lLen('test:queue');
    echo "   - 队列长度: {$len}\n";
    
    while ($len > 0) {
        $task = RedisPool::rPop('test:queue');
        echo "   - 弹出任务: {$task}\n";
        $len = RedisPool::lLen('test:queue');
    }
    echo "\n";
    
    // 示例 5: 哈希表操作
    echo "5. 哈希表操作 (用户信息)...\n";
    RedisPool::hSet('test:user:1', 'name', 'Alice');
    RedisPool::hSet('test:user:1', 'email', 'alice@example.com');
    RedisPool::hSet('test:user:1', 'age', '25');
    
    $user = RedisPool::hGetAll('test:user:1');
    echo "   - User: {$user['name']}, Email: {$user['email']}, Age: {$user['age']}\n\n";
    
    // 示例 6: 集合操作
    echo "6. 集合操作 (标签)...\n";
    RedisPool::sAdd('test:tags', 'php', 'async', 'fiber', 'workerman');
    $tags = RedisPool::sMembers('test:tags');
    echo "   - Tags: " . implode(', ', $tags) . "\n\n";
    
    // 示例 7: 有序集合 (排行榜)
    echo "7. 有序集合操作 (排行榜)...\n";
    RedisPool::zAdd('test:leaderboard', 100, 'Alice');
    RedisPool::zAdd('test:leaderboard', 200, 'Bob');
    RedisPool::zAdd('test:leaderboard', 150, 'Charlie');
    
    $top3 = RedisPool::zRange('test:leaderboard', 0, 2, true);
    echo "   排行榜 (前3名):\n";
    $rank = 1;
    foreach ($top3 as $player => $score) {
        echo "   {$rank}. {$player}: {$score} 分\n";
        $rank++;
    }
    echo "\n";
    
    // 示例 8: 并发操作
    echo "8. 并发操作测试...\n";
    $startTime = microtime(true);
    
    $tasks = [];
    for ($i = 0; $i < 10; $i++) {
        $tasks[] = create_task(function() use ($i) {
            cache_set("test:concurrent:{$i}", "value_{$i}");
            return cache_get("test:concurrent:{$i}");
        });
    }
    
    $results = gather(...$tasks);
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    
    echo "   ✓ 10 个并发操作完成\n";
    echo "   - 总耗时: {$elapsed}ms\n\n";
    
    // 示例 9: 统计信息
    echo "9. 连接池统计信息:\n";
    $stats = RedisPool::getStats();
    echo "   - 已初始化: " . ($stats['initialized'] ? '是' : '否') . "\n";
    echo "   - 有连接: " . ($stats['has_connection'] ? '是' : '否') . "\n";
    echo "   - 连接存活: " . ($stats['connection_alive'] ? '是' : '否') . "\n";
    echo "   - 主机: {$stats['config']['host']}:{$stats['config']['port']}\n\n";
    
    // 清理
    echo "10. 清理测试数据...\n";
    $keys = ['test:name', 'test:version', 'test:counter', 'test:queue', 
             'test:user:1', 'test:tags', 'test:leaderboard'];
    foreach (range(0, 9) as $i) {
        $keys[] = "test:concurrent:{$i}";
    }
    RedisPool::delete($keys);
    echo "   ✓ 清理完成\n\n";
    
    echo "=== 示例完成 ===\n";
}

try {
    run(function() {
        main();
    });
} catch (\Throwable $e) {
    echo "错误: {$e->getMessage()}\n";
    echo "提示: 请确保 Redis 服务正在运行\n";
}

