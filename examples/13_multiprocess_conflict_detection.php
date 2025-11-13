<?php
/**
 * MultiProcessMode 冲突检测示例
 * 
 * 演示 MultiProcessMode 与 EventLoop::run() 的冲突检测
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Production\MultiProcessMode;

use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\sleep;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   MultiProcessMode 冲突检测示例                           ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 示例 1: 正确的使用方式
echo "【示例 1】正确的使用方式：先启用 MultiProcessMode\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "代码示例:\n";
echo "```php\n";
echo "// ✅ 正确：先启用 MultiProcessMode，再由它管理 EventLoop\n";
echo "MultiProcessMode::enable(function() {\n";
echo "    echo \"Worker 进程运行中...\\n\";\n";
echo "});\n";
echo "```\n\n";

// 示例 2: 错误的使用方式 (会触发冲突检测)
echo "【示例 2】错误的使用方式：先调用 run()，再启用 MultiProcessMode\n";
echo "═══════════════════════════════════════════════════════════\n";

try {
    echo "步骤 1: 调用 run() 初始化事件循环...\n";
    run(function() {
        sleep(0.001);
        echo "  EventLoop 已初始化\n";
    });
    
    echo "步骤 2: 尝试启用 MultiProcessMode...\n";
    MultiProcessMode::enable(function() {
        echo "这不会被执行\n";
    });
    
    echo "❌ 应该抛出异常但没有！\n";
    
} catch (\RuntimeException $e) {
    echo "✅ 正确捕获冲突！\n";
    echo "异常消息: {$e->getMessage()}\n\n";
}

// 说明
echo "【说明】\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "MultiProcessMode 和 EventLoop::run() 不能同时使用，因为：\n";
echo "1. 它们都会初始化 Workerman 的事件循环\n";
echo "2. MultiProcessMode 创建多进程环境\n";
echo "3. EventLoop::run() 用于单进程环境\n\n";

echo "【正确的使用模式】\n";
echo "───────────────────────────────────────────────────────────\n";
echo "模式 A: 单进程模式（简单场景）\n";
echo "```php\n";
echo "run(function() {\n";
echo "    // 你的异步代码\n";
echo "});\n";
echo "```\n\n";

echo "模式 B: 多进程模式（高并发场景）\n";
echo "```php\n";
echo "MultiProcessMode::enable(function() {\n";
echo "    // EventLoop::run() 会在每个 Worker 进程中被调用\n";
echo "    // 你的异步代码会在多个进程中运行\n";
echo "}, [\n";
echo "    'worker_count' => 4,  // 4 个进程\n";
echo "]);\n";
echo "```\n\n";

echo "【何时使用多进程模式】\n";
echo "───────────────────────────────────────────────────────────\n";
echo "✅ 推荐使用多进程：\n";
echo "  • CPU 密集型任务\n";
echo "  • 需要利用多核 CPU\n";
echo "  • 高并发场景 (1000+ 请求/秒)\n";
echo "  • 生产环境部署\n\n";

echo "✅ 推荐使用单进程：\n";
echo "  • 开发和测试\n";
echo "  • 简单脚本\n";
echo "  • 低并发场景\n";
echo "  • 调试需求\n\n";

echo "✅ 冲突检测测试完成！\n";

