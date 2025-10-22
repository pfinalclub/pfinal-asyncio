<?php

namespace PfinalClub\Asyncio;

/**
 * 信号量 - 用于限制并发数量
 * 
 * 使用场景：
 * - 限制并发 HTTP 请求数量
 * - 限制数据库连接数
 * - 控制资源访问并发度
 * 
 * @example
 * ```php
 * $sem = new Semaphore(10);  // 最多10个并发
 * 
 * run(function() use ($sem) {
 *     $tasks = [];
 *     for ($i = 0; $i < 100; $i++) {
 *         $tasks[] = create_task(function() use ($sem, $i) {
 *             $sem->acquire();  // 获取许可
 *             try {
 *                 // 执行操作
 *                 return doWork($i);
 *             } finally {
 *                 $sem->release();  // 释放许可
 *             }
 *         });
 *     }
 *     gather(...$tasks);
 * });
 * ```
 */
class Semaphore
{
    private int $count;
    private int $max;
    private array $waiting = [];
    
    /**
     * 创建信号量
     * 
     * @param int $max 最大并发数
     */
    public function __construct(int $max)
    {
        if ($max <= 0) {
            throw new \InvalidArgumentException("Semaphore max value must be positive, got: {$max}");
        }
        
        $this->max = $max;
        $this->count = $max;
    }
    
    /**
     * 获取许可
     * 
     * 如果当前有可用许可，立即返回
     * 否则会暂停当前 Fiber，直到有许可可用
     * 
     * @throws \RuntimeException 如果不在 Fiber 上下文中调用
     */
    public function acquire(): void
    {
        // 如果有可用许可，直接获取
        if ($this->count > 0) {
            $this->count--;
            return;
        }
        
        // 没有可用许可，需要等待
        $currentFiber = \Fiber::getCurrent();
        
        if (!$currentFiber) {
            throw new \RuntimeException("Semaphore::acquire() can only be called within a Fiber context");
        }
        
        // 创建 Future 并加入等待队列
        $future = new Future();
        $this->waiting[] = [
            'future' => $future,
            'fiber' => $currentFiber,
        ];
        
        // 等待被唤醒
        \PfinalClub\Asyncio\await_future($future);
        
        // 被唤醒后，减少计数
        $this->count--;
    }
    
    /**
     * 释放许可
     * 
     * 如果有等待的 Fiber，会唤醒第一个
     */
    public function release(): void
    {
        // 检查是否有等待的 Fiber
        if (!empty($this->waiting)) {
            // 唤醒第一个等待的 Fiber
            $waiting = array_shift($this->waiting);
            $waiting['future']->setResult(true);
        } else {
            // 没有等待者，增加可用计数
            if ($this->count < $this->max) {
                $this->count++;
            }
        }
    }
    
    /**
     * 获取当前可用许可数
     */
    public function getAvailable(): int
    {
        return $this->count;
    }
    
    /**
     * 获取等待队列长度
     */
    public function getWaitingCount(): int
    {
        return count($this->waiting);
    }
    
    /**
     * 获取最大许可数
     */
    public function getMax(): int
    {
        return $this->max;
    }
    
    /**
     * 使用上下文管理器模式执行操作
     * 
     * 自动获取和释放许可
     * 
     * @param callable $callback 要执行的操作
     * @return mixed 操作的返回值
     */
    public function with(callable $callback): mixed
    {
        $this->acquire();
        try {
            return $callback();
        } finally {
            $this->release();
        }
    }
    
    /**
     * 获取统计信息
     */
    public function getStats(): array
    {
        return [
            'max' => $this->max,
            'available' => $this->count,
            'in_use' => $this->max - $this->count,
            'waiting' => count($this->waiting),
        ];
    }
}

