<?php

namespace PfinalClub\Asyncio;

use Workerman\Timer;
use Workerman\Worker;

/**
 * 事件循环 - asyncio 的核心
 * 负责调度和执行异步任务
 */
class EventLoop
{
    private static ?EventLoop $instance = null;
    private array $tasks = [];
    private array $callbacks = [];
    private bool $running = false;
    private int $taskIdCounter = 0;
    private array $timers = [];
    private array $callbackQueue = [];
    private bool $lightweightMode = false;
    
    private function __construct()
    {
    }
    
    /**
     * 获取事件循环单例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 创建新任务
     */
    public function createTask(\Generator $coroutine, string $name = ''): Task
    {
        $taskId = ++$this->taskIdCounter;
        $task = new Task($coroutine, $taskId, $name ?: "Task-{$taskId}");
        $this->tasks[$taskId] = $task;
        
        // 立即调度任务
        $this->scheduleTask($task);
        
        return $task;
    }
    
    /**
     * 调度任务执行
     */
    private function scheduleTask(Task $task): void
    {
        if ($task->isDone()) {
            return;
        }
        
        Timer::add(0.001, function () use ($task) {
            if ($task->isDone()) {
                return;
            }
            
            try {
                $this->step($task);
            } catch (\Throwable $e) {
                $task->setException($e);
            }
        }, [], false);
    }
    
    /**
     * 执行任务的一步
     */
    private function step(Task $task): void
    {
        $coroutine = $task->getCoroutine();
        
        try {
            if (!$coroutine->valid()) {
                $task->setResult($coroutine->getReturn());
                unset($this->tasks[$task->getId()]);
                return;
            }
            
            $yielded = $coroutine->current();
            
            // 处理不同类型的 yield 值
            if ($yielded instanceof Task) {
                // 等待另一个任务完成
                $yielded->addDoneCallback(function () use ($task, $coroutine, $yielded) {
                    try {
                        if ($yielded->hasException()) {
                            $coroutine->throw($yielded->getException());
                        } else {
                            $coroutine->send($yielded->getResult());
                        }
                        $this->scheduleTask($task);
                    } catch (\Throwable $e) {
                        $task->setException($e);
                    }
                });
            } elseif ($yielded instanceof Future) {
                // 等待 Future 完成
                $yielded->addDoneCallback(function () use ($task, $coroutine, $yielded) {
                    try {
                        if ($yielded->hasException()) {
                            $coroutine->throw($yielded->getException());
                        } else {
                            $coroutine->send($yielded->getResult());
                        }
                        $this->scheduleTask($task);
                    } catch (\Throwable $e) {
                        $task->setException($e);
                    }
                });
            } elseif ($yielded instanceof Sleep) {
                // 延迟执行
                if ($this->lightweightMode) {
                    // 轻量级模式：立即完成（不真正等待）
                    $this->callSoon(function () use ($task, $coroutine) {
                        try {
                            $coroutine->send(null);
                            $this->scheduleTask($task);
                        } catch (\Throwable $e) {
                            $task->setException($e);
                        }
                    });
                } else {
                    // 正常模式：使用 Workerman Timer
                    $delay = max($yielded->getDelay(), 0.001); // 最小 1ms
                    Timer::add($delay, function () use ($task, $coroutine) {
                        try {
                            $coroutine->send(null);
                            $this->scheduleTask($task);
                        } catch (\Throwable $e) {
                            $task->setException($e);
                        }
                    }, [], false);
                }
            } elseif (is_array($yielded)) {
                // gather - 等待多个任务
                $this->handleGather($yielded, $task, $coroutine);
            } else {
                // 直接继续执行
                $coroutine->send($yielded);
                $this->scheduleTask($task);
            }
        } catch (\Throwable $e) {
            $task->setException($e);
            unset($this->tasks[$task->getId()]);
        }
    }
    
    /**
     * 处理 gather - 等待多个任务完成
     */
    private function handleGather(array $tasks, Task $parentTask, \Generator $coroutine): void
    {
        $results = [];
        $remaining = count($tasks);
        $hasError = false;
        $error = null;
        
        if ($remaining === 0) {
            $coroutine->send([]);
            $this->scheduleTask($parentTask);
            return;
        }
        
        foreach ($tasks as $index => $task) {
            if ($task instanceof Task) {
                $task->addDoneCallback(function () use ($task, $index, &$results, &$remaining, &$hasError, &$error, $parentTask, $coroutine) {
                    if ($hasError) {
                        return;
                    }
                    
                    try {
                        if ($task->hasException()) {
                            $hasError = true;
                            $error = $task->getException();
                            $coroutine->throw($error);
                        } else {
                            $results[$index] = $task->getResult();
                        }
                    } catch (\Throwable $e) {
                        $hasError = true;
                        $error = $e;
                        $parentTask->setException($e);
                        return;
                    }
                    
                    $remaining--;
                    if ($remaining === 0 && !$hasError) {
                        ksort($results);
                        $coroutine->send(array_values($results));
                        $this->scheduleTask($parentTask);
                    }
                });
            }
        }
    }
    
    /**
     * 运行协程直到完成
     */
    public function run(\Generator $coroutine, bool $useWorkerman = false): mixed
    {
        $task = $this->createTask($coroutine);
        
        // 只在需要时启动 Workerman（避免基准测试时的问题）
        if ($useWorkerman && !Worker::$globalEvent) {
            $this->lightweightMode = false;
            Worker::runAll();
        } else {
            // 轻量级模式：使用简单的循环来完成任务
            $this->lightweightMode = true;
            $this->runSimpleLoop($task);
        }
        
        return $task->getResult();
    }
    
    /**
     * 检查是否在轻量级模式下
     */
    public function isLightweightMode(): bool
    {
        return $this->lightweightMode;
    }
    
    /**
     * 立即调度回调
     */
    public function callSoon(callable $callback): void
    {
        $this->callbackQueue[] = $callback;
    }
    
    /**
     * 简单的事件循环（用于测试和基准测试）
     */
    private function runSimpleLoop(Task $task): void
    {
        $maxIterations = 1000000; // 防止无限循环，提高到 100 万次
        $iterations = 0;
        $noWorkCount = 0;
        
        while (!$task->isDone() && $iterations < $maxIterations) {
            // 先处理回调队列
            while (!empty($this->callbackQueue)) {
                $callback = array_shift($this->callbackQueue);
                try {
                    $callback();
                } catch (\Throwable $e) {
                    // 忽略回调错误
                }
            }
            
            // 处理所有待处理的任务
            $hasWork = false;
            
            foreach ($this->tasks as $t) {
                if (!$t->isDone()) {
                    try {
                        $this->step($t);
                        $hasWork = true;
                    } catch (\Throwable $e) {
                        $t->setException($e);
                    }
                }
            }
            
            // 如果连续多次没有工作，跳出（可能卡住了）
            if (!$hasWork && empty($this->callbackQueue)) {
                $noWorkCount++;
                if ($noWorkCount > 100) {
                    break;
                }
            } else {
                $noWorkCount = 0;
            }
            
            $iterations++;
        }
        
        if ($iterations >= $maxIterations && !$task->isDone()) {
            throw new \RuntimeException("Event loop exceeded maximum iterations (possible infinite loop). Completed $iterations iterations.");
        }
    }
    
    /**
     * 运行直到所有任务完成
     */
    public function runUntilComplete(Task $task): mixed
    {
        $completed = false;
        $result = null;
        $exception = null;
        
        $task->addDoneCallback(function () use ($task, &$completed, &$result, &$exception) {
            $completed = true;
            if ($task->hasException()) {
                $exception = $task->getException();
            } else {
                $result = $task->getResult();
            }
            // 停止所有 worker
            Timer::delAll();
        });
        
        // 启动 Workerman 事件循环
        if (!Worker::$globalEvent) {
            Worker::runAll();
        }
        
        if ($exception) {
            throw $exception;
        }
        
        return $result;
    }
    
    /**
     * 添加定时器
     */
    public function addTimer(float $interval, callable $callback, bool $persistent = true): int
    {
        $timerId = Timer::add($interval, $callback, [], $persistent);
        $this->timers[$timerId] = $timerId;
        return $timerId;
    }
    
    /**
     * 删除定时器
     */
    public function delTimer(int $timerId): void
    {
        Timer::del($timerId);
        unset($this->timers[$timerId]);
    }
    
    /**
     * 停止事件循环
     */
    public function stop(): void
    {
        $this->running = false;
        Worker::stopAll();
    }
    
    /**
     * 获取所有活跃任务
     */
    public function getActiveTasks(): array
    {
        return $this->tasks;
    }
}

