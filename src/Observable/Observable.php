<?php

namespace PfinalClub\Asyncio\Observable;

use PfinalClub\Asyncio\Observable\Events\TaskEvent;

/**
 * 简化的可观测性管理器 - 仅支持基本任务事件
 * 
 * @api-stable
 */
class Observable
{
    private static ?Observable $instance = null;
    private array $observers = [];
    private bool $enabled = false;
    
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
        if (!$this->enabled) {
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
     * 检查是否启用了可观测性
     * 
     * @return bool 是否启用
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
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