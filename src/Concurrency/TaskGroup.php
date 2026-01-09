<?php

namespace PfinalClub\Asyncio\Concurrency;

use PfinalClub\Asyncio\Core\Task;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\await;

/**
 * 任务组 - 管理一组相关任务
 * 
 * @api-stable
 */
class TaskGroup
{
    private array $tasks = [];
    private bool $closed = false;
    private bool $cancelled = false;
    private ?CancellationScope $scope = null;
    
    /**
     * 构造函数
     * 
     * @param ?CancellationScope $scope 关联的取消作用域，如果为 null 则使用当前作用域
     */
    public function __construct(?CancellationScope $scope = null)
    {
        $this->scope = $scope ?? CancellationScope::current();
    }
    
    /**
     * 在任务组中生成一个新任务
     * 
     * @param callable $callback 要执行的回调函数
     * @param string $name 任务名称
     * @return Task 新创建的任务
     * @throws \RuntimeException 如果任务组已关闭
     */
    public function spawn(callable $callback, string $name = ''): Task
    {
        if ($this->closed) {
            throw new \RuntimeException("Cannot spawn tasks in a closed TaskGroup");
        }
        
        if ($this->cancelled) {
            throw new \RuntimeException("Cannot spawn tasks in a cancelled TaskGroup");
        }
        
        $task = create_task($callback, $name);
        $this->tasks[] = $task;
        
        // 注册到取消作用域
        if ($this->scope) {
            $this->scope->registerTask($task);
        }
        
        // 任务完成后自动从组中移除
        $task->addDoneCallback(function () use ($task) {
            $this->removeTask($task);
        });
        
        return $task;
    }
    
    /**
     * 等待所有任务完成
     * 
     * @return void
     */
    public function waitAll(): void
    {
        $this->closed = true;
        
        // 等待所有剩余任务完成
        while (!empty($this->tasks)) {
            $task = reset($this->tasks);
            await($task);
        }
    }
    
    /**
     * 取消所有任务
     * 
     * @return void
     */
    public function cancel(): void
    {
        $this->cancelled = true;
        $this->closed = true;
        
        foreach ($this->tasks as $task) {
            $task->cancel();
        }
        
        $this->tasks = [];
    }
    
    /**
     * 从任务组中移除任务
     * 
     * @param Task $task 要移除的任务
     * @return void
     */
    private function removeTask(Task $task): void
    {
        $key = array_search($task, $this->tasks, true);
        if ($key !== false) {
            unset($this->tasks[$key]);
            $this->tasks = array_values($this->tasks);
        }
        
        // 如果是取消作用域中的任务，也从作用域中移除
        if ($this->scope) {
            $this->scope->deregisterTask($task);
        }
    }
    
    /**
     * 获取当前任务组中的任务数量
     * 
     * @return int 任务数量
     */
    public function getTaskCount(): int
    {
        return count($this->tasks);
    }
    
    /**
     * 获取当前任务组中的所有任务
     * 
     * @return Task[] 任务数组
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
    
    /**
     * 检查任务组是否已关闭
     * 
     * @return bool 是否已关闭
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
    
    /**
     * 检查任务组是否已取消
     * 
     * @return bool 是否已取消
     */
    public function isCancelled(): bool
    {
        return $this->cancelled;
    }
    
    /**
     * 关闭任务组，不再接受新任务
     * 
     * @return void
     */
    public function close(): void
    {
        $this->closed = true;
    }
}