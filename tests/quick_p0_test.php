<?php
/**
 * 快速 P0 修复验证脚本
 * 
 * 快速测试 v2.0.4 的 3 个 P0 修复
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Semaphore;
use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;
use function PfinalClub\Asyncio\sleep;

echo "快速 P0 修复验证\n";
echo "================\n\n";

$allPassed = true;

// 测试 1: Semaphore 计数
echo "1. 测试 Semaphore 计数... ";
try {
    $minCount = PHP_INT_MAX;
    
    run(function() use (&$minCount) {
        $sem = new Semaphore(2);
        $tasks = [];
        
        for ($i = 0; $i < 5; $i++) {
            $tasks[] = create_task(function() use ($sem, &$minCount) {
                $sem->acquire();
                $minCount = min($minCount, $sem->getAvailable());
                $sem->release();
            });
        }
        
        gather(...$tasks);
    });
    
    if ($minCount >= 0) {
        echo "✅ PASS (最小计数: $minCount)\n";
    } else {
        echo "❌ FAIL (最小计数: $minCount)\n";
        $allPassed = false;
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// 测试 2: Production 类加载
echo "2. 测试 Production 类加载... ";
try {
    $classes = [
        'PfinalClub\Asyncio\Production\MultiProcessMode',
        'PfinalClub\Asyncio\Production\HealthCheck',
        'PfinalClub\Asyncio\Production\GracefulShutdown',
        'PfinalClub\Asyncio\Production\ResourceLimits',
    ];
    
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            throw new \Exception("Class $class not found");
        }
    }
    
    echo "✅ PASS\n";
} catch (\Throwable $e) {
    echo "❌ FAIL: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// 测试 3: 嵌套 run() 检测
echo "3. 测试嵌套 run() 检测... ";
try {
    run(function() {
        run(function() {
            // Should not reach here
        });
    });
    
    echo "❌ FAIL (应该抛出异常)\n";
    $allPassed = false;
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'Cannot call run() from within a Fiber context')) {
        echo "✅ PASS\n";
    } else {
        echo "❌ FAIL (错误的异常消息)\n";
        $allPassed = false;
    }
} catch (\Throwable $e) {
    echo "❌ FAIL (错误的异常类型): " . get_class($e) . "\n";
    $allPassed = false;
}

// 测试 4: 正确的嵌套操作
echo "4. 测试正确的嵌套操作... ";
try {
    $result = run(function() {
        $task = create_task(function() {
            return 'success';
        });
        return gather($task);
    });
    
    if ($result && $result[0] === 'success') {
        echo "✅ PASS\n";
    } else {
        echo "❌ FAIL (返回值错误)\n";
        $allPassed = false;
    }
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// 总结
echo "\n";
echo "================\n";
if ($allPassed) {
    echo "✅ 所有测试通过！\n";
    exit(0);
} else {
    echo "❌ 部分测试失败\n";
    exit(1);
}

