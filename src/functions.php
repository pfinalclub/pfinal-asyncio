<?php

namespace PfinalClub\Asyncio;

use Workerman\Timer;

/**
 * 辅助函数 - 提供类似 Python asyncio 的 API
 * 基于 Fiber 实现
 */

/**
 * 创建并调度一个异步任务
 * 类似 asyncio.create_task()
 */
function create_task(callable $callback, string $name = ''): Task
{
    return EventLoop::getInstance()->createFiber($callback, $name);
}

/**
 * 异步函数包装器（别名）
 * 创建新的 Fiber 任务
 */
function async(callable $callback, string $name = ''): Task
{
    return create_task($callback, $name);
}

/**
 * 运行主协程直到完成
 * 类似 asyncio.run()
 * 
 * @param callable $main 要运行的主函数
 */
function run(callable $main): mixed
{
    return EventLoop::getInstance()->run($main);
}

/**
 * 异步睡眠
 * 类似 asyncio.sleep()
 * 
 * 注意：此函数必须在 Fiber 上下文中调用
 */
function sleep(float $seconds): void
{
    EventLoop::getInstance()->sleep($seconds);
}

/**
 * 等待任务完成
 * 类似 await 关键字
 * 
 * @param Task $task 要等待的任务
 * @return mixed 任务的返回值
 */
function await(Task $task): mixed
{
    return EventLoop::getInstance()->await($task);
}

/**
 * 并发运行多个任务并等待它们全部完成
 * 类似 asyncio.gather()
 * 
 * @param Task ...$tasks 要等待的任务列表
 * @return array 所有任务的返回值数组
 */
function gather(Task ...$tasks): array
{
    return EventLoop::getInstance()->gather($tasks);
}

/**
 * 等待任务完成，带超时
 * 类似 asyncio.wait_for()
 * 
 * @param callable|Task $awaitable 可调用对象或任务
 * @param float $timeout 超时时间（秒）
 * @return mixed 任务的返回值
 * @throws TimeoutException 如果超时
 */
function wait_for(callable|Task $awaitable, float $timeout): mixed
{
    $task = $awaitable instanceof Task ? $awaitable : create_task($awaitable);
    $timedOut = false;
    $timerId = null;
    
    // 设置超时定时器
    $timerId = Timer::add($timeout, function () use ($task, &$timedOut, &$timerId) {
        $timedOut = true;
        $task->cancel();
        if ($timerId) {
            Timer::del($timerId);
        }
    }, [], false);
    
    try {
        $result = await($task);
        
        // 取消超时定时器
        if ($timerId && !$timedOut) {
            Timer::del($timerId);
        }
        
        return $result;
    } catch (TaskCancelledException $e) {
        if ($timedOut) {
            throw new TimeoutException("Task timed out after {$timeout} seconds");
        }
        throw $e;
    }
}

/**
 * 等待第一个完成的任务
 * 类似 asyncio.wait() with FIRST_COMPLETED
 */
function wait_first_completed(Task ...$tasks): array
{
    if (empty($tasks)) {
        return [[], $tasks];
    }
    
    $future = new Future();
    $completed = [];
    
    foreach ($tasks as $index => $task) {
        $task->addDoneCallback(function () use ($task, $index, &$completed, $future, $tasks) {
            if (!$future->isDone()) {
                $completed[] = $task;
                $pending = array_filter($tasks, fn($t) => !$t->isDone());
                $future->setResult([$completed, array_values($pending)]);
            }
        });
    }
    
    return await_future($future);
}

/**
 * 等待所有任务完成
 * 类似 asyncio.wait() with ALL_COMPLETED
 */
function wait_all_completed(Task ...$tasks): array
{
    if (empty($tasks)) {
        return [[], []];
    }
    
    gather(...$tasks);
    $done = array_filter($tasks, fn($t) => $t->isDone());
    $pending = array_filter($tasks, fn($t) => !$t->isDone());
    
    return [array_values($done), array_values($pending)];
}

/**
 * 创建一个 Future 对象
 */
function create_future(): Future
{
    return new Future();
}

/**
 * 等待 Future 完成
 * 直接在回调中恢复 Fiber，无延迟
 */
function await_future(Future $future): mixed
{
    $currentFiber = \Fiber::getCurrent();
    
    if (!$currentFiber) {
        throw new \RuntimeException("await_future() can only be called within a Fiber");
    }
    
    if ($future->isDone()) {
        return $future->getResult();
    }
    
    // 等待 Future 完成，立即恢复
    $future->addDoneCallback(function () use ($currentFiber, $future) {
        if ($currentFiber->isSuspended()) {
            try {
                if ($future->hasException()) {
                    $currentFiber->throw($future->getException());
                } else {
                    $currentFiber->resume($future->getResult());
                }
            } catch (\Throwable $e) {
                error_log("Error resuming fiber in await_future: " . $e->getMessage());
            }
        }
    });
    
    return \Fiber::suspend();
}

/**
 * 获取当前运行的事件循环
 * 类似 asyncio.get_event_loop()
 */
function get_event_loop(): EventLoop
{
    return EventLoop::getInstance();
}

/**
 * 在事件循环中调度回调
 */
function call_soon(callable $callback, ...$args): void
{
    Timer::add(0.001, function () use ($callback, $args) {
        $callback(...$args);
    }, [], false);
}

/**
 * 延迟调度回调
 */
function call_later(float $delay, callable $callback, ...$args): int
{
    return Timer::add($delay, function () use ($callback, $args) {
        $callback(...$args);
    }, [], false);
}

/**
 * 屏蔽任务取消
 * 类似 asyncio.shield()
 */
function shield(Task $task): mixed
{
    $future = new Future();
    
    $task->addDoneCallback(function () use ($task, $future) {
        if ($task->hasException()) {
            $future->setException($task->getException());
        } else {
            $future->setResult($task->getResult());
        }
    });
    
    try {
        return await_future($future);
    } catch (TaskCancelledException $e) {
        // 屏蔽取消，但任务继续运行
        return await_future($future);
    }
}

/**
 * 生成一个新的 Fiber 任务（spawn 别名）
 */
function spawn(callable $callback, string $name = ''): Task
{
    return create_task($callback, $name);
}
