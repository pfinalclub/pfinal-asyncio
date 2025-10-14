<?php
/**
 * 高级示例 - 复杂的异步场景
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function Pfinal\Async\{run, sleep, create_task, gather, wait_for, get_event_loop};
use Pfinal\Async\TimeoutException;

/**
 * 模拟 API 请求
 */
function fetch_user_data(int $userId): \Generator
{
    echo "获取用户 #{$userId} 的数据...\n";
    yield sleep(rand(1, 2));
    
    return [
        'id' => $userId,
        'name' => "用户{$userId}",
        'email' => "user{$userId}@example.com"
    ];
}

/**
 * 模拟数据库查询
 */
function fetch_user_posts(int $userId): \Generator
{
    echo "获取用户 #{$userId} 的文章...\n";
    yield sleep(rand(1, 3));
    
    $posts = [];
    for ($i = 1; $i <= rand(2, 5); $i++) {
        $posts[] = "文章 {$i}";
    }
    
    return $posts;
}

/**
 * 模拟外部服务调用
 */
function fetch_user_avatar(int $userId): \Generator
{
    echo "获取用户 #{$userId} 的头像...\n";
    yield sleep(rand(1, 2));
    
    return "https://avatar.example.com/user{$userId}.jpg";
}

/**
 * 获取完整的用户信息（并发获取多个数据源）
 */
function fetch_complete_user_profile(int $userId): \Generator
{
    echo "\n开始获取用户 #{$userId} 的完整信息...\n";
    
    // 并发获取所有相关数据
    $dataTask = create_task(fetch_user_data($userId));
    $postsTask = create_task(fetch_user_posts($userId));
    $avatarTask = create_task(fetch_user_avatar($userId));
    
    // 等待所有任务完成
    [$userData, $posts, $avatar] = yield gather($dataTask, $postsTask, $avatarTask);
    
    return [
        'user' => $userData,
        'posts' => $posts,
        'avatar' => $avatar
    ];
}

/**
 * 批量处理用户
 */
function process_users_batch(array $userIds): \Generator
{
    echo "\n=== 批量处理 " . count($userIds) . " 个用户 ===\n";
    
    $tasks = [];
    foreach ($userIds as $userId) {
        $tasks[] = create_task(fetch_complete_user_profile($userId));
    }
    
    $profiles = yield gather(...$tasks);
    
    echo "\n所有用户处理完成！\n";
    return $profiles;
}

/**
 * 重试机制
 */
function retry_on_failure(callable $func, int $maxRetries = 3, float $delay = 1.0): \Generator
{
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            $attempt++;
            echo "尝试 #{$attempt}...\n";
            
            $result = yield $func();
            echo "成功!\n";
            return $result;
            
        } catch (\Exception $e) {
            echo "失败: {$e->getMessage()}\n";
            
            if ($attempt >= $maxRetries) {
                throw new \Exception("达到最大重试次数 ({$maxRetries})");
            }
            
            echo "等待 {$delay} 秒后重试...\n";
            yield sleep($delay);
        }
    }
}

/**
 * 模拟不稳定的任务
 */
function unstable_task(float $failureRate = 0.7): \Generator
{
    yield sleep(0.5);
    
    if (rand(1, 100) / 100 < $failureRate) {
        throw new \Exception("任务随机失败");
    }
    
    return "任务成功完成";
}

/**
 * 生产者-消费者模式
 */
class AsyncQueue
{
    private array $items = [];
    private array $waiters = [];
    
    public function put($item): void
    {
        $this->items[] = $item;
        
        // 如果有等待者，唤醒一个
        if (!empty($this->waiters)) {
            $waiter = array_shift($this->waiters);
            $waiter->setResult($item);
        }
    }
    
    public function get(): \Generator
    {
        // 如果队列中有项目，直接返回
        if (!empty($this->items)) {
            return array_shift($this->items);
        }
        
        // 否则创建一个 future 等待
        $future = new \Pfinal\Async\Future();
        $this->waiters[] = $future;
        
        return yield $future;
    }
    
    public function size(): int
    {
        return count($this->items);
    }
}

/**
 * 生产者
 */
function producer(AsyncQueue $queue, int $count): \Generator
{
    echo "生产者开始生产...\n";
    
    for ($i = 1; $i <= $count; $i++) {
        yield sleep(0.5);
        $item = "物品-{$i}";
        echo "生产: {$item}\n";
        $queue->put($item);
    }
    
    echo "生产者完成\n";
}

/**
 * 消费者
 */
function consumer(AsyncQueue $queue, int $id, int $count): \Generator
{
    echo "消费者 #{$id} 开始消费...\n";
    
    for ($i = 1; $i <= $count; $i++) {
        $item = yield $queue->get();
        echo "消费者 #{$id} 消费: {$item}\n";
        yield sleep(1); // 模拟处理时间
    }
    
    echo "消费者 #{$id} 完成\n";
}

/**
 * 主函数
 */
function main(): \Generator
{
    echo "=== PHP AsyncIO 高级示例 ===\n";
    
    // 示例 1: 并发获取多个用户的完整信息
    echo "\n【示例 1】并发获取用户信息\n";
    $userIds = [1, 2, 3];
    $profiles = yield process_users_batch($userIds);
    
    echo "\n获取到的用户信息:\n";
    foreach ($profiles as $profile) {
        $user = $profile['user'];
        $postCount = count($profile['posts']);
        echo "  - {$user['name']} ({$user['email']}): {$postCount} 篇文章\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
    // 示例 2: 带重试的任务
    echo "\n【示例 2】重试机制\n";
    try {
        $result = yield retry_on_failure(
            fn() => unstable_task(0.7),
            maxRetries: 5,
            delay: 0.5
        );
        echo "最终结果: {$result}\n";
    } catch (\Exception $e) {
        echo "任务最终失败: {$e->getMessage()}\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
    // 示例 3: 生产者-消费者模式
    echo "\n【示例 3】生产者-消费者模式\n";
    $queue = new AsyncQueue();
    
    $producerTask = create_task(producer($queue, 10));
    $consumer1Task = create_task(consumer($queue, 1, 5));
    $consumer2Task = create_task(consumer($queue, 2, 5));
    
    yield gather($producerTask, $consumer1Task, $consumer2Task);
    
    echo "\n生产者-消费者示例完成\n";
    
    echo "\n" . str_repeat("=", 60) . "\n";
    
    // 示例 4: 超时与降级
    echo "\n【示例 4】超时与降级策略\n";
    
    function fetch_with_fallback(): \Generator
    {
        try {
            // 尝试从主服务获取数据（可能很慢）
            $result = yield wait_for(fetch_user_data(999), 1.5);
            return ['source' => 'primary', 'data' => $result];
        } catch (TimeoutException $e) {
            echo "主服务超时，使用缓存数据...\n";
            // 使用缓存或默认数据
            return [
                'source' => 'cache',
                'data' => ['id' => 999, 'name' => '缓存用户', 'email' => 'cached@example.com']
            ];
        }
    }
    
    $result = yield fetch_with_fallback();
    echo "数据来源: {$result['source']}\n";
    echo "用户名: {$result['data']['name']}\n";
    
    echo "\n=== 所有示例完成 ===\n";
}

// 运行主函数
run(main());

