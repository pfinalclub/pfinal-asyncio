<?php

namespace PfinalClub\Asyncio\Observable;

use PfinalClub\Asyncio\Observable\Events\TaskEvent;
use PfinalClub\Asyncio\Observable\Events\ScopeEvent;
use PfinalClub\Asyncio\Observable\Events\ResourceEvent;
use PfinalClub\Asyncio\Observable\Events\RuntimeStateEvent;

/**
 * 可观测性管理器 - 管理观察者和发送事件
 * 
 * @api-stable
 */
class Observable
{
    private static ?Observable $instance = null;
    private array $observers = [];
    private bool $enabled = false;
    private array $eventFilters = [
        TaskEvent::class => [],
        ScopeEvent::class => [],
        ResourceEvent::class => [],
        RuntimeStateEvent::class => [],
    ];
    
    private function __construct()
    {
    }
    
    /**
     * 获取单例实例
     * 
     * @return self 单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 注册观察者
     * 
     * @param Observer $observer 要注册的观察者
     * @return void
     */
    public function registerObserver(Observer $observer): void
    {
        $this->observers[] = $observer;
        $this->enabled = true;
    }
    
    /**
     * 注销观察者
     * 
     * @param Observer $observer 要注销的观察者
     * @return bool 是否成功注销
     */
    public function unregisterObserver(Observer $observer): bool
    {
        $key = array_search($observer, $this->observers, true);
        if ($key === false) {
            return false;
        }
        
        unset($this->observers[$key]);
        $this->observers = array_values($this->observers);
        $this->enabled = !empty($this->observers);
        
        return true;
    }
    
    /**
     * 发送任务事件
     * 
     * @param TaskEvent $event 任务事件
     * @return void
     */
    public function emitTaskEvent(TaskEvent $event): void
    {
        if (!$this->enabled || !$this->shouldEmitEvent(TaskEvent::class, $event)) {
            return;
        }
        
        foreach ($this->observers as $observer) {
            try {
                $observer->onTaskEvent($event);
            } catch (\Throwable $e) {
                // 记录错误，但不影响其他观察者
                error_log("Error in observer onTaskEvent: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 发送作用域事件
     * 
     * @param ScopeEvent $event 作用域事件
     * @return void
     */
    public function emitScopeEvent(ScopeEvent $event): void
    {
        if (!$this->enabled || !$this->shouldEmitEvent(ScopeEvent::class, $event)) {
            return;
        }
        
        foreach ($this->observers as $observer) {
            try {
                $observer->onScopeEvent($event);
            } catch (\Throwable $e) {
                // 记录错误，但不影响其他观察者
                error_log("Error in observer onScopeEvent: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 发送资源事件
     * 
     * @param ResourceEvent $event 资源事件
     * @return void
     */
    public function emitResourceEvent(ResourceEvent $event): void
    {
        if (!$this->enabled || !$this->shouldEmitEvent(ResourceEvent::class, $event)) {
            return;
        }
        
        foreach ($this->observers as $observer) {
            try {
                $observer->onResourceEvent($event);
            } catch (\Throwable $e) {
                // 记录错误，但不影响其他观察者
                error_log("Error in observer onResourceEvent: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 发送运行时状态事件
     * 
     * @param RuntimeStateEvent $event 运行时状态事件
     * @return void
     */
    public function emitRuntimeStateEvent(RuntimeStateEvent $event): void
    {
        if (!$this->enabled || !$this->shouldEmitEvent(RuntimeStateEvent::class, $event)) {
            return;
        }
        
        foreach ($this->observers as $observer) {
            try {
                $observer->onRuntimeStateEvent($event);
            } catch (\Throwable $e) {
                // 记录错误，但不影响其他观察者
                error_log("Error in observer onRuntimeStateEvent: " . $e->getMessage());
            }
        }
    }
    
    /**
     * 检查是否应该发送事件
     * 
     * @param string $eventClass 事件类名
     * @param object $event 事件对象
     * @return bool 是否应该发送
     */
    private function shouldEmitEvent(string $eventClass, object $event): bool
    {
        $filters = $this->eventFilters[$eventClass] ?? [];
        
        foreach ($filters as $filter) {
            if (!$filter($event)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 添加事件过滤器
     * 
     * @param string $eventClass 事件类名
     * @param callable $filter 过滤函数，返回 bool
     * @return void
     */
    public function addEventFilter(string $eventClass, callable $filter): void
    {
        $this->eventFilters[$eventClass][] = $filter;
    }
    
    /**
     * 移除事件过滤器
     * 
     * @param string $eventClass 事件类名
     * @param callable $filter 要移除的过滤函数
     * @return bool 是否成功移除
     */
    public function removeEventFilter(string $eventClass, callable $filter): bool
    {
        if (!isset($this->eventFilters[$eventClass])) {
            return false;
        }
        
        $key = array_search($filter, $this->eventFilters[$eventClass], true);
        if ($key === false) {
            return false;
        }
        
        unset($this->eventFilters[$eventClass][$key]);
        $this->eventFilters[$eventClass] = array_values($this->eventFilters[$eventClass]);
        
        return true;
    }
    
    /**
     * 清理所有事件过滤器
     * 
     * @param string|null $eventClass 事件类名，为 null 则清理所有
     * @return void
     */
    public function clearEventFilters(?string $eventClass = null): void
    {
        if ($eventClass === null) {
            $this->eventFilters = [
                TaskEvent::class => [],
                ScopeEvent::class => [],
                ResourceEvent::class => [],
                RuntimeStateEvent::class => [],
            ];
        } else {
            $this->eventFilters[$eventClass] = [];
        }
    }
    
    /**
     * 获取观察者数量
     * 
     * @return int 观察者数量
     */
    public function getObserverCount(): int
    {
        return count($this->observers);
    }
    
    /**
     * 检查是否启用了可观测性
     * 
     * @return bool 是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * 关闭可观测性
     * 
     * @return void
     */
    public function disable(): void
    {
        $this->enabled = false;
    }
    
    /**
     * 启用可观测性
     * 
     * @return void
     */
    public function enable(): void
    {
        $this->enabled = !empty($this->observers);
    }
    
    /**
     * 清理所有观察者
     * 
     * @return void
     */
    public function clearObservers(): void
    {
        $this->observers = [];
        $this->enabled = false;
    }
}