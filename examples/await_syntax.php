<?php
/**
 * await 语法示例
 * 演示如何使用类似 Python 的 await 语法
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function Pfinal\Async\{run, create_task, sleep, await_coro};
use function Pfinal\Async\Http\fetch_url;

echo "
╔════════════════════════════════════════════════════════════╗
║         PHP AsyncIO - await 语法糖示例                      ║
║    (使用 yield from 实现类似 Python await 的效果)            ║
╚════════════════════════════════════════════════════════════╝
\n";

/**
 * 示例 1: 基本的 yield from 用法
 */
function example_yield_from(): \Generator
{
    echo "\n【示例 1】基本的 yield from 用法\n";
    echo str_repeat("-", 60) . "\n";
    
    // 定义一个协程
    $coro = function(): \Generator {
        echo "  → 执行协程...\n";
        yield sleep(1);
        echo "  ✓ 协程完成\n";
        return "协程结果";
    };
    
    // 使用 yield from 等待协程（类似 Python 的 await）
    echo "使用 yield from 等待协程:\n";
    $result = yield from $coro();
    echo "结果: {$result}\n";
}

/**
 * 示例 2: 直接 yield 协程
 */
function example_direct_yield(): \Generator
{
    echo "\n【示例 2】直接 yield 协程\n";
    echo str_repeat("-", 60) . "\n";
    
    $task = create_task((function(): \Generator {
        echo "  → 任务开始...\n";
        yield sleep(1);
        echo "  ✓ 任务完成\n";
        return "任务结果";
    })());
    
    // 直接 yield 任务
    echo "直接 yield 任务:\n";
    $result = yield $task;
    echo "结果: {$result}\n";
}

/**
 * 示例 3: HTTP 请求的 await 风格
 */
function example_http_await(): \Generator
{
    echo "\n【示例 3】HTTP 请求的 await 风格\n";
    echo str_repeat("-", 60) . "\n";
    
    try {
        echo "使用 yield from 发起 HTTP 请求:\n";
        
        // 这就是类似 Python 的 await fetch_url('https://example.com') 的写法！
        $response = yield from fetch_url('http://httpbin.org/get');
        
        echo "✓ 请求成功\n";
        echo "  状态码: {$response['status_code']}\n";
        echo "  响应大小: " . strlen($response['body']) . " 字节\n";
    } catch (\Exception $e) {
        echo "✗ 请求失败: {$e->getMessage()}\n";
    }
}

/**
 * 示例 4: 对比不同的等待方式
 */
function example_comparison(): \Generator
{
    echo "\n【示例 4】不同等待方式的对比\n";
    echo str_repeat("-", 60) . "\n";
    
    $coro = function(): \Generator {
        yield sleep(0.5);
        return "结果";
    };
    
    echo "方式 1 - yield from (推荐，最接近 Python await):\n";
    $result1 = yield from $coro();
    echo "  结果: {$result1}\n\n";
    
    echo "方式 2 - yield 任务:\n";
    $task = create_task($coro());
    $result2 = yield $task;
    echo "  结果: {$result2}\n\n";
    
    echo "方式 3 - 直接 yield 协程:\n";
    $result3 = yield $coro();
    echo "  结果: {$result3}\n";
}

/**
 * 示例 5: 实际应用 - 完整的 await 风格代码
 */
function example_real_world(): \Generator
{
    echo "\n【示例 5】实际应用 - Python 风格的代码\n";
    echo str_repeat("-", 60) . "\n";
    
    // 定义异步函数
    $fetch_user = function($userId): \Generator {
        echo "  → 获取用户 #{$userId} 信息...\n";
        yield sleep(1);
        return ['id' => $userId, 'name' => "用户{$userId}"];
    };
    
    $fetch_posts = function($userId): \Generator {
        echo "  → 获取用户 #{$userId} 的文章...\n";
        yield sleep(1.5);
        return [['title' => '文章1'], ['title' => '文章2']];
    };
    
    echo "Python 风格的异步代码:\n\n";
    
    // 这段代码看起来非常像 Python asyncio！
    // Python: user = await fetch_user(1)
    // PHP:    $user = yield from $fetch_user(1);
    
    $userId = 1;
    $user = yield from $fetch_user($userId);
    echo "  用户信息: {$user['name']}\n";
    
    $posts = yield from $fetch_posts($userId);
    echo "  文章数量: " . count($posts) . "\n";
    
    echo "\n✓ 完成！代码风格是不是很像 Python? 😊\n";
}

/**
 * 示例 6: 展示推荐的写法
 */
function example_recommended_style(): \Generator
{
    echo "\n【示例 6】推荐的写法风格\n";
    echo str_repeat("-", 60) . "\n";
    
    echo "推荐写法对比:\n\n";
    
    echo "Python asyncio 风格:\n";
    echo "  result = await some_coroutine()\n\n";
    
    echo "PHP AsyncIO 等效写法:\n";
    echo "  \$result = yield from some_coroutine()\n\n";
    
    echo "或者更简洁的:\n";
    echo "  \$result = yield some_coroutine()\n\n";
    
    // 实际演示
    $demo_coro = function(): \Generator {
        yield sleep(0.5);
        return "演示结果";
    };
    
    echo "实际运行:\n";
    $result = yield from $demo_coro();
    echo "  ✓ {$result}\n";
}

/**
 * 主函数
 */
function main(): \Generator
{
    yield example_yield_from();
    yield example_direct_yield();
    yield example_http_await();
    yield example_comparison();
    yield example_real_world();
    yield example_recommended_style();
    
    echo "\n";
    echo str_repeat("=", 60) . "\n";
    echo "🎉 await 语法示例完成！\n";
    echo str_repeat("=", 60) . "\n";
    echo "\n";
    echo "总结:\n";
    echo "  • Python:  result = await coro()\n";
    echo "  • PHP:     \$result = yield from coro()\n";
    echo "  • 或简写:   \$result = yield coro()\n";
    echo "\n";
    echo "对于 HTTP 请求:\n";
    echo "  • Python:  response = await fetch('url')\n";
    echo "  • PHP:     \$response = yield from fetch_url('url')\n";
    echo "\n";
}

// 运行示例
try {
    run(main());
} catch (\Throwable $e) {
    echo "\n❌ 错误: {$e->getMessage()}\n";
    echo "堆栈跟踪:\n{$e->getTraceAsString()}\n";
}

