<?php
/**
 * 完整演示 - 展示 PHP AsyncIO 的主要功能
 * 这个文件演示了如何像使用 Python asyncio 一样使用 PHP
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{
    run,
    create_task,
    gather,
    wait_for,
    sleep,
    get_event_loop
};
use PfinalClub\Asyncio\TimeoutException;

echo "
╔════════════════════════════════════════════════════════════╗
║           PHP AsyncIO - 完整功能演示                        ║
║     基于 Workerman 实现的异步 IO 扩展包                     ║
╚════════════════════════════════════════════════════════════╝
\n";

/**
 * 演示 1: 基本异步操作
 */
function demo_basic(): \Generator
{
    echo "\n【演示 1】基本异步操作\n";
    echo str_repeat("-", 60) . "\n";
    
    echo "开始时间: " . date('H:i:s') . "\n";
    
    echo "执行异步睡眠 2 秒...\n";
    yield sleep(2);
    
    echo "睡眠结束: " . date('H:i:s') . "\n";
    echo "✓ 基本异步操作完成\n";
}

/**
 * 演示 2: 并发执行
 */
function demo_concurrent(): \Generator
{
    echo "\n【演示 2】并发执行多个任务\n";
    echo str_repeat("-", 60) . "\n";
    
    // 定义三个不同的任务
    $download1 = function(): \Generator {
        echo "  → 开始下载文件1...\n";
        yield sleep(2);
        echo "  ✓ 文件1 下载完成\n";
        return "file1.zip (1.2MB)";
    };
    
    $download2 = function(): \Generator {
        echo "  → 开始下载文件2...\n";
        yield sleep(1.5);
        echo "  ✓ 文件2 下载完成\n";
        return "file2.zip (800KB)";
    };
    
    $download3 = function(): \Generator {
        echo "  → 开始下载文件3...\n";
        yield sleep(1);
        echo "  ✓ 文件3 下载完成\n";
        return "file3.zip (500KB)";
    };
    
    $start = microtime(true);
    
    // 创建并发任务
    $task1 = create_task($download1());
    $task2 = create_task($download2());
    $task3 = create_task($download3());
    
    // 等待所有任务完成
    $results = yield gather($task1, $task2, $task3);
    
    $elapsed = round(microtime(true) - $start, 2);
    
    echo "\n并发下载结果:\n";
    foreach ($results as $i => $result) {
        echo "  " . ($i + 1) . ". {$result}\n";
    }
    echo "\n总耗时: {$elapsed} 秒 (如果顺序执行需要 4.5 秒)\n";
    echo "✓ 并发执行演示完成\n";
}

/**
 * 演示 3: 超时控制
 */
function demo_timeout(): \Generator
{
    echo "\n【演示 3】超时控制\n";
    echo str_repeat("-", 60) . "\n";
    
    $slow_task = function(): \Generator {
        echo "  → 执行慢速任务（需要 5 秒）...\n";
        yield sleep(5);
        return "慢速任务完成";
    };
    
    $fast_task = function(): \Generator {
        echo "  → 执行快速任务（需要 1 秒）...\n";
        yield sleep(1);
        return "快速任务完成";
    };
    
    // 测试 1: 快速任务，超时时间充足
    echo "\n测试 1: 快速任务 (超时 3 秒)\n";
    try {
        $result = yield wait_for($fast_task(), 3.0);
        echo "  ✓ {$result}\n";
    } catch (TimeoutException $e) {
        echo "  ✗ 超时: {$e->getMessage()}\n";
    }
    
    // 测试 2: 慢速任务，会超时
    echo "\n测试 2: 慢速任务 (超时 2 秒)\n";
    try {
        $result = yield wait_for($slow_task(), 2.0);
        echo "  ✓ {$result}\n";
    } catch (TimeoutException $e) {
        echo "  ✗ 捕获超时异常\n";
    }
    
    echo "\n✓ 超时控制演示完成\n";
}

/**
 * 演示 4: 错误处理
 */
function demo_error_handling(): \Generator
{
    echo "\n【演示 4】错误处理\n";
    echo str_repeat("-", 60) . "\n";
    
    $risky_task = function(bool $shouldFail): \Generator {
        echo "  → 执行任务...\n";
        yield sleep(0.5);
        
        if ($shouldFail) {
            throw new \Exception("任务执行失败！");
        }
        
        return "任务成功";
    };
    
    // 成功的任务
    echo "\n测试 1: 正常任务\n";
    try {
        $result = yield $risky_task(false);
        echo "  ✓ {$result}\n";
    } catch (\Exception $e) {
        echo "  ✗ 错误: {$e->getMessage()}\n";
    }
    
    // 失败的任务
    echo "\n测试 2: 失败的任务\n";
    try {
        $result = yield $risky_task(true);
        echo "  ✓ {$result}\n";
    } catch (\Exception $e) {
        echo "  ✓ 成功捕获异常: {$e->getMessage()}\n";
    }
    
    echo "\n✓ 错误处理演示完成\n";
}

/**
 * 演示 5: 实际应用场景 - 数据聚合
 */
function demo_real_world(): \Generator
{
    echo "\n【演示 5】实际应用 - 用户数据聚合\n";
    echo str_repeat("-", 60) . "\n";
    
    // 模拟获取用户基本信息
    $fetch_profile = function(int $userId): \Generator {
        echo "  → 获取用户 #{$userId} 基本信息...\n";
        yield sleep(1);
        return [
            'id' => $userId,
            'name' => "张三",
            'email' => "zhangsan@example.com"
        ];
    };
    
    // 模拟获取用户文章
    $fetch_posts = function(int $userId): \Generator {
        echo "  → 获取用户 #{$userId} 文章列表...\n";
        yield sleep(1.5);
        return [
            ['title' => 'PHP 异步编程入门', 'views' => 1234],
            ['title' => 'Workerman 实战', 'views' => 2345],
        ];
    };
    
    // 模拟获取用户统计
    $fetch_stats = function(int $userId): \Generator {
        echo "  → 获取用户 #{$userId} 统计数据...\n";
        yield sleep(1);
        return [
            'followers' => 456,
            'following' => 123,
            'total_posts' => 89
        ];
    };
    
    echo "\n开始聚合用户数据 (并发请求)...\n";
    $start = microtime(true);
    
    // 并发获取所有数据
    $userId = 1001;
    [$profile, $posts, $stats] = yield gather(
        create_task($fetch_profile($userId)),
        create_task($fetch_posts($userId)),
        create_task($fetch_stats($userId))
    );
    
    $elapsed = round(microtime(true) - $start, 2);
    
    echo "\n用户完整信息:\n";
    echo "  姓名: {$profile['name']}\n";
    echo "  邮箱: {$profile['email']}\n";
    echo "  文章数: {$stats['total_posts']}\n";
    echo "  粉丝数: {$stats['followers']}\n";
    echo "  最近文章: {$posts[0]['title']} ({$posts[0]['views']} 次浏览)\n";
    echo "\n数据聚合耗时: {$elapsed} 秒 (顺序执行需要 3.5 秒)\n";
    echo "性能提升: " . round(3.5 / $elapsed, 1) . "x\n";
    echo "✓ 实际应用演示完成\n";
}

/**
 * 演示 6: 任务管理
 */
function demo_task_management(): \Generator
{
    echo "\n【演示 6】任务管理与控制\n";
    echo str_repeat("-", 60) . "\n";
    
    $long_task = function(string $name): \Generator {
        for ($i = 1; $i <= 5; $i++) {
            echo "  {$name}: 步骤 {$i}/5\n";
            yield sleep(0.5);
        }
        return "{$name} 完成";
    };
    
    echo "\n创建后台任务...\n";
    $task = create_task($long_task("后台任务"));
    
    // 等待一段时间
    yield sleep(1.5);
    
    echo "\n检查任务状态:\n";
    echo "  任务名称: {$task->getName()}\n";
    echo "  任务 ID: {$task->getId()}\n";
    echo "  是否完成: " . ($task->isDone() ? "是" : "否") . "\n";
    
    // 等待任务完成
    echo "\n等待任务完成...\n";
    $result = yield $task;
    echo "  结果: {$result}\n";
    
    echo "\n✓ 任务管理演示完成\n";
}

/**
 * 主函数 - 运行所有演示
 */
function main(): \Generator
{
    // 运行所有演示
    yield demo_basic();
    yield demo_concurrent();
    yield demo_timeout();
    yield demo_error_handling();
    yield demo_real_world();
    yield demo_task_management();
    
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "🎉 所有演示完成！\n";
    echo str_repeat("=", 60) . "\n";
    echo "\n";
    echo "更多信息:\n";
    echo "  - 查看 README.md 了解完整文档\n";
    echo "  - 查看 examples/ 目录了解更多示例\n";
    echo "  - 查看 docs/QUICKSTART.md 快速入门\n";
    echo "  - 查看 docs/API.md 完整 API 参考\n";
    echo "\n";
}

// 运行演示
try {
    run(main());
} catch (\Throwable $e) {
    echo "\n❌ 错误: {$e->getMessage()}\n";
    echo "堆栈跟踪:\n{$e->getTraceAsString()}\n";
}

