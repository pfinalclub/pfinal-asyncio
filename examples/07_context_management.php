<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PfinalClub\Asyncio\Resource\Context;
use function PfinalClub\Asyncio\{run, create_task, sleep, await};

// 示例：演示 Context 上下文管理
// Context 用于在任务间传递上下文信息，支持继承和隔离

echo "=== Context 上下文管理示例 ===\n";

// 示例 1: 基本设置和获取
echo "1. 基本设置和获取：\n";
run(function() {
    // 设置上下文
    Context::set('user_id', 12345);
    Context::set('request_id', 'req-78901');
    Context::set('session_data', ['theme' => 'dark', 'language' => 'zh-CN']);
    
    // 获取上下文
    $userId = Context::get('user_id');
    $requestId = Context::get('request_id');
    $sessionData = Context::get('session_data');
    
    echo "   user_id: {$userId}\n";
    echo "   request_id: {$requestId}\n";
    echo "   session_data: " . json_encode($sessionData) . "\n";
    
    // 获取不存在的键，使用默认值
    $nonExistent = Context::get('non_existent_key', 'default_value');
    echo "   不存在的键（带默认值）: {$nonExistent}\n";
    
    // 检查键是否存在
    $hasUserId = Context::has('user_id');
    $hasNonExistent = Context::has('non_existent_key');
    echo "   has('user_id'): " . ($hasUserId ? '是' : '否') . "\n";
    echo "   has('non_existent_key'): " . ($hasNonExistent ? '是' : '否') . "\n";
});

// 示例 2: 上下文继承
echo "\n2. 上下文继承：\n";
run(function() {
    // 在父任务中设置上下文
    Context::set('parent_key', 'parent_value');
    Context::set('shared_key', 'parent_shared');
    
    echo "   父任务 - parent_key: " . Context::get('parent_key') . "\n";
    echo "   父任务 - shared_key: " . Context::get('shared_key') . "\n";
    
    // 创建子任务
    $childTask = create_task(function() {
        // 获取继承的上下文
        $parentKey = Context::get('parent_key');
        $sharedKey = Context::get('shared_key');
        
        echo "   子任务 - 继承的 parent_key: {$parentKey}\n";
        echo "   子任务 - 继承的 shared_key: {$sharedKey}\n";
        
        // 在子任务中设置新的上下文
        Context::set('child_key', 'child_value');
        // 修改共享上下文（只影响子任务）
        Context::set('shared_key', 'child_shared');
        
        echo "   子任务 - 新设置的 child_key: " . Context::get('child_key') . "\n";
        echo "   子任务 - 修改后的 shared_key: " . Context::get('shared_key') . "\n";
        
        // 创建嵌套子任务
        $nestedTask = create_task(function() {
            echo "   嵌套子任务 - 继承的 parent_key: " . Context::get('parent_key') . "\n";
            echo "   嵌套子任务 - 继承的 shared_key: " . Context::get('shared_key') . "\n";
            echo "   嵌套子任务 - 继承的 child_key: " . Context::get('child_key') . "\n";
        });
        
        await($nestedTask);
        
        return [
            'parent_key' => $parentKey,
            'child_key' => Context::get('child_key'),
            'child_shared_key' => Context::get('shared_key')
        ];
    });
    
    $childResult = await($childTask);
    
    // 父任务上下文未被修改
    echo "   父任务 - parent_key 仍为: " . Context::get('parent_key') . "\n";
    echo "   父任务 - shared_key 仍为: " . Context::get('shared_key') . "\n";
    echo "   父任务 - child_key 不存在: " . (Context::has('child_key') ? '是' : '否') . "\n";
});

// 示例 3: 删除和清除上下文
echo "\n3. 删除和清除上下文：\n";
run(function() {
    // 设置多个上下文
    Context::set('key1', 'value1');
    Context::set('key2', 'value2');
    Context::set('key3', 'value3');
    
    echo "   初始上下文: " . json_encode(Context::getAll()) . "\n";
    
    // 删除单个键
    Context::delete('key2');
    echo "   删除 key2 后: " . json_encode(Context::getAll()) . "\n";
    
    // 清除所有上下文
    Context::clear();
    echo "   清除所有上下文后: " . json_encode(Context::getAll()) . "\n";
});

// 示例 4: 实际应用场景
echo "\n4. 实际应用场景：HTTP 请求上下文传递\n";
run(function() {
    // 模拟 HTTP 请求处理
    $handleHttpRequest = function($requestId, $userId, $path) {
        // 设置请求上下文
        Context::set('request_id', $requestId);
        Context::set('user_id', $userId);
        Context::set('request_path', $path);
        Context::set('timestamp', time());
        
        echo "   请求 {$requestId} 开始处理: {$path}\n";
        
        // 模拟多个异步操作
        $dbTask = create_task(function() {
            $requestId = Context::get('request_id');
            $userId = Context::get('user_id');
            echo "   数据库查询 - 请求: {$requestId}, 用户: {$userId}\n";
            sleep(0.1);
            return [
                'user_name' => '张三',
                'email' => 'zhangsan@example.com'
            ];
        });
        
        $cacheTask = create_task(function() {
            $requestId = Context::get('request_id');
            $path = Context::get('request_path');
            echo "   缓存检查 - 请求: {$requestId}, 路径: {$path}\n";
            sleep(0.05);
            return [
                'cached' => false,
                'ttl' => 3600
            ];
        });
        
        $logTask = create_task(function() {
            $requestId = Context::get('request_id');
            $timestamp = Context::get('timestamp');
            echo "   日志记录 - 请求: {$requestId}, 时间: " . date('Y-m-d H:i:s', $timestamp) . "\n";
            sleep(0.02);
            return true;
        });
        
        // 等待所有操作完成
        $dbResult = await($dbTask);
        $cacheResult = await($cacheTask);
        $logResult = await($logTask);
        
        echo "   请求 {$requestId} 处理完成\n";
        return [
            'request_id' => $requestId,
            'user_id' => $userId,
            'path' => $path,
            'db_result' => $dbResult,
            'cache_result' => $cacheResult,
            'log_result' => $logResult
        ];
    };
    
    // 处理两个并发请求
    $request1 = create_task(function() use ($handleHttpRequest) {
        return $handleHttpRequest('req-001', 1001, '/api/users/1001');
    });
    
    $request2 = create_task(function() use ($handleHttpRequest) {
        return $handleHttpRequest('req-002', 1002, '/api/users/1002/profile');
    });
    
    // 等待两个请求处理完成
    $result1 = await($request1);
    $result2 = await($request2);
    
    echo "\n   请求 1 结果: 请求ID={$result1['request_id']}, 用户ID={$result1['user_id']}\n";
    echo "   请求 2 结果: 请求ID={$result2['request_id']}, 用户ID={$result2['user_id']}\n";
});

echo "\n=== 示例结束 ===\n";
