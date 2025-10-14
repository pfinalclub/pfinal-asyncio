<?php

namespace PfinalClub\Asyncio\Debug;

/**
 * Asyncio 调试器
 * 追踪 await 链路和协程调用栈
 */
class AsyncioDebugger
{
    private static ?AsyncioDebugger $instance = null;
    private bool $enabled = false;
    private array $traces = [];
    private int $maxTraces = 500;
    private array $callStack = [];
    private int $depth = 0;
    
    private function __construct()
    {
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 启用调试
     */
    public function enable(): void
    {
        $this->enabled = true;
        echo "🐛 AsyncIO 调试器已启用\n";
    }
    
    /**
     * 禁用调试
     */
    public function disable(): void
    {
        $this->enabled = false;
        echo "🔇 AsyncIO 调试器已禁用\n";
    }
    
    /**
     * 检查是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * 记录协程调用
     */
    public function traceCoroutineCall(string $taskId, string $name, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $trace = [
            'type' => 'coroutine_call',
            'task_id' => $taskId,
            'name' => $name,
            'depth' => $this->depth,
            'timestamp' => microtime(true),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'context' => $context,
            'backtrace' => $this->captureBacktrace(),
        ];
        
        $this->traces[] = $trace;
        $this->trimTraces();
        
        $this->depth++;
        $this->callStack[] = [
            'task_id' => $taskId,
            'name' => $name,
            'start_time' => microtime(true),
        ];
        
        $this->logTrace('CALL', $taskId, $name, $this->depth - 1);
    }
    
    /**
     * 记录协程返回
     */
    public function traceCoroutineReturn(string $taskId, $result = null): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->depth--;
        $call = array_pop($this->callStack);
        
        if ($call) {
            $duration = microtime(true) - $call['start_time'];
            
            $trace = [
                'type' => 'coroutine_return',
                'task_id' => $taskId,
                'name' => $call['name'],
                'depth' => $this->depth,
                'duration_ms' => round($duration * 1000, 2),
                'timestamp' => microtime(true),
                'result_type' => is_object($result) ? get_class($result) : gettype($result),
            ];
            
            $this->traces[] = $trace;
            $this->trimTraces();
            
            $this->logTrace('RETURN', $taskId, $call['name'], $this->depth, $duration);
        }
    }
    
    /**
     * 记录 yield 操作
     */
    public function traceYield(string $taskId, $yieldedValue): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $valueType = is_object($yieldedValue) ? get_class($yieldedValue) : gettype($yieldedValue);
        
        $trace = [
            'type' => 'yield',
            'task_id' => $taskId,
            'yielded_type' => $valueType,
            'depth' => $this->depth,
            'timestamp' => microtime(true),
        ];
        
        $this->traces[] = $trace;
        $this->trimTraces();
        
        $this->logTrace('YIELD', $taskId, $valueType, $this->depth);
    }
    
    /**
     * 记录异常
     */
    public function traceException(string $taskId, \Throwable $exception): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $trace = [
            'type' => 'exception',
            'task_id' => $taskId,
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'depth' => $this->depth,
            'timestamp' => microtime(true),
            'stack_trace' => $exception->getTraceAsString(),
        ];
        
        $this->traces[] = $trace;
        $this->trimTraces();
        
        $this->logTrace('EXCEPTION', $taskId, get_class($exception), $this->depth, null, $exception->getMessage());
    }
    
    /**
     * 捕获回溯
     */
    private function captureBacktrace(int $limit = 5): array
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 3);
        // 移除调试器自身的调用
        return array_slice($backtrace, 3);
    }
    
    /**
     * 修剪追踪记录
     */
    private function trimTraces(): void
    {
        if (count($this->traces) > $this->maxTraces) {
            $this->traces = array_slice($this->traces, -$this->maxTraces);
        }
    }
    
    /**
     * 记录日志
     */
    private function logTrace(string $type, string $taskId, string $name, int $depth, ?float $duration = null, ?string $extra = null): void
    {
        $indent = str_repeat('  ', $depth);
        $timestamp = date('H:i:s.') . substr(microtime(), 2, 3);
        
        $message = "[{$timestamp}] {$indent}";
        
        switch ($type) {
            case 'CALL':
                $message .= "→ {$name} (#{$taskId})";
                break;
            case 'RETURN':
                $durationStr = $duration !== null ? sprintf('%.2fms', $duration * 1000) : '';
                $message .= "← {$name} ({$durationStr})";
                break;
            case 'YIELD':
                $message .= "⏸ yield {$name}";
                break;
            case 'EXCEPTION':
                $message .= "❌ {$name}: {$extra}";
                break;
        }
        
        echo $message . "\n";
    }
    
    /**
     * 获取追踪记录
     */
    public function getTraces(int $limit = 100): array
    {
        return array_slice($this->traces, -$limit);
    }
    
    /**
     * 获取当前调用栈
     */
    public function getCallStack(): array
    {
        return $this->callStack;
    }
    
    /**
     * 生成追踪报告
     */
    public function report(): string
    {
        $report = "\n";
        $report .= "╔════════════════════════════════════════════════════════════╗\n";
        $report .= "║          PfinalClub AsyncIO - 调试追踪报告                 ║\n";
        $report .= "╚════════════════════════════════════════════════════════════╝\n";
        $report .= "\n";
        
        $report .= "📊 追踪统计:\n";
        $report .= "  ├─ 总记录数: " . count($this->traces) . "\n";
        $report .= "  ├─ 当前深度: {$this->depth}\n";
        $report .= "  └─ 调用栈深度: " . count($this->callStack) . "\n";
        $report .= "\n";
        
        if (!empty($this->callStack)) {
            $report .= "🔍 当前调用栈:\n";
            foreach ($this->callStack as $i => $call) {
                $indent = str_repeat('  ', $i + 1);
                $report .= "{$indent}└─ {$call['name']} (#{$call['task_id']})\n";
            }
            $report .= "\n";
        }
        
        // 统计各类型事件
        $types = array_count_values(array_column($this->traces, 'type'));
        $report .= "📈 事件类型分布:\n";
        foreach ($types as $type => $count) {
            $report .= "  ├─ {$type}: {$count}\n";
        }
        $report .= "\n";
        
        return $report;
    }
    
    /**
     * 导出追踪为 JSON
     */
    public function toJson(int $limit = 100): string
    {
        return json_encode($this->getTraces($limit), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 清空追踪记录
     */
    public function clear(): void
    {
        $this->traces = [];
        $this->callStack = [];
        $this->depth = 0;
        echo "🗑️  追踪记录已清空\n";
    }
    
    /**
     * 可视化调用链
     */
    public function visualizeCallChain(): string
    {
        $output = "\n";
        $output .= "🌳 协程调用链可视化:\n";
        $output .= str_repeat("─", 60) . "\n";
        
        $lastDepth = 0;
        foreach ($this->traces as $trace) {
            if ($trace['type'] === 'coroutine_call') {
                $indent = str_repeat('│  ', $trace['depth']);
                $output .= $indent . "├─→ {$trace['name']}\n";
                $lastDepth = $trace['depth'];
            } elseif ($trace['type'] === 'coroutine_return') {
                $indent = str_repeat('│  ', $trace['depth']);
                $duration = isset($trace['duration_ms']) ? " ({$trace['duration_ms']}ms)" : '';
                $output .= $indent . "└─← {$trace['name']}{$duration}\n";
            }
        }
        
        $output .= str_repeat("─", 60) . "\n";
        return $output;
    }
}

