<?php
/**
 * 超简单 P0 修复验证
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Semaphore;
use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\await;

echo "超简单 P0 修复验证\n";
echo "==================\n\n";

// 测试 1: Semaphore 基础功能
echo "1. Semaphore 基础... ";
try {
    run(function() {
        $sem = new Semaphore(3);
        
        // 获取许可
        $sem->acquire();
        assert($sem->getAvailable() === 2, "应该剩余 2 个许可");
        
        $sem->acquire();
        assert($sem->getAvailable() === 1, "应该剩余 1 个许可");
        
        // 释放许可
        $sem->release();
        assert($sem->getAvailable() === 2, "应该恢复到 2 个许可");
        
        $sem->release();
        assert($sem->getAvailable() === 3, "应该恢复到 3 个许可");
    });
    echo "✅\n";
} catch (\Throwable $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

// 测试 2: Production 类
echo "2. Production 类加载... ";
try {
    assert(class_exists('PfinalClub\Asyncio\Production\HealthCheck'));
    assert(class_exists('PfinalClub\Asyncio\Production\ResourceLimits'));
    echo "✅\n";
} catch (\Throwable $e) {
    echo "❌\n";
}

// 测试 3: 嵌套 run() 检测
echo "3. 嵌套 run() 检测... ";
try {
    run(function() {
        try {
            run(function() {});
            assert(false, "应该抛出异常");
        } catch (\RuntimeException $e) {
            assert(str_contains($e->getMessage(), 'Fiber context'));
        }
    });
    echo "✅\n";
} catch (\Throwable $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

// 测试 4: create_task
echo "4. create_task 基础... ";
try {
    $result = run(function() {
        $task = create_task(function() {
            return 'hello';
        });
        return await($task);
    });
    assert($result === 'hello');
    echo "✅\n";
} catch (\Throwable $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

echo "\n✅ 核心功能验证完成！\n";

