<?php

namespace PfinalClub\Asyncio\Production;

use PfinalClub\Asyncio\EventLoop;
use PfinalClub\Asyncio\Monitor\{AsyncioMonitor, PerformanceMonitor};

/**
 * 健康检查
 * 
 * 提供系统健康状态检查功能，用于监控和负载均衡
 */
class HealthCheck
{
    private static ?HealthCheck $instance = null;
    private array $checks = [];
    private array $lastResult = [];
    private float $lastCheckTime = 0;
    private float $cacheDuration = 1.0;  // 缓存1秒
    
    private function __construct()
    {
        // 注册默认检查
        $this->registerDefaultChecks();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 注册默认检查项
     */
    private function registerDefaultChecks(): void
    {
        // PHP 版本检查
        $this->registerCheck('php_version', function() {
            return [
                'status' => 'ok',
                'version' => PHP_VERSION,
                'fiber_supported' => version_compare(PHP_VERSION, '8.1.0', '>='),
            ];
        });
        
        // 内存检查
        $this->registerCheck('memory', function() {
            $usage = memory_get_usage(true);
            $peak = memory_get_peak_usage(true);
            $limit = ini_get('memory_limit');
            
            if ($limit === '-1') {
                $limitBytes = PHP_INT_MAX;
            } else {
                $limitBytes = $this->parseMemoryLimit($limit);
            }
            
            $usagePercent = ($usage / $limitBytes) * 100;
            $status = $usagePercent < 80 ? 'ok' : ($usagePercent < 90 ? 'warning' : 'critical');
            
            return [
                'status' => $status,
                'usage_mb' => round($usage / 1024 / 1024, 2),
                'peak_mb' => round($peak / 1024 / 1024, 2),
                'limit_mb' => round($limitBytes / 1024 / 1024, 2),
                'usage_percent' => round($usagePercent, 2),
            ];
        });
        
        // EventLoop 检查
        $this->registerCheck('event_loop', function() {
            $loop = EventLoop::getInstance();
            $fibers = $loop->getActiveFibers();
            
            return [
                'status' => 'ok',
                'active_fibers' => count($fibers),
                'mode' => 'fiber-event-driven',
            ];
        });
        
        // 性能监控检查
        $this->registerCheck('performance', function() {
            $monitor = PerformanceMonitor::getInstance();
            $slowTasks = $monitor->getSlowTasks();
            
            $status = count($slowTasks) > 10 ? 'warning' : 'ok';
            
            return [
                'status' => $status,
                'slow_tasks_count' => count($slowTasks),
            ];
        });
    }
    
    /**
     * 注册自定义检查
     */
    public function registerCheck(string $name, callable $check): void
    {
        $this->checks[$name] = $check;
    }
    
    /**
     * 执行健康检查
     */
    public function check(bool $detailed = false): array
    {
        // 使用缓存
        $now = microtime(true);
        if ($now - $this->lastCheckTime < $this->cacheDuration && !empty($this->lastResult)) {
            return $this->lastResult;
        }
        
        $result = [
            'status' => 'ok',
            'timestamp' => time(),
            'checks' => [],
        ];
        
        foreach ($this->checks as $name => $check) {
            try {
                $checkResult = $check();
                $result['checks'][$name] = $checkResult;
                
                // 更新总体状态
                if (isset($checkResult['status'])) {
                    if ($checkResult['status'] === 'critical') {
                        $result['status'] = 'critical';
                    } elseif ($checkResult['status'] === 'warning' && $result['status'] !== 'critical') {
                        $result['status'] = 'warning';
                    }
                }
            } catch (\Throwable $e) {
                $result['checks'][$name] = [
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
                $result['status'] = 'error';
            }
        }
        
        // 添加摘要
        if ($detailed) {
            $result['summary'] = $this->generateSummary();
        }
        
        $this->lastResult = $result;
        $this->lastCheckTime = $now;
        
        return $result;
    }
    
    /**
     * 生成摘要
     */
    private function generateSummary(): array
    {
        $monitor = AsyncioMonitor::getInstance();
        $snapshot = $monitor->snapshot();
        
        return [
            'uptime_seconds' => $snapshot['uptime_seconds'],
            'memory_mb' => $snapshot['memory']['current_mb'],
            'active_fibers' => $snapshot['event_loop']['active_fibers'],
        ];
    }
    
    /**
     * 解析内存限制
     */
    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int)$limit;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * 返回简单的健康状态（用于负载均衡器）
     */
    public function isHealthy(): bool
    {
        $result = $this->check();
        return $result['status'] === 'ok' || $result['status'] === 'warning';
    }
    
    /**
     * 导出为 JSON
     */
    public function toJson(bool $detailed = false): string
    {
        return json_encode($this->check($detailed), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * 设置缓存时长
     */
    public function setCacheDuration(float $seconds): void
    {
        $this->cacheDuration = $seconds;
    }
}

