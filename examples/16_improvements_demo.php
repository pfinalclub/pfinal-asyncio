<?php

/**
 * 示例 16: v2.2.0 改进功能演示
 * 
 * 演示所有新增的改进功能：
 * 1. GatherException - 聚合异常处理
 * 2. Timer 自动清理 - wait_for() 改进
 * 3. Context - 协程上下文管理
 * 4. HTTP 重试机制
 * 5. TaskState 枚举
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep, wait_for, set_context, get_context};
use PfinalClub\Asyncio\GatherException;
use PfinalClub\Asyncio\TimeoutException;
use PfinalClub\Asyncio\Context;
use PfinalClub\Asyncio\Http\AsyncHttpClient;
use PfinalClub\Asyncio\Http\RetryPolicy;

echo "=== v2.2.0 改进功能演示 ===\n\n";

// ============================================
// 1. GatherException - 聚合异常处理
// ============================================
echo "【1】GatherException - 聚合异常处理\n";
echo "-------------------------------------------\n";

run(function() {
    $task1 = create_task(function() {
        sleep(0.1);
        return "任务1成功";
    });
    
    $task2 = create_task(function() {
        sleep(0.1);
        throw new \Exception("任务2失败");
    });
    
    $task3 = create_task(function() {
        sleep(0.1);
        return "任务3成功";
    });
    
    $task4 = create_task(function() {
        sleep(0.1);
        throw new \RuntimeException("任务4失败");
    });
    
    try {
        $results = gather($task1, $task2, $task3, $task4);
        echo "结果: " . implode(", ", $results) . "\n";
    } catch (GatherException $e) {
        echo "✅ 捕获到 GatherException\n";
        echo "  失败任务数: {$e->getFailedCount()}\n";
        echo "  成功任务数: {$e->getSuccessCount()}\n";
        echo "\n详细报告:\n";
        echo $e->getDetailedReport();
    }
});

echo "\n";

// ============================================
// 2. Timer 自动清理 - wait_for() 改进
// ============================================
echo "【2】Timer 自动清理 - wait_for() 改进\n";
echo "-------------------------------------------\n";

run(function() {
    // 2.1 正常完成
    try {
        $result = wait_for(function() {
            sleep(0.5);
            return "快速完成";
        }, 2.0);
        echo "✅ 正常完成: {$result}\n";
    } catch (TimeoutException $e) {
        echo "超时: {$e->getMessage()}\n";
    }
    
    // 2.2 超时
    try {
        $result = wait_for(function() {
            sleep(3);
            return "慢速任务";
        }, 1.0);
        echo "结果: {$result}\n";
    } catch (TimeoutException $e) {
        echo "✅ 正确捕获超时: {$e->getMessage()}\n";
    }
    
    // 2.3 任务失败
    try {
        $result = wait_for(function() {
            sleep(0.3);
            throw new \Exception("任务内部错误");
        }, 2.0);
    } catch (TimeoutException $e) {
        echo "超时\n";
    } catch (\Exception $e) {
        echo "✅ 任务失败（Timer 已清理）: {$e->getMessage()}\n";
    }
});

echo "\n";

// ============================================
// 3. Context - 协程上下文管理
// ============================================
echo "【3】Context - 协程上下文管理\n";
echo "-------------------------------------------\n";

run(function() {
    // 设置请求上下文
    set_context('request_id', 'req_' . uniqid());
    set_context('user_id', 12345);
    set_context('trace_level', 'debug');
    
    echo "✅ 主协程设置上下文:\n";
    echo "  Request ID: " . get_context('request_id') . "\n";
    echo "  User ID: " . get_context('user_id') . "\n";
    echo "  Trace Level: " . get_context('trace_level') . "\n\n";
    
    // 子任务自动继承上下文
    $tasks = [];
    for ($i = 1; $i <= 3; $i++) {
        $tasks[] = create_task(function() use ($i) {
            $requestId = get_context('request_id');
            $userId = get_context('user_id');
            
            echo "  子任务 {$i}:\n";
            echo "    - Request ID: {$requestId}\n";
            echo "    - User ID: {$userId}\n";
            
            sleep(0.1);
            return "完成";
        });
    }
    
    gather(...$tasks);
    
    echo "\n✅ 上下文统计: " . json_encode(Context::getStats()) . "\n";
});

echo "\n";

// ============================================
// 4. HTTP 重试机制
// ============================================
echo "【4】HTTP 重试机制 (模拟演示)\n";
echo "-------------------------------------------\n";

echo "创建带重试策略的 HTTP 客户端：\n";
echo "  - 最大重试次数: 3\n";
echo "  - 初始延迟: 0.1s\n";
echo "  - 退避乘数: 2.0\n";
echo "  - 可重试状态码: [408, 429, 500, 502, 503, 504]\n";

$retryPolicy = new RetryPolicy(
    maxRetries: 3,
    initialDelay: 0.1,
    maxDelay: 10.0,
    backoffMultiplier: 2.0
);

$client = new AsyncHttpClient([
    'retry_policy' => $retryPolicy,
    'timeout' => 5
]);

echo "\n✅ HTTP 客户端配置完成（带重试支持）\n";
echo "注意：实际请求需要在 Fiber 上下文中执行\n";

echo "\n";

// ============================================
// 5. TaskState 枚举
// ============================================
echo "【5】TaskState 枚举 - 任务状态管理\n";
echo "-------------------------------------------\n";

run(function() {
    $task1 = create_task(function() {
        sleep(0.5);
        return "完成";
    });
    
    $task2 = create_task(function() {
        sleep(0.2);
        throw new \Exception("失败");
    });
    
    $task3 = create_task(function() {
        sleep(10);
        return "永远不会完成";
    });
    $task3->cancel();
    
    sleep(0.6);  // 等待任务完成
    
    echo "任务1 状态: {$task1->getState()->format()}\n";
    echo "  - 持续时间: " . round($task1->getDuration() * 1000, 2) . "ms\n";
    echo "  - 统计: " . json_encode($task1->getStats(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
    
    echo "任务2 状态: {$task2->getState()->format()}\n";
    echo "  - 是否失败: " . ($task2->getState()->isFailure() ? '是' : '否') . "\n\n";
    
    echo "任务3 状态: {$task3->getState()->format()}\n";
    echo "  - 是否取消: " . ($task3->getState()->isCancelled() ? '是' : '否') . "\n";
});

echo "\n";

// ============================================
// 综合示例：所有功能结合
// ============================================
echo "【综合】所有功能结合演示\n";
echo "-------------------------------------------\n";

run(function() {
    // 设置全局上下文
    set_context('request_id', 'req_' . bin2hex(random_bytes(8)));
    set_context('start_time', microtime(true));
    
    echo "开始处理请求: " . get_context('request_id') . "\n\n";
    
    // 创建多个任务，部分会失败
    $tasks = [];
    for ($i = 1; $i <= 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            $requestId = get_context('request_id');
            echo "  任务 {$i} 开始 [{$requestId}]\n";
            
            sleep(0.2 * $i);
            
            // 任务 3 和 5 会失败
            if ($i === 3 || $i === 5) {
                throw new \Exception("任务 {$i} 模拟失败");
            }
            
            return "任务 {$i} 成功";
        });
    }
    
    try {
        $results = gather(...$tasks);
        echo "\n所有任务成功完成\n";
    } catch (GatherException $e) {
        echo "\n" . $e->getDetailedReport();
        
        // 获取成功的结果
        $successResults = $e->getResults();
        echo "成功的任务结果: " . implode(", ", $successResults) . "\n";
    }
    
    $elapsed = microtime(true) - get_context('start_time');
    echo "\n总耗时: " . round($elapsed, 3) . "s\n";
});

echo "\n=== 演示完成 ===\n";
echo "\n📊 改进总结：\n";
echo "1. ✅ GatherException - 收集所有失败，不再丢失信息\n";
echo "2. ✅ Timer 自动清理 - 防止资源泄漏\n";
echo "3. ✅ Context 管理 - 协程间共享上下文\n";
echo "4. ✅ HTTP 重试 - 智能指数退避\n";
echo "5. ✅ TaskState 枚举 - 类型安全的状态管理\n";
echo "\n🚀 AsyncIO v2.2.0 - 生产级别，更加健壮！\n";

