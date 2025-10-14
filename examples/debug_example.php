<?php
/**
 * AsyncIO 调试器示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, sleep};
use PfinalClub\Asyncio\Debug\AsyncioDebugger;

// 获取调试器实例并启用
$debugger = AsyncioDebugger::getInstance();
$debugger->enable();

// 嵌套协程示例
function fetchData(int $id): \Generator {
    global $debugger;
    $taskId = "fetch-{$id}";
    
    $debugger->traceCoroutineCall($taskId, "fetchData({$id})");
    
    echo "开始获取数据 {$id}...\n";
    yield sleep(1);
    
    $result = ['id' => $id, 'data' => "Data for {$id}"];
    
    $debugger->traceCoroutineReturn($taskId, $result);
    return $result;
}

function processData(array $data): \Generator {
    global $debugger;
    $taskId = "process-{$data['id']}";
    
    $debugger->traceCoroutineCall($taskId, "processData({$data['id']})");
    
    echo "处理数据 {$data['id']}...\n";
    yield sleep(0.5);
    
    $result = array_merge($data, ['processed' => true]);
    
    $debugger->traceCoroutineReturn($taskId, $result);
    return $result;
}

function main(): \Generator {
    global $debugger;
    
    $debugger->traceCoroutineCall('main', 'main()');
    
    echo "=== 开始调试示例 ===\n\n";
    
    // 创建多个任务
    $tasks = [];
    for ($i = 1; $i <= 3; $i++) {
        $tasks[] = create_task((function() use ($i) {
            global $debugger;
            
            // 获取数据
            $data = yield fetchData($i);
            
            // 处理数据
            $processed = yield processData($data);
            
            return $processed;
        })());
    }
    
    // 等待所有任务完成
    $results = yield $tasks;
    
    echo "\n所有任务完成！\n\n";
    
    // 显示调试报告
    echo $debugger->report();
    
    // 显示调用链可视化
    echo $debugger->visualizeCallChain();
    
    // 导出追踪数据
    file_put_contents(__DIR__ . '/debug_traces.json', $debugger->toJson());
    echo "\n追踪数据已导出到: debug_traces.json\n";
    
    $debugger->traceCoroutineReturn('main', $results);
    
    return $results;
}

// 运行（使用 Workerman 模式）
run(main(), useWorkerman: true);

