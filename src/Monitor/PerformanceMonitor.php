<?php

namespace PfinalClub\Asyncio\Monitor;

use Workerman\Timer;

/**
 * 性能监控器
 * 追踪任务执行时间、内存使用和慢任务
 */
class PerformanceMonitor
{
    private static ?PerformanceMonitor $instance = null;
    private array $metrics = [];
    private array $slowTasks = [];
    private float $slowTaskThreshold = 1.0; // 秒
    private int $maxSlowTasks = 100;
    private array $taskTimings = [];
    
    private function __construct() {}
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 开始追踪任务
     */
    public function startTask(int $taskId, string $taskName): void
    {
        $this->taskTimings[$taskId] = [
            'name' => $taskName,
            'start_time' => microtime(true),
            'memory_start' => memory_get_usage(),
        ];
    }
    
    /**
     * 结束任务追踪
     */
    public function endTask(int $taskId): void
    {
        if (!isset($this->taskTimings[$taskId])) {
            return;
        }
        
        $timing = $this->taskTimings[$taskId];
        $duration = microtime(true) - $timing['start_time'];
        $memoryUsed = memory_get_usage() - $timing['memory_start'];
        
        // 记录慢任务
        if ($duration > $this->slowTaskThreshold) {
            $this->slowTasks[] = [
                'task_id' => $taskId,
                'name' => $timing['name'],
                'duration' => $duration,
                'memory_used' => $memoryUsed,
                'timestamp' => time(),
            ];
            
            // 限制慢任务记录数量
            if (count($this->slowTasks) > $this->maxSlowTasks) {
                array_shift($this->slowTasks);
            }
        }
        
        // 更新统计
        $this->updateMetrics($timing['name'], $duration, $memoryUsed);
        
        unset($this->taskTimings[$taskId]);
    }
    
    /**
     * 更新指标统计
     */
    private function updateMetrics(string $taskName, float $duration, int $memoryUsed): void
    {
        if (!isset($this->metrics[$taskName])) {
            $this->metrics[$taskName] = [
                'count' => 0,
                'total_duration' => 0,
                'min_duration' => PHP_FLOAT_MAX,
                'max_duration' => 0,
                'total_memory' => 0,
            ];
        }
        
        $this->metrics[$taskName]['count']++;
        $this->metrics[$taskName]['total_duration'] += $duration;
        $this->metrics[$taskName]['min_duration'] = min($this->metrics[$taskName]['min_duration'], $duration);
        $this->metrics[$taskName]['max_duration'] = max($this->metrics[$taskName]['max_duration'], $duration);
        $this->metrics[$taskName]['total_memory'] += $memoryUsed;
    }
    
    /**
     * 获取性能指标
     */
    public function getMetrics(): array
    {
        $result = [];
        
        foreach ($this->metrics as $name => $data) {
            $result[$name] = [
                'count' => $data['count'],
                'avg_duration' => $data['total_duration'] / $data['count'],
                'min_duration' => $data['min_duration'],
                'max_duration' => $data['max_duration'],
                'avg_memory' => $data['total_memory'] / $data['count'],
            ];
        }
        
        return $result;
    }
    
    /**
     * 获取慢任务列表
     */
    public function getSlowTasks(): array
    {
        return $this->slowTasks;
    }
    
    /**
     * 导出 Prometheus 格式的指标
     */
    public function exportPrometheus(): string
    {
        $output = [];
        
        // 任务计数和执行时间
        foreach ($this->metrics as $name => $data) {
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
            
            // 任务计数
            $output[] = "# HELP asyncio_task_count_{$safeName} Total task count";
            $output[] = "# TYPE asyncio_task_count_{$safeName} counter";
            $output[] = "asyncio_task_count_{$safeName} {$data['count']}";
            
            // 平均执行时间
            $output[] = "# HELP asyncio_task_duration_{$safeName} Task duration in seconds";
            $output[] = "# TYPE asyncio_task_duration_{$safeName} gauge";
            $output[] = "asyncio_task_duration_{$safeName} " . ($data['total_duration'] / $data['count']);
            
            // 最大执行时间
            $output[] = "# HELP asyncio_task_duration_max_{$safeName} Max task duration in seconds";
            $output[] = "# TYPE asyncio_task_duration_max_{$safeName} gauge";
            $output[] = "asyncio_task_duration_max_{$safeName} {$data['max_duration']}";
        }
        
        // 慢任务计数
        $output[] = "# HELP asyncio_slow_tasks Total slow tasks";
        $output[] = "# TYPE asyncio_slow_tasks gauge";
        $output[] = "asyncio_slow_tasks " . count($this->slowTasks);
        
        // 内存使用
        $output[] = "# HELP asyncio_memory_usage Current memory usage in bytes";
        $output[] = "# TYPE asyncio_memory_usage gauge";
        $output[] = "asyncio_memory_usage " . memory_get_usage();
        
        return implode("\n", $output) . "\n";
    }
    
    /**
     * 设置慢任务阈值
     */
    public function setSlowTaskThreshold(float $seconds): void
    {
        $this->slowTaskThreshold = $seconds;
    }
    
    /**
     * 重置所有统计数据
     */
    public function reset(): void
    {
        $this->metrics = [];
        $this->slowTasks = [];
        $this->taskTimings = [];
    }
}

