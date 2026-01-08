<?php

namespace PfinalClub\Asyncio\Resource;

use PfinalClub\Asyncio\Core\EventLoop;

/**
 * 协程上下文管理器
 * 
 * 提供类似 Python contextvars 或 Go context 的功能
 * 允许在协程之间传递和共享上下文数据
 * 
 * 特性：
 * - 协程隔离：每个 Fiber 有独立的上下文
 * - 自动清理：Fiber 终止时自动清理上下文
 * - 线程安全：使用 Fiber ID 作为隔离键
 * - 定期清理：自动清理过期上下文
 * - 延迟清理：优化清理性能
 * 
 * 典型用例：
 * - 请求追踪（Request ID）
 * - 用户身份（User ID）
 * - 事务上下文（Transaction）
 * - 日志上下文（Logger Context）
 * 
 * @example
 * ```php
 * run(function() {
 *     // 设置请求 ID
 *     Context::set('request_id', uniqid('req_'));
 *     Context::set('user_id', 12345);
 *     
 *     $tasks = [];
 *     for ($i = 0; $i < 10; $i++) {
 *         $tasks[] = create_task(function() use ($i) {
 *             // 自动继承父协程的上下文
 *             $requestId = Context::get('request_id');
 *             $userId = Context::get('user_id');
 *             
 *             error_log("[$requestId] User $userId processing task $i");
 *         });
 *     }
 *     
 *     gather(...$tasks);
 * });
 * ```
 */
class Context
{
    /**
     * 存储所有 Fiber 的上下文
     * @var array<int, array<string, mixed>>
     */
    private static array $contexts = [];
    
    /**
     * 父子 Fiber 关系映射（用于上下文继承）
     * @var array<int, int>
     */
    private static array $parentMap = [];
    
    /**
     * 上次清理时间
     * @var float|null
     */
    private static ?float $lastCleanupTime = null;
    
    /**
     * 清理间隔（秒）
     */
    private const CLEANUP_INTERVAL = 5.0;
    
    /**
     * 清理阈值（上下文数量）
     */
    private const CLEANUP_THRESHOLD = 1000;
    
    /**
     * 设置当前协程的上下文变量
     * 
     * @param string $key 键名
     * @param mixed $value 值
     */
    public static function set(string $key, mixed $value): void
    {
        $fiberId = self::getCurrentFiberId();
        
        if (!isset(self::$contexts[$fiberId])) {
            self::$contexts[$fiberId] = [];
        }
        
        self::$contexts[$fiberId][$key] = $value;
    }
    
    /**
     * 获取当前协程的上下文变量
     * 
     * 如果当前协程没有该键，会递归尝试从父协程继承
     * 
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $fiberId = self::getCurrentFiberId();
        
        // 先查找当前协程
        if (isset(self::$contexts[$fiberId][$key])) {
            return self::$contexts[$fiberId][$key];
        }
        
        // 递归查找父协程链
        $currentId = $fiberId;
        while (isset(self::$parentMap[$currentId])) {
            $parentId = self::$parentMap[$currentId];
            if (isset(self::$contexts[$parentId][$key])) {
                return self::$contexts[$parentId][$key];
            }
            $currentId = $parentId;
        }
        
        return $default;
    }
    
    /**
     * 检查当前协程是否有指定的上下文变量
     * 
     * @param string $key 键名
     * @return bool
     */
    public static function has(string $key): bool
    {
        $fiberId = self::getCurrentFiberId();
        
        if (isset(self::$contexts[$fiberId][$key])) {
            return true;
        }
        
        // 递归检查父协程链
        $currentId = $fiberId;
        while (isset(self::$parentMap[$currentId])) {
            $parentId = self::$parentMap[$currentId];
            if (isset(self::$contexts[$parentId][$key])) {
                return true;
            }
            $currentId = $parentId;
        }
        
        return false;
    }
    
    /**
     * 删除当前协程的上下文变量
     * 
     * @param string $key 键名
     */
    public static function delete(string $key): void
    {
        $fiberId = self::getCurrentFiberId();
        unset(self::$contexts[$fiberId][$key]);
    }
    
    /**
     * 获取当前协程的所有上下文
     * 
     * @param bool $includeParent 是否包含父协程的上下文
     * @return array
     */
    public static function getAll(bool $includeParent = true): array
    {
        $fiberId = self::getCurrentFiberId();
        $context = self::$contexts[$fiberId] ?? [];
        
        // 合并父协程的上下文
        if ($includeParent && isset(self::$parentMap[$fiberId])) {
            $parentId = self::$parentMap[$fiberId];
            $parentContext = self::$contexts[$parentId] ?? [];
            $context = array_merge($parentContext, $context);
        }
        
        return $context;
    }
    
    /**
     * 清理当前协程的所有上下文
     */
    public static function clear(): void
    {
        $fiberId = self::getCurrentFiberId();
        unset(self::$contexts[$fiberId]);
        unset(self::$parentMap[$fiberId]);
    }
    
    /**
     * 设置父子 Fiber 关系（用于上下文继承）
     * 
     * @param int $childFiberId 子 Fiber ID
     * @param int $parentFiberId 父 Fiber ID
     * @internal 由 EventLoop 内部调用
     */
    public static function setParent(int $childFiberId, int $parentFiberId): void
    {
        self::$parentMap[$childFiberId] = $parentFiberId;
    }
    
    /**
     * 批量设置上下文变量
     * 
     * @param array $context 键值对数组
     */
    public static function setMultiple(array $context): void
    {
        $fiberId = self::getCurrentFiberId();
        
        if (!isset(self::$contexts[$fiberId])) {
            self::$contexts[$fiberId] = [];
        }
        
        self::$contexts[$fiberId] = array_merge(self::$contexts[$fiberId], $context);
    }
    
    /**
     * 清理已终止协程的上下文（内存清理）
     * 
     * @param array $activeFiberIds 活跃的 Fiber ID 列表
     * @return int 清理的上下文数量
     */
    public static function cleanup(array $activeFiberIds = []): int
    {
        if (empty($activeFiberIds)) {
            // 如果没有提供活跃列表，从 EventLoop 获取
            try {
                $activeFibers = EventLoop::getInstance()->getActiveFibers();
                $activeFiberIds = array_map(
                    fn($info) => $info['task']->getId(),
                    $activeFibers
                );
                $activeFiberIds[] = 0;  // 保留主线程的上下文
            } catch (\Throwable $e) {
                // EventLoop 可能还未初始化
                return 0;
            }
        }
        
        $cleaned = 0;
        
        // 清理上下文
        foreach (array_keys(self::$contexts) as $fiberId) {
            if (!in_array($fiberId, $activeFiberIds)) {
                unset(self::$contexts[$fiberId]);
                $cleaned++;
            }
        }
        
        // 清理父子关系
        foreach (array_keys(self::$parentMap) as $fiberId) {
            if (!in_array($fiberId, $activeFiberIds)) {
                unset(self::$parentMap[$fiberId]);
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 获取当前 Fiber 的唯一 ID
     * 
     * @return int
     */
    private static function getCurrentFiberId(): int
    {
        $fiber = \Fiber::getCurrent();
        if (!$fiber) {
            return 0;  // 主线程使用 ID 0
        }
        return spl_object_id($fiber);
    }
    
    /**
     * 获取统计信息
     * 
     * @return array
     */
    public static function getStats(): array
    {
        return [
            'total_contexts' => count(self::$contexts),
            'total_parent_mappings' => count(self::$parentMap),
            'memory_usage' => memory_get_usage(true),
        ];
    }
    
    /**
     * 导出所有上下文（调试用）
     * 
     * @return array
     */
    public static function dump(): array
    {
        return [
            'contexts' => self::$contexts,
            'parent_map' => self::$parentMap,
            'stats' => self::getStats(),
        ];
    }
}

