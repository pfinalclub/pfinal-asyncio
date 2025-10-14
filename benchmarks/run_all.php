<?php
/**
 * 运行所有基准测试
 */

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║     PHP AsyncIO - 完整性能基准测试套件                      ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "PHP 版本: " . PHP_VERSION . "\n";
echo "开始时间: " . date('Y-m-d H:i:s') . "\n";
echo "\n";

// 创建报告目录
$reportsDir = __DIR__ . '/reports';
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
}

$benchmarks = [
    '01_task_creation.php'   => '任务创建开销',
    '02_concurrent_tasks.php' => '并发任务性能',
    '03_context_switch.php'   => '上下文切换延迟',
    '04_memory_usage.php'     => '内存使用分析',
    '05_real_world.php'       => '真实场景模拟',
];

$startTime = microtime(true);

foreach ($benchmarks as $file => $name) {
    echo str_repeat("=", 80) . "\n";
    echo "运行: {$name}\n";
    echo str_repeat("=", 80) . "\n\n";
    
    $output = [];
    $returnCode = 0;
    
    exec("php " . __DIR__ . "/{$file} 2>&1", $output, $returnCode);
    
    if ($returnCode === 0) {
        echo implode("\n", $output) . "\n\n";
    } else {
        echo "❌ 测试失败: {$file}\n";
        echo implode("\n", $output) . "\n\n";
    }
}

$endTime = microtime(true);
$totalTime = $endTime - $startTime;

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "✅ 所有基准测试完成！\n";
echo str_repeat("=", 80) . "\n";
echo sprintf("总耗时: %.2f 秒\n", $totalTime);
echo "报告目录: {$reportsDir}\n";
echo "\n";

// 生成汇总报告
$summaryFile = $reportsDir . '/summary.txt';
$summary = "性能基准测试汇总\n";
$summary .= str_repeat("=", 80) . "\n\n";
$summary .= "PHP 版本: " . PHP_VERSION . "\n";
$summary .= "测试时间: " . date('Y-m-d H:i:s') . "\n";
$summary .= "总耗时: " . sprintf("%.2f 秒", $totalTime) . "\n\n";
$summary .= "测试文件:\n";

foreach ($benchmarks as $file => $name) {
    $reportFile = $reportsDir . '/' . str_replace('.php', '.txt', $file);
    if (file_exists($reportFile)) {
        $summary .= "  ✓ {$name}: {$reportFile}\n";
    } else {
        $summary .= "  ✗ {$name}: 报告未生成\n";
    }
}

$summary .= "\n详细报告请查看各个测试文件的输出。\n";

file_put_contents($summaryFile, $summary);
echo "汇总报告: {$summaryFile}\n\n";

