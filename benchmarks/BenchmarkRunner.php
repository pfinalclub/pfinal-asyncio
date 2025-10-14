<?php

namespace PfinalClub\Asyncio\Benchmarks;

/**
 * 性能基准测试运行器
 */
class BenchmarkRunner
{
    private array $results = [];
    private int $warmupRounds = 3;
    private int $testRounds = 10;
    
    public function __construct(int $warmupRounds = 3, int $testRounds = 10)
    {
        $this->warmupRounds = $warmupRounds;
        $this->testRounds = $testRounds;
    }
    
    /**
     * 运行基准测试
     */
    public function run(string $name, callable $test): BenchmarkResult
    {
        echo "运行基准测试: {$name}\n";
        
        // 预热
        echo "  预热 ({$this->warmupRounds} 轮)...\n";
        for ($i = 0; $i < $this->warmupRounds; $i++) {
            $test();
            $this->clearMemory();
        }
        
        // 正式测试
        echo "  测试 ({$this->testRounds} 轮)...\n";
        $times = [];
        $memoryUsages = [];
        
        for ($i = 0; $i < $this->testRounds; $i++) {
            $memBefore = memory_get_usage(true);
            $start = microtime(true);
            
            $test();
            
            $end = microtime(true);
            $memAfter = memory_get_usage(true);
            
            $times[] = $end - $start;
            $memoryUsages[] = $memAfter - $memBefore;
            
            $this->clearMemory();
        }
        
        $result = new BenchmarkResult($name, $times, $memoryUsages);
        $this->results[$name] = $result;
        
        echo "  完成: " . $result->getSummary() . "\n\n";
        
        return $result;
    }
    
    /**
     * 清理内存
     */
    private function clearMemory(): void
    {
        gc_collect_cycles();
        usleep(10000); // 10ms
    }
    
    /**
     * 获取所有结果
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * 生成报告
     */
    public function generateReport(): string
    {
        $report = "\n";
        $report .= str_repeat("=", 80) . "\n";
        $report .= "性能基准测试报告\n";
        $report .= str_repeat("=", 80) . "\n\n";
        
        $report .= "PHP 版本: " . PHP_VERSION . "\n";
        $report .= "测试时间: " . date('Y-m-d H:i:s') . "\n";
        $report .= "测试轮数: {$this->testRounds} (预热: {$this->warmupRounds})\n\n";
        
        $report .= str_repeat("-", 80) . "\n";
        $report .= sprintf("%-40s %12s %12s %12s\n", "测试项目", "平均耗时", "内存使用", "吞吐量");
        $report .= str_repeat("-", 80) . "\n";
        
        foreach ($this->results as $result) {
            $report .= sprintf(
                "%-40s %10.4fms %10.2fKB %8.0f ops/s\n",
                $result->getName(),
                $result->getAvgTime() * 1000,
                $result->getAvgMemory() / 1024,
                $result->getThroughput()
            );
        }
        
        $report .= str_repeat("-", 80) . "\n\n";
        
        return $report;
    }
    
    /**
     * 保存报告到文件
     */
    public function saveReport(string $filename): void
    {
        $report = $this->generateReport();
        
        // 详细报告
        $detailedReport = $report;
        $detailedReport .= "\n详细数据:\n\n";
        
        foreach ($this->results as $result) {
            $detailedReport .= $result->getDetailedReport() . "\n";
        }
        
        file_put_contents($filename, $detailedReport);
        echo "报告已保存到: {$filename}\n";
    }
}

/**
 * 基准测试结果
 */
class BenchmarkResult
{
    private string $name;
    private array $times;
    private array $memoryUsages;
    
    public function __construct(string $name, array $times, array $memoryUsages)
    {
        $this->name = $name;
        $this->times = $times;
        $this->memoryUsages = $memoryUsages;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function getAvgTime(): float
    {
        return array_sum($this->times) / count($this->times);
    }
    
    public function getMinTime(): float
    {
        return min($this->times);
    }
    
    public function getMaxTime(): float
    {
        return max($this->times);
    }
    
    public function getAvgMemory(): float
    {
        return array_sum($this->memoryUsages) / count($this->memoryUsages);
    }
    
    public function getThroughput(): float
    {
        return 1.0 / $this->getAvgTime();
    }
    
    public function getSummary(): string
    {
        return sprintf(
            "平均: %.4fms, 内存: %.2fKB, 吞吐: %.0f ops/s",
            $this->getAvgTime() * 1000,
            $this->getAvgMemory() / 1024,
            $this->getThroughput()
        );
    }
    
    public function getDetailedReport(): string
    {
        $report = "【{$this->name}】\n";
        $report .= "  时间统计:\n";
        $report .= sprintf("    平均: %.4fms\n", $this->getAvgTime() * 1000);
        $report .= sprintf("    最小: %.4fms\n", $this->getMinTime() * 1000);
        $report .= sprintf("    最大: %.4fms\n", $this->getMaxTime() * 1000);
        $report .= sprintf("    标准差: %.4fms\n", $this->getStdDev($this->times) * 1000);
        $report .= "  内存统计:\n";
        $report .= sprintf("    平均: %.2fKB\n", $this->getAvgMemory() / 1024);
        $report .= sprintf("    最小: %.2fKB\n", min($this->memoryUsages) / 1024);
        $report .= sprintf("    最大: %.2fKB\n", max($this->memoryUsages) / 1024);
        $report .= "  性能:\n";
        $report .= sprintf("    吞吐量: %.0f ops/s\n", $this->getThroughput());
        
        return $report;
    }
    
    private function getStdDev(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / count($values);
        return sqrt($variance);
    }
}

