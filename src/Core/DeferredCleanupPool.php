<?php

namespace PfinalClub\Asyncio\Core;

/**
 * 延迟清理池 - 优化热路径性能
 * 
 * 设计理念：
 * - 创建时不立即unset，而是加入延迟池
 * - 在合适时机批量处理延迟池
 * - 控制延迟池大小，避免内存泄漏
 * - 提供统计信息用于监控
 * 
 * @internal 核心组件，不对用户暴露
 */
class DeferredCleanupPool
{
    /** @var array<int> 延迟清理的Fiber ID池 */
    private array $pool = [];
    
    /** @var int 延迟池最大大小 */
    private int $maxSize;
    
    /** @var int 刷新次数统计 */
    private int $flushedCount = 0;
    
    /** @var int 总加入数量统计 */
    private int $totalAdded = 0;
    
    /** @var float 上次刷新时间 */
    private float $lastFlushTime = 0;
    
    /** @var int 强制刷新阈值 */
    private const FORCE_FLUSH_THRESHOLD = 50;
    
    /**
     * 构造函数
     * 
     * @param int $maxSize 延迟池最大大小
     */
    public function __construct(int $maxSize = 100)
    {
        $this->maxSize = $maxSize;
        $this->lastFlushTime = microtime(true);
    }
    
    /**
     * 添加到延迟清理池
     * 
     * @param int $fiberId Fiber ID
     * @return bool 是否立即刷新（池满）
     */
    public function add(int $fiberId): bool
    {
        $this->totalAdded++;
        
        if (count($this->pool) >= $this->maxSize) {
            $this->flush();
            return true;
        }
        
        $this->pool[] = $fiberId;
        return false;
    }
    
    /**
     * 刷新延迟池
     * 
     * @return int 清理的数量
     */
    public function flush(): int
    {
        $count = count($this->pool);
        if ($count > 0) {
            $this->flushedCount++;
            $this->lastFlushTime = microtime(true);
        }
        
        $this->pool = [];
        return $count;
    }
    
    /**
     * 处理延迟池中的Fiber
     * 
     * @param array &$fibers Fiber数组引用
     * @return int 实际清理的数量
     */
    public function processFibers(array &$fibers): int
    {
        if (empty($this->pool)) {
            return 0;
        }
        
        $cleanedCount = 0;
        
        foreach ($this->pool as $fiberId) {
            if (isset($fibers[$fiberId])) {
                unset($fibers[$fiberId]);
                $cleanedCount++;
            }
        }
        
        $this->flush();
        return $cleanedCount;
    }
    
    /**
     * 检查是否需要强制刷新
     * 
     * @return bool
     */
    public function shouldForceFlush(): bool
    {
        return count($this->pool) >= self::FORCE_FLUSH_THRESHOLD;
    }
    
    /**
     * 检查池是否为空
     * 
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->pool);
    }
    
    /**
     * 获取当前池大小
     * 
     * @return int
     */
    public function getSize(): int
    {
        return count($this->pool);
    }
    
    /**
     * 获取延迟池统计信息
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'pool_size' => count($this->pool),
            'max_size' => $this->maxSize,
            'flushed_count' => $this->flushedCount,
            'total_added' => $this->totalAdded,
            'last_flush_time' => $this->lastFlushTime,
            'time_since_last_flush' => $this->lastFlushTime > 0 ? 
                microtime(true) - $this->lastFlushTime : 0,
            'avg_flush_size' => $this->flushedCount > 0 ? 
                $this->totalAdded / $this->flushedCount : 0,
        ];
    }
    
    /**
     * 获取详细性能指标
     * 
     * @return array
     */
    public function getPerformanceMetrics(): array
    {
        $stats = $this->getStats();
        
        return array_merge($stats, [
            'pool_utilization' => $this->maxSize > 0 ? 
                ($stats['pool_size'] / $this->maxSize) * 100 : 0,
            'flush_frequency' => $this->lastFlushTime > 0 ? 
                $stats['flushed_count'] / (microtime(true) - $this->lastFlushTime) : 0,
            'memory_efficiency' => $stats['total_added'] > 0 ? 
                ($stats['total_added'] - $stats['pool_size']) / $stats['total_added'] * 100 : 0,
        ]);
    }
    
    /**
     * 重置统计信息
     */
    public function resetStats(): void
    {
        $this->flushedCount = 0;
        $this->totalAdded = 0;
        $this->lastFlushTime = microtime(true);
    }
    
    /**
     * 设置最大池大小
     * 
     * @param int $maxSize
     */
    public function setMaxSize(int $maxSize): void
    {
        $this->maxSize = max(1, $maxSize);
        
        // 如果当前池超过新的最大大小，立即刷新
        if (count($this->pool) > $this->maxSize) {
            $this->flush();
        }
    }
    
    /**
     * 获取最大池大小
     * 
     * @return int
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }
}