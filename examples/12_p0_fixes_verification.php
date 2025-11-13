<?php
/**
 * P0 修复验证示例
 * 
 * 本示例验证 v2.0.4 中修复的 3 个 P0 问题：
 * 1. Semaphore 计数 bug
 * 2. Production PSR-4 映射
 * 3. EventLoop 嵌套调用检测
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Semaphore;
use PfinalClub\Asyncio\Production\MultiProcessMode;
use PfinalClub\Asyncio\Production\HealthCheck;
use PfinalClub\Asyncio\Production\GracefulShutdown;
use PfinalClub\Asyncio\Production\ResourceLimits;

use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;
use function PfinalClub\Asyncio\sleep;
use function PfinalClub\Asyncio\await;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║         P0 修复验证测试 (v2.0.4)                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// ========================================
// 测试 1: Semaphore 计数正确性
// ========================================
echo "📋 测试 1: Semaphore 计数正确性\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$test1Passed = true;
$minCount = PHP_INT_MAX;

run(function() use (&$test1Passed, &$minCount) {
    $sem = new Semaphore(3);
    $tasks = [];
    
    echo "初始状态: 最大={$sem->getMax()}, 可用={$sem->getAvailable()}\n\n";
    
    // 创建 10 个并发任务
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task(function() use ($sem, $i, &$test1Passed, &$minCount) {
            $sem->acquire();
            
            $stats = $sem->getStats();
            $available = $stats['available'];
            $waiting = $stats['waiting'];
            
            // 记录最小计数
            $minCount = min($minCount, $available);
            
            echo sprintf(
                "  [%s] 任务 %2d: 可用=%d, 使用=%d, 等待=%d\n",
                date('H:i:s'),
                $i,
                $available,
                $stats['in_use'],
                $waiting
            );
            
            // 验证计数是否为负数
            if ($available < 0) {
                $test1Passed = false;
                echo "    ❌ 错误: 可用计数为负数!\n";
            }
            
            sleep(0.001); // 1ms - 快速测试
            
            $sem->release();
        });
    }
    
    gather(...$tasks);
});

echo "\n结果:\n";
echo "  最小计数: $minCount\n";
if ($test1Passed && $minCount >= 0) {
    echo "  ✅ 测试通过: Semaphore 计数始终 >= 0\n\n";
} else {
    echo "  ❌ 测试失败: Semaphore 计数出现负数\n\n";
}

// ========================================
// 测试 2: Production 类自动加载
// ========================================
echo "📋 测试 2: Production 类自动加载\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$test2Passed = true;

try {
    // 测试 MultiProcessMode
    echo "  检查 MultiProcessMode... ";
    if (class_exists('PfinalClub\Asyncio\Production\MultiProcessMode')) {
        echo "✅\n";
    } else {
        echo "❌\n";
        $test2Passed = false;
    }
    
    // 测试 HealthCheck
    echo "  检查 HealthCheck... ";
    if (class_exists('PfinalClub\Asyncio\Production\HealthCheck')) {
        echo "✅\n";
    } else {
        echo "❌\n";
        $test2Passed = false;
    }
    
    // 测试 GracefulShutdown
    echo "  检查 GracefulShutdown... ";
    if (class_exists('PfinalClub\Asyncio\Production\GracefulShutdown')) {
        echo "✅\n";
    } else {
        echo "❌\n";
        $test2Passed = false;
    }
    
    // 测试 ResourceLimits
    echo "  检查 ResourceLimits... ";
    if (class_exists('PfinalClub\Asyncio\Production\ResourceLimits')) {
        echo "✅\n";
    } else {
        echo "❌\n";
        $test2Passed = false;
    }
    
    // 测试实例化
    echo "  测试实例化 HealthCheck... ";
    $health = new HealthCheck();
    echo "✅\n";
    
    echo "  测试实例化 ResourceLimits... ";
    $limits = new ResourceLimits();
    echo "✅\n";
    
    echo "\n结果:\n";
    if ($test2Passed) {
        echo "  ✅ 测试通过: 所有 Production 类正常加载\n\n";
    } else {
        echo "  ❌ 测试失败: 部分类无法加载\n\n";
    }
    
} catch (\Throwable $e) {
    echo "❌\n";
    echo "  错误: " . $e->getMessage() . "\n\n";
    $test2Passed = false;
}

// ========================================
// 测试 3: EventLoop 嵌套调用检测
// ========================================
echo "📋 测试 3: EventLoop 嵌套调用检测\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$test3Passed = false;

try {
    echo "  尝试嵌套调用 run()... ";
    
    run(function() {
        // 在 Fiber 内部尝试调用 run()
        run(function() {
            echo "这不应该被执行\n";
        });
    });
    
    // 如果没有抛出异常，测试失败
    echo "❌\n";
    echo "  错误: 应该抛出异常但没有\n\n";
    
} catch (\RuntimeException $e) {
    echo "✅\n";
    echo "  捕获异常: {$e->getMessage()}\n";
    
    // 验证异常消息是否正确
    if (str_contains($e->getMessage(), 'Cannot call run() from within a Fiber context')) {
        $test3Passed = true;
        echo "\n结果:\n";
        echo "  ✅ 测试通过: 正确检测并阻止嵌套调用\n\n";
    } else {
        echo "\n结果:\n";
        echo "  ❌ 测试失败: 异常消息不正确\n\n";
    }
} catch (\Throwable $e) {
    echo "❌\n";
    echo "  错误: 捕获到错误类型的异常: " . get_class($e) . "\n";
    echo "  消息: {$e->getMessage()}\n\n";
}

// ========================================
// 测试 4: 正确的嵌套异步操作
// ========================================
echo "📋 测试 4: 正确的嵌套异步操作 (使用 create_task)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$test4Passed = false;

try {
    echo "  执行嵌套异步任务... ";
    
    $result = run(function() {
        echo "\n  主任务开始\n";
        
        // 正确的方式：使用 create_task
        $task1 = create_task(function() {
            echo "    子任务 1 开始\n";
            sleep(0.01);
            echo "    子任务 1 完成\n";
            return "结果 1";
        });
        
        $task2 = create_task(function() {
            echo "    子任务 2 开始\n";
            sleep(0.01);
            echo "    子任务 2 完成\n";
            return "结果 2";
        });
        
        $results = gather($task1, $task2);
        
        echo "  主任务完成\n";
        
        return $results;
    });
    
    echo "\n  返回值: " . json_encode($result) . "\n";
    
    if (is_array($result) && count($result) === 2) {
        $test4Passed = true;
        echo "\n结果:\n";
        echo "  ✅ 测试通过: create_task 正确执行嵌套异步操作\n\n";
    } else {
        echo "\n结果:\n";
        echo "  ❌ 测试失败: 返回值不正确\n\n";
    }
    
} catch (\Throwable $e) {
    echo "❌\n";
    echo "  错误: {$e->getMessage()}\n\n";
}

// ========================================
// 总结
// ========================================
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   测试结果总结                             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

$allPassed = $test1Passed && $test2Passed && $test3Passed && $test4Passed;

echo "测试 1 (Semaphore 计数):        " . ($test1Passed ? "✅ 通过" : "❌ 失败") . "\n";
echo "测试 2 (Production 类加载):    " . ($test2Passed ? "✅ 通过" : "❌ 失败") . "\n";
echo "测试 3 (嵌套调用检测):         " . ($test3Passed ? "✅ 通过" : "❌ 失败") . "\n";
echo "测试 4 (正确的嵌套操作):       " . ($test4Passed ? "✅ 通过" : "❌ 失败") . "\n";
echo "\n";

if ($allPassed) {
    echo "🎉 所有测试通过！v2.0.4 P0 修复已验证。\n";
    exit(0);
} else {
    echo "⚠️  部分测试失败，请检查代码。\n";
    exit(1);
}

