<?php

namespace PfinalClub\Asyncio;

use Workerman\Timer;

/**
 * 辅助函数 - 模拟 Python asyncio 的 API
 */

/**
 * 创建并调度一个任务
 * 类似 asyncio.create_task()
 */
function create_task(\Generator $coroutine, string $name = ''): Task
{
    return EventLoop::getInstance()->createTask($coroutine, $name);
}

/**
 * 运行协程直到完成
 * 类似 asyncio.run()
 */
function run(\Generator $coroutine): mixed
{
    return EventLoop::getInstance()->run($coroutine);
}

/**
 * 异步睡眠
 * 类似 asyncio.sleep()
 */
function sleep(float $seconds): Sleep
{
    return new Sleep($seconds);
}

/**
 * 并发运行多个任务并等待它们全部完成
 * 类似 asyncio.gather()
 * 
 * @param Task ...$tasks
 * @return \Generator
 */
function gather(Task ...$tasks): \Generator
{
    return yield $tasks;
}

/**
 * 等待任务完成，带超时
 * 类似 asyncio.wait_for()
 */
function wait_for(\Generator|Task $awaitable, float $timeout): \Generator
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
        $result = yield $task;
        
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
function wait_first_completed(Task ...$tasks): \Generator
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
    
    return yield $future;
}

/**
 * 等待所有任务完成
 * 类似 asyncio.wait() with ALL_COMPLETED
 */
function wait_all_completed(Task ...$tasks): \Generator
{
    if (empty($tasks)) {
        return [[], []];
    }
    
    $result = yield $tasks;
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
 * 包装阻塞式函数为异步函数
 * 使用 yield 来暂停执行
 */
function async_wrap(callable $func): \Closure
{
    return function (...$args) use ($func) {
        $future = new Future();
        
        call_soon(function () use ($func, $args, $future) {
            try {
                $result = $func(...$args);
                $future->setResult($result);
            } catch (\Throwable $e) {
                $future->setException($e);
            }
        });
        
        return yield $future;
    };
}

/**
 * 创建协程包装器
 */
function coroutine(callable $func): \Closure
{
    return function (...$args) use ($func): \Generator {
        return yield from $func(...$args);
    };
}

/**
 * 确保参数是一个任务
 */
function ensure_task(\Generator|Task $awaitable): Task
{
    if ($awaitable instanceof Task) {
        return $awaitable;
    }
    return create_task($awaitable);
}

/**
 * 屏蔽任务取消
 * 类似 asyncio.shield()
 */
function shield(Task $task): \Generator
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
        return yield $future;
    } catch (TaskCancelledException $e) {
        // 屏蔽取消，但任务继续运行
        return yield $future;
    }
}

/**
 * 等待协程完成（类似 Python 的 await）
 * 这是一个语法糖，让代码更接近 Python asyncio
 * 
 * @param \Generator|Task $awaitable 要等待的协程或任务
 * @return \Generator
 */
function await_coro(\Generator|Task $awaitable): \Generator
{
    if ($awaitable instanceof Task) {
        return yield $awaitable;
    }
    
    // 如果是 Generator，直接 yield from
    return yield from $awaitable;
}

