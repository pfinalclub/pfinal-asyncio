<?php
/**
 * 多进程模式示例
 * 
 * 演示如何使用多进程模式充分利用多核 CPU
 * 
 * 运行方式:
 *   php examples/11_multiprocess_mode.php start   # 启动
 *   php examples/11_multiprocess_mode.php stop    # 停止
 *   php examples/11_multiprocess_mode.php reload  # 重载
 *   php examples/11_multiprocess_mode.php status  # 状态
 */

require __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\sleep;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;
use function PfinalClub\Asyncio\Production\run_multiprocess;

echo "=== 多进程模式示例 ===\n\n";

// 定义工作任务
$workerTask = function() {
    echo "Worker 开始处理任务...\n";
    
    // 创建多个并发任务
    $tasks = [];
    for ($i = 1; $i <= 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            echo "任务 {$i} 开始\n";
            sleep(1);
            echo "任务 {$i} 完成\n";
            return "结果-{$i}";
        }, "task-{$i}");
    }
    
    // 等待所有任务完成
    $results = gather($tasks);
    
    echo "所有任务完成: " . implode(', ', $results) . "\n";
    
    return "Worker 完成";
};

// 配置选项
$config = [
    'worker_count' => 4,          // 4 个 Worker 进程
    'name' => 'AsyncIO-Demo',     // Worker 名称
    'daemon' => false,            // 非守护进程（方便调试）
    'log_file' => __DIR__ . '/../asyncio.log',
    'pid_file' => __DIR__ . '/../asyncio.pid',
];

// 启用多进程模式
run_multiprocess($workerTask, $config);

/**
 * 输出示例:
 * 
 * === AsyncIO 多进程模式 ===
 * Worker 进程数: 4 (CPU 核心数: 8)
 * Worker 名称: AsyncIO-Demo
 * 守护进程: 否
 * 日志文件: /path/to/asyncio.log
 * PID 文件: /path/to/asyncio.pid
 * =============================
 * 
 * Worker #0 启动 (PID: 12345)
 * Worker #1 启动 (PID: 12346)
 * Worker #2 启动 (PID: 12347)
 * Worker #3 启动 (PID: 12348)
 * 
 * [每个 Worker 并发处理 5 个任务...]
 * 
 * Worker #0 完成
 * Worker #1 完成
 * Worker #2 完成
 * Worker #3 完成
 */

