<?php
/**
 * AsyncIO 监控器示例
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, sleep};
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

// 获取监控器实例
$monitor = AsyncioMonitor::getInstance();

// 主任务
function main(): \Generator {
    global $monitor;
    
    echo "启动任务...\n\n";
    
    // 创建一些异步任务
    $tasks = [];
    for ($i = 0; $i < 10; $i++) {
        $tasks[] = create_task((function() use ($i) {
            yield sleep(rand(1, 3));
            echo "任务 {$i} 完成\n";
            return "result-{$i}";
        })());
    }
    
    // 等待 2 秒后显示监控快照
    yield sleep(2);
    echo "\n" . $monitor->report();
    
    // 等待所有任务完成
    $results = yield $tasks;
    
    echo "\n所有任务完成！\n";
    echo "\n最终监控报告:\n";
    echo $monitor->report();
    
    // 导出 JSON
    file_put_contents(__DIR__ . '/monitor_snapshot.json', $monitor->toJson());
    echo "监控数据已导出到: monitor_snapshot.json\n";
}

// 运行（使用 Workerman 模式以支持真实的异步 sleep）
run(main(), useWorkerman: true);

