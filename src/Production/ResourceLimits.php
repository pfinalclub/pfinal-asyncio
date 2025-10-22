<?php

namespace PfinalClub\Asyncio\Production;

use PfinalClub\Asyncio\EventLoop;

/**
 * 资源限制
 * 
 * 限制内存使用和任务数量，防止资源耗尽
 */
class ResourceLimits
{
    private static ?ResourceLimits $instance = null;
    private ?int $maxMemoryMb = null;
    private ?int $maxTasks = null;
    private bool $enforceMemoryLimit = false;
    private bool $enforceTaskLimit = false;
    private array $violations = [];
    
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
     * 设置内存限制
     * 
     * @param int $megabytes 最大内存（MB）
     * @param bool $enforce 是否强制执行（抛出异常）
     */
    public function setMemoryLimit(int $megabytes, bool $enforce = false): void
    {
        if ($megabytes <= 0) {
            throw new \InvalidArgumentException("Memory limit must be positive, got: {$megabytes}");
        }
        
        $this->maxMemoryMb = $megabytes;
        $this->enforceMemoryLimit = $enforce;
        
        echo "Memory limit set to {$megabytes} MB" . ($enforce ? ' (enforced)' : '') . "\n";
    }
    
    /**
     * 设置任务数量限制
     * 
     * @param int $max 最大任务数
     * @param bool $enforce 是否强制执行（抛出异常）
     */
    public function setTaskLimit(int $max, bool $enforce = false): void
    {
        if ($max <= 0) {
            throw new \InvalidArgumentException("Task limit must be positive, got: {$max}");
        }
        
        $this->maxTasks = $max;
        $this->enforceTaskLimit = $enforce;
        
        echo "Task limit set to {$max}" . ($enforce ? ' (enforced)' : '') . "\n";
    }
    
    /**
     * 检查内存使用
     * 
     * @throws \RuntimeException 如果启用强制执行且超过限制
     */
    public function checkMemory(): bool
    {
        if ($this->maxMemoryMb === null) {
            return true;
        }
        
        $usageMb = memory_get_usage(true) / 1024 / 1024;
        
        if ($usageMb > $this->maxMemoryMb) {
            $violation = [
                'type' => 'memory',
                'current' => round($usageMb, 2),
                'limit' => $this->maxMemoryMb,
                'timestamp' => time(),
            ];
            
            $this->violations[] = $violation;
            
            if ($this->enforceMemoryLimit) {
                throw new \RuntimeException(
                    "Memory limit exceeded: " . round($usageMb, 2) . " MB > {$this->maxMemoryMb} MB"
                );
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查任务数量
     * 
     * @throws \RuntimeException 如果启用强制执行且超过限制
     */
    public function checkTaskCount(): bool
    {
        if ($this->maxTasks === null) {
            return true;
        }
        
        $loop = EventLoop::getInstance();
        $fibers = $loop->getActiveFibers();
        $count = count($fibers);
        
        if ($count > $this->maxTasks) {
            $violation = [
                'type' => 'task_count',
                'current' => $count,
                'limit' => $this->maxTasks,
                'timestamp' => time(),
            ];
            
            $this->violations[] = $violation;
            
            if ($this->enforceTaskLimit) {
                throw new \RuntimeException(
                    "Task limit exceeded: {$count} > {$this->maxTasks}"
                );
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * 检查所有限制
     * 
     * @return array 检查结果
     */
    public function checkAll(): array
    {
        $results = [
            'memory' => ['ok' => true, 'message' => 'No limit set'],
            'tasks' => ['ok' => true, 'message' => 'No limit set'],
        ];
        
        // 检查内存
        if ($this->maxMemoryMb !== null) {
            try {
                $ok = $this->checkMemory();
                $usageMb = memory_get_usage(true) / 1024 / 1024;
                
                $results['memory'] = [
                    'ok' => $ok,
                    'current_mb' => round($usageMb, 2),
                    'limit_mb' => $this->maxMemoryMb,
                    'usage_percent' => round(($usageMb / $this->maxMemoryMb) * 100, 2),
                ];
            } catch (\RuntimeException $e) {
                $results['memory'] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        // 检查任务数
        if ($this->maxTasks !== null) {
            try {
                $ok = $this->checkTaskCount();
                $loop = EventLoop::getInstance();
                $count = count($loop->getActiveFibers());
                
                $results['tasks'] = [
                    'ok' => $ok,
                    'current' => $count,
                    'limit' => $this->maxTasks,
                    'usage_percent' => round(($count / $this->maxTasks) * 100, 2),
                ];
            } catch (\RuntimeException $e) {
                $results['tasks'] = [
                    'ok' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * 获取违规记录
     */
    public function getViolations(int $limit = 100): array
    {
        return array_slice($this->violations, -$limit);
    }
    
    /**
     * 清除违规记录
     */
    public function clearViolations(): void
    {
        $this->violations = [];
    }
    
    /**
     * 获取配置
     */
    public function getConfig(): array
    {
        return [
            'memory_limit_mb' => $this->maxMemoryMb,
            'memory_enforce' => $this->enforceMemoryLimit,
            'task_limit' => $this->maxTasks,
            'task_enforce' => $this->enforceTaskLimit,
        ];
    }
    
    /**
     * 导出为 JSON
     */
    public function toJson(): string
    {
        return json_encode([
            'config' => $this->getConfig(),
            'status' => $this->checkAll(),
            'violations_count' => count($this->violations),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

