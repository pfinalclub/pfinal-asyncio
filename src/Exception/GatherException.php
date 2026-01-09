<?php

namespace PfinalClub\Asyncio\Exception;

/**
 * Gather 聚合异常
 * 
 * 当 gather() 中有任务失败时抛出此异常
 * 包含所有失败任务的异常和成功任务的结果
 * 
 * @example
 * ```php
 * try {
 *     $results = gather($task1, $task2, $task3);
 * } catch (GatherException $e) {
 *     echo $e->getDetailedReport();
 *     
 *     // 获取失败的任务
 *     foreach ($e->getExceptions() as $index => $exception) {
 *         echo "Task {$index} failed: {$exception->getMessage()}\n";
 *     }
 *     
 *     // 获取成功的任务
 *     $successResults = $e->getResults();
 * }
 * ```
 */
class GatherException extends \RuntimeException
{
    private array $exceptions;
    private array $results;
    private array $taskNames;
    
    /**
     * @param array $exceptions 失败任务的异常（索引 => 异常对象）
     * @param array $results 成功任务的结果（索引 => 结果）
     * @param array $taskNames 任务名称（索引 => 名称）
     */
    public function __construct(array $exceptions, array $results, array $taskNames = [])
    {
        $this->exceptions = $exceptions;
        $this->results = $results;
        $this->taskNames = $taskNames;
        
        $failedCount = count($exceptions);
        $successCount = count($results);
        $totalCount = $failedCount + $successCount;
        
        parent::__construct(
            "gather() failed: {$failedCount} of {$totalCount} task(s) failed, {$successCount} succeeded"
        );
    }
    
    /**
     * 获取所有失败任务的异常
     * 
     * @return array<int, \Throwable> 索引 => 异常对象
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }
    
    /**
     * 获取所有成功任务的结果
     * 
     * @return array<int, mixed> 索引 => 结果
     */
    public function getResults(): array
    {
        return $this->results;
    }
    
    /**
     * 获取任务名称映射
     * 
     * @return array<int, string>
     */
    public function getTaskNames(): array
    {
        return $this->taskNames;
    }
    
    /**
     * 获取失败任务的数量
     */
    public function getFailedCount(): int
    {
        return count($this->exceptions);
    }
    
    /**
     * 获取成功任务的数量
     */
    public function getSuccessCount(): int
    {
        return count($this->results);
    }
    
    /**
     * 检查指定索引的任务是否失败
     */
    public function hasFailed(int $index): bool
    {
        return isset($this->exceptions[$index]);
    }
    
    /**
     * 获取指定索引任务的异常
     */
    public function getException(int $index): ?\Throwable
    {
        return $this->exceptions[$index] ?? null;
    }
    
    /**
     * 获取详细的错误报告
     * 
     * @return string 格式化的错误报告
     */
    public function getDetailedReport(): string
    {
        $report = "=== Gather Exception Report ===\n";
        $report .= "Total Tasks: " . ($this->getFailedCount() + $this->getSuccessCount()) . "\n";
        $report .= "Failed: {$this->getFailedCount()}, Success: {$this->getSuccessCount()}\n\n";
        
        if (!empty($this->exceptions)) {
            $report .= "Failed Tasks:\n";
            foreach ($this->exceptions as $index => $exception) {
                $taskName = $this->taskNames[$index] ?? "Task#{$index}";
                $report .= "  [{$taskName}] {$exception->getMessage()}\n";
                $report .= "    File: {$exception->getFile()}:{$exception->getLine()}\n";
                
                // 简化的堆栈跟踪（只显示前 3 行）
                $trace = $exception->getTrace();
                $traceLines = array_slice($trace, 0, 3);
                foreach ($traceLines as $line) {
                    $file = $line['file'] ?? 'unknown';
                    $lineNum = $line['line'] ?? '?';
                    $function = $line['function'] ?? '';
                    $report .= "      at {$function}() in {$file}:{$lineNum}\n";
                }
                $report .= "\n";
            }
        }
        
        if (!empty($this->results)) {
            $report .= "Successful Tasks:\n";
            foreach ($this->results as $index => $result) {
                $taskName = $this->taskNames[$index] ?? "Task#{$index}";
                $resultPreview = is_scalar($result) 
                    ? var_export($result, true) 
                    : gettype($result);
                $report .= "  [{$taskName}] => {$resultPreview}\n";
            }
        }
        
        $report .= "===============================\n";
        
        return $report;
    }
    
    /**
     * 获取第一个失败任务的异常
     * 
     * @return \Throwable|null
     */
    public function getFirstException(): ?\Throwable
    {
        if (empty($this->exceptions)) {
            return null;
        }
        return reset($this->exceptions);
    }
    
    /**
     * 将异常转换为 JSON 格式
     * 
     * @return string JSON 字符串
     */
    public function toJson(): string
    {
        $data = [
            'message' => $this->getMessage(),
            'failed_count' => $this->getFailedCount(),
            'success_count' => $this->getSuccessCount(),
            'exceptions' => [],
            'results' => $this->results,
        ];
        
        foreach ($this->exceptions as $index => $exception) {
            $data['exceptions'][$index] = [
                'message' => $exception->getMessage(),
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'task_name' => $this->taskNames[$index] ?? "Task#{$index}",
            ];
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

