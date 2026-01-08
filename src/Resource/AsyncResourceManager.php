<?php

namespace PfinalClub\Asyncio\Resource;

use PfinalClub\Asyncio\Concurrency\CancellationScope;

/**
 * 异步资源管理器 - 管理 Runtime 资源的生命周期
 * 
 * @api-stable
 */
class AsyncResourceManager
{
    private static array $resources = [];
    private static int $cleanupCounter = 0;
    private const CLEANUP_THRESHOLD = 100;
    
    /**
     * 注册资源到当前取消作用域
     * 
     * @param AsyncResource $resource 要注册的资源
     * @return void
     * @throws \RuntimeException 如果没有活动的 CancellationScope
     */
    public static function register(AsyncResource $resource): void
    {
        $scope = CancellationScope::current();
        if (!$scope) {
            throw new \RuntimeException(
                "No active CancellationScope. Use CancellationScope::run() to create a scope for resources."
            );
        }
        
        $scopeId = spl_object_id($scope);
        if (!isset(self::$resources[$scopeId])) {
            self::$resources[$scopeId] = [];
        }
        
        self::$resources[$scopeId][] = $resource;
        
        
        
        // 定期清理过期资源
        self::$cleanupCounter++;
        if (self::$cleanupCounter >= self::CLEANUP_THRESHOLD) {
            self::cleanupExpired();
            self::$cleanupCounter = 0;
        }
    }
    
    /**
     * 从当前取消作用域注销资源
     * 
     * @param AsyncResource $resource 要注销的资源
     * @return bool 是否成功注销
     */
    public static function deregister(AsyncResource $resource): bool
    {
        $scope = CancellationScope::current();
        if (!$scope) {
            return false;
        }
        
        $scopeId = spl_object_id($scope);
        if (!isset(self::$resources[$scopeId])) {
            return false;
        }
        
        $key = array_search($resource, self::$resources[$scopeId], true);
        if ($key === false) {
            return false;
        }
        
        unset(self::$resources[$scopeId][$key]);
        self::$resources[$scopeId] = array_values(self::$resources[$scopeId]);
        
        return true;
    }
    
    /**
     * 清理指定取消作用域的所有资源
     * 
     * @param CancellationScope $scope 要清理的取消作用域
     * @return int 清理的资源数量
     */
    public static function cleanupScope(CancellationScope $scope): int
    {
        $scopeId = spl_object_id($scope);
        if (!isset(self::$resources[$scopeId])) {
            return 0;
        }
        
        $resourceCount = count(self::$resources[$scopeId]);
        
        // 关闭所有资源
        foreach (self::$resources[$scopeId] as $resource) {
            try {
                if (!$resource->isClosed()) {
                    
                    
                    $resource->close();
                }
            } catch (\Throwable $e) {
                // 记录错误，但继续清理其他资源
                error_log("Error closing resource: " . $e->getMessage());
            }
        }
        
        // 移除资源记录
        unset(self::$resources[$scopeId]);
        
        return $resourceCount;
    }
    
    /**
     * 清理过期资源（已关闭的资源）
     * 
     * @return int 清理的过期资源数量
     */
    public static function cleanupExpired(): int
    {
        $cleanedCount = 0;
        
        foreach (self::$resources as $scopeId => &$resources) {
            $remainingResources = [];
            
            foreach ($resources as $resource) {
                if ($resource->isClosed()) {
                    $cleanedCount++;
                } else {
                    $remainingResources[] = $resource;
                }
            }
            
            $resources = $remainingResources;
            
            // 如果作用域下没有资源了，移除整个作用域记录
            if (empty($resources)) {
                unset(self::$resources[$scopeId]);
            }
        }
        
        return $cleanedCount;
    }
    
    /**
     * 批量注册资源
     * 
     * @param AsyncResource ...$resources 要注册的资源列表
     * @return void
     */
    public static function registerBatch(AsyncResource ...$resources): void
    {
        foreach ($resources as $resource) {
            self::register($resource);
        }
    }
    
    /**
     * 获取指定作用域的资源数量
     * 
     * @param CancellationScope $scope 取消作用域
     * @return int 资源数量
     */
    public static function getResourceCount(CancellationScope $scope): int
    {
        $scopeId = spl_object_id($scope);
        return isset(self::$resources[$scopeId]) ? count(self::$resources[$scopeId]) : 0;
    }
    
    /**
     * 获取资源统计信息
     * 
     * @return array 资源统计信息
     */
    public static function getStats(): array
    {
        $totalResources = 0;
        $scopeCount = count(self::$resources);
        
        foreach (self::$resources as $resources) {
            $totalResources += count($resources);
        }
        
        return [
            'total_scopes' => $scopeCount,
            'total_resources' => $totalResources,
            'cleanup_threshold' => self::CLEANUP_THRESHOLD,
            'next_cleanup_at' => self::CLEANUP_THRESHOLD - self::$cleanupCounter,
        ];
    }
    
    /**
     * 检测资源泄漏
     * 
     * @return array 泄漏检测结果
     */
    public static function detectLeaks(): array
    {
        $leakedResources = [];
        
        // 遍历所有资源，检查是否有资源所属的作用域已经被销毁
        foreach (self::$resources as $scopeId => $resources) {
            // 这里可以添加更复杂的泄漏检测逻辑
            // 例如，检查作用域对象是否仍然存在
            
            if (!empty($resources)) {
                $leakedResources[$scopeId] = count($resources);
            }
        }
        
        return [
            'leaked_scopes' => count($leakedResources),
            'leaked_resources' => array_sum($leakedResources),
            'details' => $leakedResources,
        ];
    }
}