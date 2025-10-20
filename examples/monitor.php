<?php
/**
 * 监控示例 - 优化版
 * 
 * 展示如何使用 AsyncioMonitor 监控异步任务执行情况
 * 
 * 优化内容：
 * - 完整展示 v2.0.2 新特性
 * - 添加实时监控仪表板
 * - 增加性能趋势分析
 * - 集成错误追踪和告警
 * - 展示自定义监控指标
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, sleep, gather};
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

echo "=== AsyncioMonitor 监控示例 - v2.0.2 新特性展示 ===\n\n";

run(function() {
    $monitor = AsyncioMonitor::getInstance();
    
    // 启用高级监控功能
    $monitor->enableAdvancedMetrics(true);
    $monitor->setSlowTaskThreshold(0.5); // 设置慢任务阈值为 0.5 秒
    
    echo "🔧 监控配置：高级指标启用，慢任务阈值 0.5 秒\n\n";
    
    // 示例 1: 实时监控仪表板
    echo "【示例 1】实时监控仪表板\n";
    
    function display_dashboard(AsyncioMonitor $monitor, string $title = "当前状态") {
        $snapshot = $monitor->snapshot();
        $memory = $monitor->getMemoryInfo();
        $performance = $monitor->getPerformanceMetrics();
        
        echo "📊 {$title}\n";
        echo str_repeat("-", 50) . "\n";
        echo "  🧵 Fiber 状态:\n";
        echo "    活跃: {$snapshot['active_fibers']} | 等待: {$snapshot['waiting_fibers']} | 完成: {$snapshot['completed_tasks']}\n";
        
        echo "  💾 内存使用:\n";
        echo "    当前: " . round($memory['current'] / 1024 / 1024, 2) . " MB | ";
        echo "峰值: " . round($memory['peak'] / 1024 / 1024, 2) . " MB\n";
        
        echo "  ⚡ 性能指标:\n";
        echo "    任务总数: {$performance['tasks_executed']} | ";
        echo "平均耗时: " . round($performance['avg_execution_time'] * 1000, 2) . " ms\n";
        
        // v2.0.2 新增指标
        if (isset($performance['throughput'])) {
            echo "    吞吐量: " . round($performance['throughput'], 2) . " 任务/秒\n";
        }
        if (isset($performance['error_rate'])) {
            echo "    错误率: " . round($performance['error_rate'] * 100, 2) . "%\n";
        }
        
        echo str_repeat("-", 50) . "\n\n";
    }
    
    display_dashboard($monitor, "初始状态");
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // 示例 2: 创建复杂任务场景
    echo "【示例 2】复杂任务场景监控\n";
    
    $tasks = [];
    
    // 创建不同类型的任务
    for ($i = 0; $i < 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            $start = microtime(true);
            
            // 模拟不同类型的任务
            switch ($i % 3) {
                case 0: // 快速任务
                    sleep(0.1);
                    break;
                case 1: // 中等任务
                    sleep(0.5);
                    break;
                case 2: // 慢任务
                    sleep(1.2);
                    break;
            }
            
            // 10% 概率模拟错误
            if (mt_rand(1, 10) === 1) {
                throw new \RuntimeException("任务 {$i} 执行失败");
            }
            
            return [
                'task_id' => $i,
                'duration' => microtime(true) - $start,
                'type' => ['快速', '中等', '慢速'][$i % 3]
            ];
        }, "task-{$i}-" . ['快速', '中等', '慢速'][$i % 3]);
    }
    
    // 实时监控任务执行过程
    echo "🔄 任务执行中...\n";
    for ($i = 0; $i < 3; $i++) {
        sleep(0.3);
        display_dashboard($monitor, "执行进度 " . ($i + 1) . "/3");
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // 示例 3: v2.0.2 新增功能 - 性能趋势分析
    echo "【示例 3】性能趋势分析 (v2.0.2 新特性)\n";
    
    $trends = $monitor->getPerformanceTrends(10); // 获取最近10个时间点的趋势
    
    if (!empty($trends)) {
        echo "📈 性能趋势数据:\n";
        foreach ($trends as $metric => $data) {
            echo "  {$metric}: ";
            $last5 = array_slice($data, -5); // 显示最近5个数据点
            foreach ($last5 as $value) {
                echo round($value, 2) . " ";
            }
            echo "\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // 示例 4: 错误追踪和告警系统
    echo "【示例 4】错误追踪和告警系统 (v2.0.2 新特性)\n";
    
    $errors = $monitor->getRecentErrors(10);
    
    if (!empty($errors)) {
        echo "⚠️  最近错误记录:\n";
        foreach ($errors as $error) {
            echo "  [{$error['timestamp']}] {$error['task_name']}: {$error['message']}\n";
            echo "    类型: {$error['type']} | 堆栈: " . substr($error['trace'], 0, 50) . "...\n";
        }
    } else {
        echo "✅ 暂无错误记录\n";
    }
    
    // 检查告警状态
    $alerts = $monitor->getActiveAlerts();
    if (!empty($alerts)) {
        echo "🚨 活跃告警:\n";
        foreach ($alerts as $alert) {
            echo "  [{$alert['level']}] {$alert['message']} (触发于: {$alert['triggered_at']})\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // 示例 5: 自定义监控指标
    echo "【示例 5】自定义监控指标 (v2.0.2 新特性)\n";
    
    // 注册自定义指标
    $monitor->registerCustomMetric('business_throughput', '业务吞吐量', 'requests/second');
    $monitor->registerCustomMetric('cache_hit_rate', '缓存命中率', 'percentage');
    
    // 更新自定义指标
    for ($i = 0; $i < 5; $i++) {
        $monitor->updateCustomMetric('business_throughput', mt_rand(100, 500));
        $monitor->updateCustomMetric('cache_hit_rate', mt_rand(80, 95) / 100);
        sleep(0.2);
    }
    
    $customMetrics = $monitor->getCustomMetrics();
    echo "📊 自定义指标:\n";
    foreach ($customMetrics as $name => $metric) {
        echo "  {$metric['description']}: {$metric['value']} {$metric['unit']}\n";
        echo "    历史趋势: " . implode(" ", array_slice($metric['history'] ?? [], -3)) . "\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // 示例 6: 连接池和资源监控
    echo "【示例 6】连接池和资源监控\n";
    
    $resources = $monitor->getResourceUsage();
    
    echo "🔗 连接池状态:\n";
    if (isset($resources['connection_pools'])) {
        foreach ($resources['connection_pools'] as $pool => $stats) {
            echo "  {$pool}: 使用中 {$stats['active']}/{$stats['total']} | ";
            echo "空闲 {$stats['idle']} | 等待 {$stats['waiting']}\n";
        }
    }
    
    echo "\n📊 系统资源:\n";
    if (isset($resources['system'])) {
        echo "  CPU 使用率: " . round($resources['system']['cpu_usage'] * 100, 2) . "%\n";
        echo "  内存使用率: " . round($resources['system']['memory_usage'] * 100, 2) . "%\n";
        echo "  磁盘 I/O: 读 " . round($resources['system']['disk_read'] / 1024, 2) . " KB/s | ";
        echo "写 " . round($resources['system']['disk_write'] / 1024, 2) . " KB/s\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // 示例 7: 慢任务分析和优化建议
    echo "【示例 7】慢任务分析和优化建议\n";
    
    $slowTasks = $monitor->getSlowTasks(0.3); // 获取执行超过 0.3 秒的慢任务
    
    if (!empty($slowTasks)) {
        echo "🐌 慢任务分析:\n";
        foreach ($slowTasks as $task) {
            echo "  📝 任务: {$task['name']}\n";
            echo "    执行时间: " . round($task['duration'], 3) . " 秒\n";
            echo "    开始时间: {$task['started_at']}\n";
            
            // 提供优化建议
            if ($task['duration'] > 1.0) {
                echo "    💡 建议: 考虑任务拆分或异步优化\n";
            } elseif ($task['duration'] > 0.5) {
                echo "    💡 建议: 检查 I/O 操作或网络延迟\n";
            }
            echo "\n";
        }
    } else {
        echo "✅ 无慢任务检测到\n";
    }
    
    echo "\n" . str_repeat("=", 60) . "\n\n";
    
    // 等待所有任务完成并收集结果
    echo "【最终结果】任务执行汇总\n";
    
    $results = [];
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($tasks as $task) {
        try {
            $result = $task->getResult();
            $results[] = $result;
            $successCount++;
        } catch (\Throwable $e) {
            $errorCount++;
            echo "❌ 任务失败: {$e->getMessage()}\n";
        }
    }
    
    echo "✅ 成功任务: {$successCount} | ❌ 失败任务: {$errorCount}\n";
    
    // 显示最终统计
    display_dashboard($monitor, "最终状态");
    
    // v2.0.2 新增：生成监控报告
    $report = $monitor->generateReport();
    echo "📋 监控报告摘要:\n";
    echo "  运行时长: " . round($report['duration'], 2) . " 秒\n";
    echo "  总任务数: {$report['total_tasks']}\n";
    echo "  成功率: " . round($report['success_rate'] * 100, 2) . "%\n";
    echo "  平均吞吐量: " . round($report['avg_throughput'], 2) . " 任务/秒\n";
});

echo "\n✅ 监控示例优化完成\n";
echo "💡 v2.0.2 新特性展示：\n";
echo "  - 实时监控仪表板\n";
echo "  - 性能趋势分析\n";
echo "  - 错误追踪和告警系统\n";
echo "  - 自定义监控指标\n";
echo "  - 系统资源监控\n";
echo "  - 慢任务分析和优化建议\n";
echo "  - 自动生成监控报告\n";

