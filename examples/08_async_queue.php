<?php
/**
 * 示例 8: 异步队列
 * 
 * 使用 Future 实现生产者-消费者模式
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, create_future, sleep};

echo "=== 异步队列示例 ===\n\n";

run(function() {
    $queue = [];
    $maxSize = 5;
    
    // 生产者
    $producer = create_task(function() use (&$queue, $maxSize) {
        for ($i = 1; $i <= 10; $i++) {
            // 等待队列有空间
            while (count($queue) >= $maxSize) {
                sleep(0.1);
            }
            
            $future = create_future();
            $queue[] = ['id' => $i, 'future' => $future];
            echo "  生产者: 添加任务 #{$i} (队列: " . count($queue) . ")\n";
            
            sleep(0.2);  // 模拟生产速度
        }
        echo "  生产者: 完成\n";
    });
    
    // 消费者
    $consumer = create_task(function() use (&$queue) {
        $processed = 0;
        
        while ($processed < 10) {
            if (empty($queue)) {
                sleep(0.1);
                continue;
            }
            
            $item = array_shift($queue);
            echo "  消费者: 处理任务 #{$item['id']} (队列: " . count($queue) . ")\n";
            
            // 模拟处理
            sleep(0.3);
            
            // 完成任务
            $item['future']->setResult("完成#{$item['id']}");
            $processed++;
        }
        echo "  消费者: 完成\n";
    });
    
    // 等待完成
    \PfinalClub\Asyncio\await($producer);
    \PfinalClub\Asyncio\await($consumer);
    
    echo "\n所有任务处理完毕！\n";
});


