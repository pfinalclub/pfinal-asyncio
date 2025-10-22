<?php
/**
 * 示例 9: 信号量 - 并发限制
 * 
 * 演示如何使用 Semaphore 限制并发数量
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep, semaphore};
use PfinalClub\Asyncio\Semaphore;

echo "=== 信号量并发限制示例 ===\n\n";

run(function() {
    // 【示例 1】基础用法 - 限制并发为 3
    echo "【示例 1】限制并发数为 3\n";
    echo "创建 10 个任务，但同时只能运行 3 个\n\n";
    
    $sem = new Semaphore(3);
    $start = microtime(true);
    
    $tasks = [];
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task(function() use ($sem, $i) {
            $sem->acquire();  // 获取许可
            
            try {
                echo "  [" . date('H:i:s') . "] 任务 {$i} 开始执行 (可用: {$sem->getAvailable()}, 等待: {$sem->getWaitingCount()})\n";
                sleep(1);  // 模拟耗时操作
                echo "  [" . date('H:i:s') . "] 任务 {$i} 完成\n";
                return "结果-{$i}";
            } finally {
                $sem->release();  // 释放许可
            }
        });
    }
    
    $results = gather(...$tasks);
    $elapsed = microtime(true) - $start;
    
    echo "\n所有任务完成！\n";
    echo "总耗时: " . round($elapsed, 2) . " 秒\n";
    echo "（无限制需 1 秒，完全顺序需 10 秒，限制3并发约需 4 秒）\n\n";
    
    // 【示例 2】HTTP 请求限流
    echo "【示例 2】HTTP 请求限流 - 最多 5 个并发\n";
    
    $httpSem = semaphore(5);  // 使用辅助函数创建
    $urls = [];
    for ($i = 1; $i <= 20; $i++) {
        $urls[] = "https://httpbin.org/delay/1?id={$i}";
    }
    
    echo "准备发送 20 个 HTTP 请求，限制并发为 5\n\n";
    
    $start = microtime(true);
    $tasks = [];
    
    foreach ($urls as $i => $url) {
        $tasks[] = create_task(function() use ($httpSem, $url, $i) {
            // 使用 with() 方法自动管理许可
            return $httpSem->with(function() use ($url, $i) {
                echo "  正在请求: URL-" . ($i + 1) . "\n";
                sleep(1);  // 模拟 HTTP 请求
                return "响应-" . ($i + 1);
            });
        });
    }
    
    $responses = gather(...$tasks);
    $elapsed = microtime(true) - $start;
    
    echo "\n完成 " . count($responses) . " 个请求\n";
    echo "总耗时: " . round($elapsed, 2) . " 秒\n";
    echo "（无限制需 1 秒，限制 5 并发约需 4 秒）\n\n";
    
    // 【示例 3】统计信息
    echo "【示例 3】信号量统计\n";
    
    $sem2 = new Semaphore(10);
    $stats = $sem2->getStats();
    
    echo "  最大并发: {$stats['max']}\n";
    echo "  可用许可: {$stats['available']}\n";
    echo "  使用中: {$stats['in_use']}\n";
    echo "  等待中: {$stats['waiting']}\n\n";
    
    // 【示例 4】嵌套使用
    echo "【示例 4】多个资源限制\n";
    
    $dbSem = new Semaphore(5);   // 数据库连接限制
    $apiSem = new Semaphore(10);  // API 调用限制
    
    $tasks = [];
    for ($i = 1; $i <= 20; $i++) {
        $tasks[] = create_task(function() use ($dbSem, $apiSem, $i) {
            // 先获取 API 许可
            $apiSem->acquire();
            try {
                echo "  任务 {$i}: 调用 API\n";
                sleep(0.3);
                
                // 再获取数据库许可
                $dbSem->acquire();
                try {
                    echo "  任务 {$i}: 写入数据库\n";
                    sleep(0.2);
                    return "完成-{$i}";
                } finally {
                    $dbSem->release();
                }
            } finally {
                $apiSem->release();
            }
        });
    }
    
    $start = microtime(true);
    gather(...$tasks);
    $elapsed = microtime(true) - $start;
    
    echo "\n所有任务完成，耗时: " . round($elapsed, 2) . " 秒\n";
});

echo "\n=== 示例结束 ===\n";

