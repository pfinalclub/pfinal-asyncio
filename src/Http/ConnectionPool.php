<?php

namespace PfinalClub\Asyncio\Http;

use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;

/**
 * HTTP 连接池
 * 管理和复用 HTTP 连接，提高性能
 */
class ConnectionPool
{
    private array $pools = [];
    private int $maxConnectionsPerHost = 10;
    private float $connectionTimeout = 60.0;
    private float $idleTimeout = 30.0;
    private ?int $cleanupTimerId = null;
    
    public function __construct(array $config = [])
    {
        $this->maxConnectionsPerHost = $config['max_connections'] ?? 10;
        $this->connectionTimeout = $config['connection_timeout'] ?? 60.0;
        $this->idleTimeout = $config['idle_timeout'] ?? 30.0;
        
        // 定期清理过期连接（每10秒）
        $this->cleanupTimerId = Timer::add(10, fn() => $this->cleanupIdleConnections(), [], true);
    }
    
    /**
     * 从池中获取可用连接
     */
    public function getConnection(string $host, int $port, bool $ssl = false): ?AsyncTcpConnection
    {
        $key = "{$host}:{$port}";
        
        // 从池中获取可用连接
        if (isset($this->pools[$key])) {
            foreach ($this->pools[$key] as $idx => $item) {
                if ($item['available'] && !$item['connection']->isPaused()) {
                    $this->pools[$key][$idx]['available'] = false;
                    $this->pools[$key][$idx]['last_used'] = microtime(true);
                    return $item['connection'];
                }
            }
        }
        
        // 检查是否达到最大连接数
        if (isset($this->pools[$key]) && count($this->pools[$key]) >= $this->maxConnectionsPerHost) {
            return null; // 需要等待或创建新连接
        }
        
        // 返回 null 表示需要调用者创建新连接
        return null;
    }
    
    /**
     * 添加新连接到池中
     */
    public function addConnection(string $host, int $port, AsyncTcpConnection $connection): void
    {
        $key = "{$host}:{$port}";
        
        if (!isset($this->pools[$key])) {
            $this->pools[$key] = [];
        }
        
        $this->pools[$key][] = [
            'connection' => $connection,
            'available' => false,
            'created_at' => microtime(true),
            'last_used' => microtime(true),
        ];
    }
    
    /**
     * 释放连接回池中
     */
    public function releaseConnection(string $host, int $port, AsyncTcpConnection $connection): void
    {
        $key = "{$host}:{$port}";
        
        if (isset($this->pools[$key])) {
            foreach ($this->pools[$key] as $idx => $item) {
                if ($item['connection'] === $connection) {
                    $this->pools[$key][$idx]['available'] = true;
                    $this->pools[$key][$idx]['last_used'] = microtime(true);
                    break;
                }
            }
        }
    }
    
    /**
     * 清理空闲和过期的连接
     */
    private function cleanupIdleConnections(): void
    {
        $now = microtime(true);
        
        foreach ($this->pools as $key => $connections) {
            foreach ($connections as $idx => $item) {
                // 移除空闲超时的连接
                if ($item['available'] && ($now - $item['last_used']) > $this->idleTimeout) {
                    $item['connection']->close();
                    unset($this->pools[$key][$idx]);
                }
                
                // 移除超过总超时的连接
                if (($now - $item['created_at']) > $this->connectionTimeout) {
                    $item['connection']->close();
                    unset($this->pools[$key][$idx]);
                }
            }
            
            // 重新索引数组
            if (isset($this->pools[$key])) {
                $this->pools[$key] = array_values($this->pools[$key]);
                
                // 删除空的池
                if (empty($this->pools[$key])) {
                    unset($this->pools[$key]);
                }
            }
        }
    }
    
    /**
     * 获取连接池统计信息
     */
    public function getStats(): array
    {
        $stats = [];
        foreach ($this->pools as $key => $connections) {
            $available = count(array_filter($connections, fn($c) => $c['available']));
            $stats[$key] = [
                'total' => count($connections),
                'available' => $available,
                'in_use' => count($connections) - $available,
            ];
        }
        return $stats;
    }
    
    /**
     * 关闭所有连接
     */
    public function closeAll(): void
    {
        foreach ($this->pools as $connections) {
            foreach ($connections as $item) {
                $item['connection']->close();
            }
        }
        $this->pools = [];
        
        if ($this->cleanupTimerId !== null) {
            Timer::del($this->cleanupTimerId);
            $this->cleanupTimerId = null;
        }
    }
    
    public function __destruct()
    {
        $this->closeAll();
    }
}

