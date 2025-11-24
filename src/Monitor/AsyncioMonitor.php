<?php

namespace PfinalClub\Asyncio\Monitor;

use PfinalClub\Asyncio\EventLoop;

/**
 * Asyncio 监控器
 * 监控当前任务、Future、Timer 数量和状态
 */
class AsyncioMonitor
{
    private static ?AsyncioMonitor $instance = null;
    private array $metrics = [];
    private int $startTime;
    private array $taskHistory = [];
    private int $maxHistorySize = 1000;
    
    private function __construct()
    {
        $this->startTime = time();
        $this->resetMetrics();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 重置指标
     */
    private function resetMetrics(): void
    {
        $this->metrics = [
            'tasks' => [
                'total' => 0,
                'pending' => 0,
                'running' => 0,
                'completed' => 0,
                'failed' => 0,
                'cancelled' => 0,
            ],
            'futures' => [
                'total' => 0,
                'pending' => 0,
                'resolved' => 0,
                'rejected' => 0,
            ],
            'timers' => [
                'active' => 0,
                'total' => 0,
            ],
            'performance' => [
                'event_loop_iterations' => 0,
                'avg_task_duration_ms' => 0,
                'peak_memory_mb' => 0,
            ],
        ];
    }
    
    /**
     * 获取当前快照
     */
    public function snapshot(): array
    {
        $eventLoop = EventLoop::getInstance();
        
        // 获取任务统计
        $tasks = $this->getTaskStats($eventLoop);
        
        // 获取内存使用
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $peakMemory = memory_get_peak_usage(true) / 1024 / 1024;
        
        return [
            'timestamp' => time(),
            'uptime_seconds' => time() - $this->startTime,
            'tasks' => $tasks,
            'memory' => [
                'current_mb' => round($memoryUsage, 2),
                'peak_mb' => round($peakMemory, 2),
            ],
            'event_loop' => [
                'mode' => 'fiber-event-driven',
                'active_fibers' => count($eventLoop->getActiveFibers()),
            ],
            'performance' => PerformanceMonitor::getInstance()->getMetrics(),
            'slow_tasks' => PerformanceMonitor::getInstance()->getSlowTasks(),
            'connection_manager' => $this->getConnectionManagerStats(),
        ];
    }
    
    /**
     * 获取连接管理器统计信息
     * 
     * @deprecated HTTP 模块已移除，请使用 pfinal/asyncio-http-core 包
     */
    private function getConnectionManagerStats(): array
    {
        // HTTP 模块已移到独立包
        return [];
    }
    
    /**
     * 获取 Fiber 统计
     */
    private function getTaskStats(EventLoop $eventLoop): array
    {
        // 使用反射获取私有属性（仅用于监控）
        $reflection = new \ReflectionClass($eventLoop);
        
        $fibersProperty = $reflection->getProperty('fibers');
        $fibersProperty->setAccessible(true);
        $fibers = $fibersProperty->getValue($eventLoop);
        
        $stats = [
            'total' => count($fibers),
            'pending' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
        
        foreach ($fibers as $info) {
            $task = $info['task'];
            if ($task->isDone()) {
                if ($task->hasException()) {
                    $stats['failed']++;
                } else {
                    $stats['completed']++;
                }
            } else {
                $stats['pending']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * 记录任务创建
     */
    public function recordTaskCreated(string $taskId, string $name): void
    {
        $this->taskHistory[] = [
            'event' => 'created',
            'task_id' => $taskId,
            'name' => $name,
            'timestamp' => microtime(true),
        ];
        
        $this->trimHistory();
    }
    
    /**
     * 记录任务完成
     */
    public function recordTaskCompleted(string $taskId, float $duration): void
    {
        $this->taskHistory[] = [
            'event' => 'completed',
            'task_id' => $taskId,
            'duration_ms' => round($duration * 1000, 2),
            'timestamp' => microtime(true),
        ];
        
        $this->trimHistory();
    }
    
    /**
     * 记录任务失败
     */
    public function recordTaskFailed(string $taskId, \Throwable $error): void
    {
        $this->taskHistory[] = [
            'event' => 'failed',
            'task_id' => $taskId,
            'error' => get_class($error),
            'message' => $error->getMessage(),
            'timestamp' => microtime(true),
        ];
        
        $this->trimHistory();
    }
    
    /**
     * 修剪历史记录
     */
    private function trimHistory(): void
    {
        if (count($this->taskHistory) > $this->maxHistorySize) {
            $this->taskHistory = array_slice($this->taskHistory, -$this->maxHistorySize);
        }
    }
    
    /**
     * 获取任务历史
     */
    public function getTaskHistory(int $limit = 100): array
    {
        return array_slice($this->taskHistory, -$limit);
    }
    
    /**
     * 生成监控报告
     */
    public function report(): string
    {
        $snapshot = $this->snapshot();
        
        $report = "\n";
        $report .= "╔════════════════════════════════════════════════════════════╗\n";
        $report .= "║          PfinalClub AsyncIO - 实时监控报告                 ║\n";
        $report .= "╚════════════════════════════════════════════════════════════╝\n";
        $report .= "\n";
        
        $report .= "⏱️  运行时间: " . $this->formatUptime($snapshot['uptime_seconds']) . "\n";
        $report .= "📅 时间戳: " . date('Y-m-d H:i:s', $snapshot['timestamp']) . "\n";
        $report .= "\n";
        
        $report .= "📊 任务统计:\n";
        $report .= "  ├─ 总计: {$snapshot['tasks']['total']}\n";
        $report .= "  ├─ 待处理: {$snapshot['tasks']['pending']}\n";
        $report .= "  ├─ 已完成: {$snapshot['tasks']['completed']}\n";
        $report .= "  └─ 失败: {$snapshot['tasks']['failed']}\n";
        $report .= "\n";
        
        $report .= "💾 内存使用:\n";
        $report .= "  ├─ 当前: {$snapshot['memory']['current_mb']} MB\n";
        $report .= "  └─ 峰值: {$snapshot['memory']['peak_mb']} MB\n";
        $report .= "\n";
        
        $report .= "⚙️  事件循环模式: {$snapshot['event_loop']['mode']}\n";
        $report .= "\n";
        
        return $report;
    }
    
    /**
     * 格式化运行时间
     */
    private function formatUptime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $parts = [];
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        $parts[] = "{$secs}s";
        
        return implode(' ', $parts);
    }
    
    /**
     * 导出为 JSON
     */
    public function toJson(): string
    {
        return json_encode($this->snapshot(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 启用实时监控（定期输出）
     */
    public function startRealTimeMonitor(int $intervalSeconds = 5): void
    {
        echo "🔍 启动 AsyncIO 实时监控（每 {$intervalSeconds} 秒刷新）\n";
        echo "按 Ctrl+C 停止监控\n\n";
        
        while (true) {
            // 清屏（可选）
            // echo "\033[2J\033[;H";
            
            echo $this->report();
            echo str_repeat("─", 60) . "\n";
            
            sleep($intervalSeconds);
        }
    }
}

