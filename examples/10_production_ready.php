<?php
/**
 * 示例 10: 生产环境就绪
 * 
 * 演示生产环境的完整配置：
 * - 健康检查
 * - 优雅关闭
 * - 资源限制
 * - 请求限流
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep, semaphore};
use PfinalClub\Asyncio\Production\{HealthCheck, GracefulShutdown, ResourceLimits};
use PfinalClub\Asyncio\Semaphore;

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║          生产环境就绪示例                                    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

// 1. 配置资源限制
echo "【1】配置资源限制\n";
$limits = ResourceLimits::getInstance();
$limits->setMemoryLimit(256, false);  // 256MB 内存限制（不强制）
$limits->setTaskLimit(50, false);      // 最多50个并发任务（不强制）

$config = $limits->getConfig();
echo "  内存限制: {$config['memory_limit_mb']} MB\n";
echo "  任务限制: {$config['task_limit']} 个\n\n";

// 2. 注册优雅关闭
echo "【2】注册优雅关闭处理器\n";
$shutdown = GracefulShutdown::getInstance();
$shutdown->setGracePeriod(10);  // 10秒优雅关闭时间
$shutdown->register();

// 注册关闭回调
$shutdown->onShutdown(function() {
    echo "  执行清理操作...\n";
    // 清理临时文件、关闭连接等
});

echo "  优雅关闭时间: {$shutdown->getGracePeriod()} 秒\n";
echo "  提示: 按 Ctrl+C 测试优雅关闭\n\n";

// 3. 健康检查
echo "【3】健康检查\n";
$health = HealthCheck::getInstance();

// 注册自定义检查
$health->registerCheck('custom_service', function() {
    // 模拟检查外部服务
    return [
        'status' => 'ok',
        'response_time_ms' => 45,
    ];
});

$healthStatus = $health->check(true);
echo "  总体状态: {$healthStatus['status']}\n";
echo "  PHP 版本: {$healthStatus['checks']['php_version']['version']}\n";
echo "  内存使用: {$healthStatus['checks']['memory']['usage_mb']} MB\n";
echo "  活跃任务: {$healthStatus['checks']['event_loop']['active_fibers']}\n\n";

// 4. 运行示例工作负载
echo "【4】运行工作负载（使用信号量限流）\n\n";

run(function() use ($limits, $health, $shutdown) {
    // 创建信号量限制并发
    $sem = semaphore(5);  // 最多5个并发
    
    $tasks = [];
    for ($i = 1; $i <= 20; $i++) {
        $tasks[] = create_task(function() use ($sem, $i, $limits, $shutdown) {
            // 检查是否请求关闭
            if ($shutdown->isShutdownRequested()) {
                echo "  任务 {$i}: 跳过（已请求关闭）\n";
                return;
            }
            
            // 使用信号量限流
            $sem->acquire();
            
            try {
                echo "  任务 {$i} 开始执行\n";
                
                // 模拟工作
                sleep(0.5);
                
                // 定期检查资源限制
                if ($i % 5 === 0) {
                    $check = $limits->checkAll();
                    if (!$check['memory']['ok']) {
                        echo "    警告: 内存使用过高！\n";
                    }
                }
                
                echo "  任务 {$i} 完成\n";
                return "结果-{$i}";
            } finally {
                $sem->release();
            }
        });
    }
    
    // 等待所有任务完成
    $results = gather(...$tasks);
    
    echo "\n所有任务完成！\n";
    echo "完成数量: " . count($results) . "\n\n";
    
    // 5. 最终健康检查
    echo "【5】最终健康检查\n";
    $health = HealthCheck::getInstance();
    $finalStatus = $health->check(true);
    
    echo "  状态: {$finalStatus['status']}\n";
    echo "  内存: {$finalStatus['checks']['memory']['usage_mb']} MB ";
    echo "({$finalStatus['checks']['memory']['usage_percent']}%)\n";
    echo "  活跃任务: {$finalStatus['checks']['event_loop']['active_fibers']}\n\n";
    
    // 6. 资源限制检查
    echo "【6】资源限制检查\n";
    $limits = ResourceLimits::getInstance();
    $limitStatus = $limits->checkAll();
    
    if (isset($limitStatus['memory'])) {
        echo "  内存: {$limitStatus['memory']['current_mb']} MB / ";
        echo "{$limitStatus['memory']['limit_mb']} MB ";
        echo "({$limitStatus['memory']['usage_percent']}%)\n";
    }
    
    if (isset($limitStatus['tasks'])) {
        echo "  任务: {$limitStatus['tasks']['current']} / ";
        echo "{$limitStatus['tasks']['limit']} ";
        echo "({$limitStatus['tasks']['usage_percent']}%)\n";
    }
    
    $violations = $limits->getViolations();
    echo "  违规次数: " . count($violations) . "\n";
});

echo "\n╔════════════════════════════════════════════════════════════╗\n";
echo "║  生产环境配置完成！                                          ║\n";
echo "║                                                            ║\n";
echo "║  特性:                                                      ║\n";
echo "║    ✓ 健康检查 - 监控系统状态                                ║\n";
echo "║    ✓ 优雅关闭 - 完成任务后退出                              ║\n";
echo "║    ✓ 资源限制 - 防止内存/任务过载                           ║\n";
echo "║    ✓ 请求限流 - Semaphore 控制并发                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

