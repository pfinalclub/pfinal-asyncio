<?php

namespace PfinalClub\Asyncio\Concurrency;

use PfinalClub\Asyncio\Task;

/**
 * 取消作用域 - 支持结构化并发取消
 * 
 * @api-stable
 */
class CancellationScope
{
    private static ?CancellationScope $current = null;
    private bool $cancelled = false;
    private array $tasks = [];
    private ?CancellationScope $parent = null;
    
    /**
     * 在作用域内运行回调
     * 
     * @param callable $callback 要运行的回调函数
     * @return mixed 回调函数的返回值
     */
    public static function run(callable $callback): mixed
    {
        $scope = new self();
        $scope->parent = self::$current;
        self::$current = $scope;
        
        try {
            return $callback($scope);
        } finally {
            $scope->cancel();
            self::$current = $scope->parent;
        }
    }
    
    /**
     * 获取当前活动的取消作用域
     * 
     * @return self|null 当前活动的取消作用域，如果没有则返回 null
     */
    public static function current(): ?self
    {
        return self::$current;
    }
    
    /**
     * 取消作用域
     * 
     * @return void
     */
    public function cancel(): void
    {
        if ($this->cancelled) {
            return;
        }
        
        $this->cancelled = true;
        
        // 取消所有注册的任务
        foreach ($this->tasks as $task) {
            $task->cancel();
        }
        
        $this->tasks = [];
        
        // 清理所有注册的资源
        \PfinalClub\Asyncio\Resource\AsyncResourceManager::cleanupScope($this);
    }
    
    /**
     * 检查作用域是否已取消
     * 
     * @return bool 是否已取消
     */
    public function isCancelled(): bool
    {
        return $this->cancelled || ($this->parent?->isCancelled() ?? false);
    }
    
    /**
     * 注册任务到当前作用域
     * 
     * @param Task $task 要注册的任务
     * @return void
     */
    public function registerTask(Task $task): void
    {
        $task->setScope($this);
        $this->tasks[] = $task;
    }
    
    /**
     * 从作用域中注销任务
     * 
     * @param Task $task 要注销的任务
     * @return void
     */
    public function deregisterTask(Task $task): void
    {
        $key = array_search($task, $this->tasks, true);
        if ($key !== false) {
            unset($this->tasks[$key]);
            $this->tasks = array_values($this->tasks);
        }
    }
    
    /**
     * 获取当前作用域中的所有任务
     * 
     * @return Task[] 任务数组
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}