<?php
/**
 * 示例 1: Hello World
 * 
 * 最简单的 AsyncIO 程序
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep};

echo "=== Hello World 示例 ===\n\n";

// 方式 1: 使用匿名函数
run(function() {
    echo "Hello\n";
    sleep(1);  // 异步睡眠 1 秒
    echo "World!\n";
});

echo "\n";

// 方式 2: 使用命名函数
function greet(string $name): string {
    echo "Hello, {$name}!\n";
    sleep(0.5);
    return "Greeted {$name}";
}

$result = run(fn() => greet("AsyncIO"));
echo "结果: {$result}\n";


