<?php

namespace PfinalClub\Asyncio\Core;

use Fiber;
use Workerman\Timer;
use PfinalClub\Asyncio\Core\Task;
use PfinalClub\Asyncio\Exception\GatherException;
use PfinalClub\Asyncio\Observable\Observable;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;

/**
 * 延迟清理池 - 优化热路径性能
 * 
 * 设计理念：
 * - 创建时不立即unset，而是加入延迟池
 * - 在合适时机批量处理延迟池
 * - 控制延迟池大小，避免内存泄漏
 */
class DeferredCleanupPool
{
    private array $pool = [];
    private int $maxSize;
    private int $flushedCount = 0;
    
    public function __construct(int $maxSize = 100)
    {
        $this->maxSize = $maxSize;
    }
    
    /**
     * 添加到延迟清理池
     * 
     * @param int $fiberId
     * @return bool 是否立即刷新（池满）
     */
    public function add(int $fiberId): bool
    {
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
        $this->flushedCount += $count;
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
     * 获取延迟池统计
     * 
     * @return array
     */
    public function getStats(): array
    {
        return [
            'pool_size' => count($this->pool),
            'max_size' => $this->maxSize,
            'flushed_count' => $this->flushedCount,
        ];
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
}

/**
 * 内存监控器 - 检测内存压力
 */
class MemoryMonitor
{
    private int $peakFiberCount = 0;
    private const MEMORY_PRESSURE_RATIO = 0.8;
    
    public function updatePeakFiberCount(int $count): void
    {
        $this->peakFiberCount = max($this->peakFiberCount, $count);
    }
    
    /**
     * 检查是否有内存压力
     * 
     * @param array $fibers
     * @return bool
     */
    public function hasMemoryPressure(array $fibers): bool
    {
        // 1. 基于Fiber数量判断
        $currentCount = count($fibers);
        $fiberThreshold = $this->peakFiberCount * self::MEMORY_PRESSURE_RATIO;
        
        if ($currentCount > $fiberThreshold) {
            return true;
        }
        
        // 2. 基于PHP内存使用判断
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        if ($memoryLimit && $memoryUsage > $memoryLimit * self::MEMORY_PRESSURE_RATIO) {
            return true;
        }
        
        // 3. 基于系统内存使用判断（如果可用）
        if (function_exists('memory_get_usage')) {
            $systemMemoryUsage = $this->getSystemMemoryUsage();
            if ($systemMemoryUsage > self::MEMORY_PRESSURE_RATIO) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 获取PHP内存限制
     * 
     * @return int|null
     */
    private function getMemoryLimit(): ?int
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return null;
        }
        
        return $this->parseMemoryLimit($limit);
    }
    
    /**
     * 解析内存限制字符串
     * 
     * @param string $limit
     * @return int
     */
    private function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) substr($limit, 0, -1);
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $limit,
        };
    }
    
    /**
     * 获取系统内存使用率（Linux/macOS）
     * 
     * @return float
     */
    private function getSystemMemoryUsage(): float
    {
        // Linux: /proc/meminfo
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $available);
            
            if (isset($total[1], $available[1])) {
                $totalKB = (int) $total[1];
                $usedKB = $totalKB - (int) $available[1];
                return $usedKB / $totalKB;
            }
        }
        
        // macOS: vm_stat
        if (PHP_OS === 'Darwin') {
            exec('vm_stat', $output);
            $page_size = 4096;
            $free_pages = 0;
            $inactive_pages = 0;
            $active_pages = 0;
            $wired_pages = 0;
            
            foreach ($output as $line) {
                if (preg_match('/Pages free:\s+(\d+)/', $line, $match)) {
                    $free_pages = (int) $match[1];
                } elseif (preg_match('/Pages inactive:\s+(\d+)/', $line, $match)) {
                    $inactive_pages = (int) $match[1];
                } elseif (preg_match('/Pages active:\s+(\d+)/', $line, $match)) {
                    $active_pages = (int) $match[1];
                } elseif (preg_match('/Pages wired down:\s+(\d+)/', $line, $match)) {
                    $wired_pages = (int) $match[1];
                }
            }
            
            $total_pages = $free_pages + $inactive_pages + $active_pages + $wired_pages;
            if ($total_pages > 0) {
                return ($active_pages + $wired_pages) / $total_pages;
            }
        }
        
        return 0.5; // 默认50%使用率
    }
}

/**
 * 清理性能分析器
 */
class CleanupProfiler
{
    private static array $profiles = [];
    private static int $maxProfiles = 100;
    
    /**
     * 分析清理函数性能
     * 
     * @param callable $cleanupFunc
     * @return array 性能数据
     */
    public static function profile(callable $cleanupFunc): array
    {
        $startTime = microtime(true);
        $memoryBefore = memory_get_usage();
        $memoryBeforePeak = memory_get_peak_usage();
        
        $result = $cleanupFunc();
        
        $endTime = microtime(true);
        $memoryAfter = memory_get_usage();
        $memoryAfterPeak = memory_get_peak_usage();
        
        $profile = [
            'timestamp' => microtime(true),
            'duration' => ($endTime - $startTime) * 1000, // ms
            'memory_freed' => $memoryBefore - $memoryAfter,
            'memory_peak_before' => $memoryBeforePeak,
            'memory_peak_after' => $memoryAfterPeak,
            'items_processed' => $result,
        ];
        
        self::$profiles[] = $profile;
        
        // 保持最近的分析记录
        if (count(self::$profiles) > self::$maxProfiles) {
            self::$profiles = array_slice(self::$profiles, -self::$maxProfiles);
        }
        
        return $profile;
    }
    
    /**
     * 获取所有性能分析
     * 
     * @return array
     */
    public static function getProfiles(): array
    {
        return self::$profiles;
    }
    
    /**
     * 获取最近的分析
     * 
     * @return array|null
     */
    public static function getLastProfile(): ?array
    {
        return empty(self::$profiles) ? null : end(self::$profiles);
    }
    
    /**
     * 获取平均性能
     * 
     * @return array
     */
    public static function getAveragePerformance(): array
    {
        if (empty(self::$profiles)) {
            return [
                'avg_duration' => 0,
                'avg_memory_freed' => 0,
                'avg_items_processed' => 0,
                'total_profiles' => 0,
            ];
        }
        
        $totalDuration = 0;
        $totalMemoryFreed = 0;
        $totalItemsProcessed = 0;
        
        foreach (self::$profiles as $profile) {
            $totalDuration += $profile['duration'];
            $totalMemoryFreed += $profile['memory_freed'];
            $totalItemsProcessed += $profile['items_processed'];
        }
        
        return [
            'avg_duration' => $totalDuration / count(self::$profiles),
            'avg_memory_freed' => $totalMemoryFreed / count(self::$profiles),
            'avg_items_processed' => $totalItemsProcessed / count(self::$profiles),
            'total_profiles' => count(self::$profiles),
        ];
    }
    
    /**
     * 清理所有分析数据
     */
    public static function clearProfiles(): void
    {
        self::$profiles = [];
    }
}

/**
 * 改进的Fiber清理器 - 集成所有优化策略
 */
class ImprovedFiberCleanup
{
    private const HIGH_FREQ_THRESHOLD = 50;
    private const LOW_FREQ_INTERVAL = 5.0;
    
    private int $fiberCleanupCounter = 0;
    private DeferredCleanupPool $deferredPool;
    private MemoryMonitor $memoryMonitor;
    private ?int $lowFreqTimerId = null;
    
    // 清理统计
    private array $stats = [
        'high_freq_cleanups' => 0,
        'low_freq_cleanups' => 0,
        'memory_pressure_cleanups' => 0,
        'total_fibers_cleaned' => 0,
        'peak_fiber_count' => 0,
        'deferred_flushes' => 0,
    ];
    
    public function __construct()
    {
        $this->deferredPool = new DeferredCleanupPool();
        $this->memoryMonitor = new MemoryMonitor();
        
        // 启动低频定时清理
        $this->startLowFreqCleanup();
    }
    
    /**
     * 智能清理触发器
     * 
     * @param array &$fibers
     * @return int 清理的Fiber数量
     */
    public function triggerSmartCleanup(array &$fibers): int
    {
        $currentCount = count($fibers);
        $this->memoryMonitor->updatePeakFiberCount($currentCount);
        $this->stats['peak_fiber_count'] = max($this->stats['peak_fiber_count'], $currentCount);
        
        $totalCleaned = 0;
        
        // 1. 高频计数器清理
        $this->fiberCleanupCounter++;
        if ($this->fiberCleanupCounter >= self::HIGH_FREQ_THRESHOLD) {
            $totalCleaned += $this->performHighFreqCleanup($fibers);
            $this->fiberCleanupCounter = 0;
        }
        
        // 2. 内存压力检查
        if ($this->memoryMonitor->hasMemoryPressure($fibers)) {
            $totalCleaned += $this->performMemoryPressureCleanup($fibers);
        }
        
        // 3. 处理延迟池
        $totalCleaned += $this->deferredPool->processFibers($fibers);
        
        $this->stats['total_fibers_cleaned'] += $totalCleaned;
        
        return $totalCleaned;
    }
    
    /**
     * 高频清理（快速扫描）
     * 
     * @param array &$fibers
     * @return int
     */
    private function performHighFreqCleanup(array &$fibers): int
    {
        return CleanupProfiler::profile(function() use (&$fibers) {
            $cleanedCount = 0;
            
            // 快速扫描明显已终止的Fiber
            foreach ($fibers as $fiberId => $info) {
                if ($info['fiber']->isTerminated() && 
                    $info['state']->isTerminal()) {
                    // 添加到延迟池
                    if ($this->deferredPool->add($fiberId)) {
                        // 池满时立即处理
                        $cleanedCount += $this->deferredPool->processFibers($fibers);
                        $this->stats['deferred_flushes']++;
                    }
                    $cleanedCount++;
                }
            }
            
            $this->stats['high_freq_cleanups']++;
            return $cleanedCount;
        })['items_processed'];
    }
    
    /**
     * 内存压力清理（强制清理）
     * 
     * @param array &$fibers
     * @return int
     */
    private function performMemoryPressureCleanup(array &$fibers): int
    {
        return CleanupProfiler::profile(function() use (&$fibers) {
            $cleanedCount = 0;
            
            // 强制清理所有已终止的Fiber
            foreach ($fibers as $fiberId => $info) {
                if ($info['fiber']->isTerminated()) {
                    unset($fibers[$fiberId]);
                    $cleanedCount++;
                }
            }
            
            // 清空延迟池
            $this->deferredPool->flush();
            
            // 强制垃圾回收
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            $this->stats['memory_pressure_cleanups']++;
            return $cleanedCount;
        })['items_processed'];
    }
    
    /**
     * 低频定时清理（深度扫描）
     * 
     * @param array &$fibers
     * @return int
     */
    private function performLowFreqCleanup(array &$fibers): int
    {
        return CleanupProfiler::profile(function() use (&$fibers) {
            $cleanedCount = 0;
            
            // 深度扫描所有Fiber状态
            foreach ($fibers as $fiberId => $info) {
                if ($info['fiber']->isTerminated()) {
                    unset($fibers[$fiberId]);
                    $cleanedCount++;
                } elseif ($this->isStaleFiber($info)) {
                    // 清理长时间未活动的Fiber
                    unset($fibers[$fiberId]);
                    $cleanedCount++;
                }
            }
            
            // 处理延迟池
            $cleanedCount += $this->deferredPool->processFibers($fibers);
            
            // 周期性垃圾回收
            if (function_exists('gc_collect_cycles') && mt_rand(1, 10) === 1) {
                gc_collect_cycles();
            }
            
            $this->stats['low_freq_cleanups']++;
            return $cleanedCount;
        })['items_processed'];
    }
    
    /**
     * 检查Fiber是否陈旧
     * 
     * @param array $info
     * @return bool
     */
    private function isStaleFiber(array $info): bool
    {
        if (!isset($info['created_at'])) {
            return false;
        }
        
        $staleThreshold = 300; // 5分钟
        return (time() - $info['created_at']) > $staleThreshold;
    }
    
    /**
     * 启动低频定时清理
     */
    private function startLowFreqCleanup(): void
    {
        $this->lowFreqTimerId = Timer::add(
            self::LOW_FREQ_INTERVAL,
            function () {
                // 需要在EventLoop中集成这个回调
                // 这里先占位，实际集成时需要修改
            },
            [],
            true
        );
    }
    
    /**
     * 获取清理统计
     * 
     * @return array
     */
    public function getCleanupStats(): array
    {
        return array_merge($this->stats, [
            'deferred_pool' => $this->deferredPool->getStats(),
            'performance' => CleanupProfiler::getAveragePerformance(),
        ]);
    }
    
    /**
     * 重置统计
     */
    public function resetStats(): void
    {
        $this->stats = [
            'high_freq_cleanups' => 0,
            'low_freq_cleanups' => 0,
            'memory_pressure_cleanups' => 0,
            'total_fibers_cleaned' => 0,
            'peak_fiber_count' => 0,
            'deferred_flushes' => 0,
        ];
        CleanupProfiler::clearProfiles();
    }
    
    /**
     * 析构函数
     */
    public function __destruct()
    {
        if ($this->lowFreqTimerId !== null) {
            Timer::del($this->lowFreqTimerId);
        }
    }
}