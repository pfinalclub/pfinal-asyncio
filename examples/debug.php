<?php
/**
 * 调试示例
 * 
 * 展示如何使用 AsyncioDebugger 追踪 Fiber 调用链
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep, create_task, gather};
use PfinalClub\Asyncio\Debug\AsyncioDebugger;

echo "=== AsyncIO 调试示例 ===\n\n";

run(function() {
    // 启用调试器
    $debugger = AsyncioDebugger::getInstance();
    $debugger->enable();
    
    echo "【示例 1】追踪任务执行\n\n";
    
    // 创建一些任务
    $task1 = create_task(function() {
        echo "[Task1] 开始\n";
        sleep(0.2);
        echo "[Task1] 完成\n";
        return "Result1";
    }, 'task-1');
    
    $task2 = create_task(function() {
        echo "[Task2] 开始\n";
        sleep(0.15);
        echo "[Task2] 完成\n";
        return "Result2";
    }, 'task-2');
    
    // 等待任务完成
    $results = gather($task1, $task2);
    
    echo "\n所有任务完成\n\n";
    
    // 查看调试追踪
    echo "【示例 2】调试追踪信息\n";
    $traces = $debugger->getTraces();
    echo "记录了 " . count($traces) . " 条调试追踪\n\n";
    
    // 显示部分追踪
    echo "最近的追踪（前 5 条）:\n";
    foreach (array_slice($traces, -5) as $trace) {
        echo "  [{$trace['timestamp']}] {$trace['type']}: {$trace['task_name']}\n";
    }
    echo "\n";
    
    // 示例 3: 嵌套任务调试
    echo "【示例 3】嵌套任务调试\n\n";
    
    create_task(function() {
        echo "[Parent] 开始\n";
        
        $child = create_task(function() {
            echo "[Child] 开始\n";
            sleep(0.1);
            echo "[Child] 完成\n";
            return "Child Result";
        }, 'child-task');
        
        $result = \PfinalClub\Asyncio\await($child);
        echo "[Parent] 收到子任务结果: {$result}\n";
    }, 'parent-task');
    
    sleep(0.3);
    
    echo "\n";
    
    // 清理和关闭调试器
    $debugger->disable();
});

echo "\n✅ 调试示例完成\n";
echo "💡 提示: 调试器会追踪任务创建、开始、完成、暂停和恢复\n";

