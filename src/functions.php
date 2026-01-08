<?php

namespace PfinalClub\Asyncio;

use Workerman\Timer;
use PfinalClub\Asyncio\Core\EventLoop;
use PfinalClub\Asyncio\Core\Task;

/**
 * 辅助函数 - 提供类似 Python asyncio 的 API
 * 基于 Fiber 实现
 */

/**
 * 创建并调度一个异步任务
 * 类似 asyncio.create_task()
 * 
 * @api-stable
 * @throws \RuntimeException 如果没有活动的 CancellationScope
 */
function create_task(callable $callback, string $name = ''): Task
{
    $task = EventLoop::getInstance()->createFiber($callback, $name);
    
    // 获取当前活动的取消作用域
    $scope = \PfinalClub\Asyncio\Concurrency\CancellationScope::current();
    if ($scope === null) {
        throw new \RuntimeException(
            "No active CancellationScope. Use CancellationScope::run() to create a scope for tasks."
        );
    }
    
    // 注册任务到当前作用域
    $scope->registerTask($task);
    
    return $task;
}

/**
 * 异步函数包装器（别名）
 * 创建新的 Fiber 任务
 * 
 * @deprecated Use create_task() instead
 * @api-experimental
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
 * @api-stable
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
 * @api-stable
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
 * @api-stable
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
 * @param GatherStrategy $strategy 收集策略，默认为 FAIL_FAST
 * @return array 所有任务的返回值数组
 * @api-stable
 */
function gather(Task ...$tasks): array
{
    // 从参数中提取策略，如果有的话
    $strategy = GatherStrategy::FAIL_FAST;
    if (!empty($tasks) && $tasks[count($tasks) - 1] instanceof GatherStrategy) {
        $strategy = array_pop($tasks);
    }
    
    return EventLoop::getInstance()->gather($tasks, $strategy);
}

/**
 * 等待任务完成，带超时
 * 类似 asyncio.wait_for()
 * 
 * 改进：
 * - 修复 Timer 资源泄漏问题
 * - 确保在所有情况下都清理 Timer
 * - 保留原始异常链
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
    $cleanupDone = false;
    
    // 封装清理逻辑，确保只执行一次
    $cleanup = function() use (&$timerId, &$cleanupDone) {
        if ($cleanupDone) {
            return;
        }
        $cleanupDone = true;
        
        if ($timerId !== null) {
            try {
                Timer::del($timerId);
            } catch (\Throwable $e) {
                // Timer 可能已经被删除或不存在
                error_log("Warning: Failed to delete timer {$timerId}: " . $e->getMessage());
            }
            $timerId = null;
        }
    };
    
    // 设置超时定时器
    $timerId = Timer::add($timeout, function () use ($task, &$timedOut, $cleanup) {
        $timedOut = true;
        $task->cancel();
        $cleanup();
    }, [], false);
    
    try {
        $result = await($task);
        $cleanup();  // 成功时清理
        return $result;
        
    } catch (TaskCancelledException $e) {
        $cleanup();  // 取消时清理
        
        if ($timedOut) {
            throw new TimeoutException(
                "Task timed out after {$timeout} seconds",
                0,
                $e  // 保留原始异常链
            );
        }
        throw $e;
        
    } catch (\Throwable $e) {
        $cleanup();  // 任何异常都清理
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

/**
 * 创建信号量
 * 
 * @param int $max 最大并发数
 * @return Semaphore
 */
function semaphore(int $max): Semaphore
{
    return new Semaphore($max);
}

/**
 * 设置协程上下文变量
 * 
 * @param string $key 键名
 * @param mixed $value 值
 */
function set_context(string $key, mixed $value): void
{
    \PfinalClub\Asyncio\Resource\Context::set($key, $value);
}

/**
 * 获取协程上下文变量
 * 
 * @param string $key 键名
 * @param mixed $default 默认值
 * @return mixed
 */
function get_context(string $key, mixed $default = null): mixed
{
    return \PfinalClub\Asyncio\Resource\Context::get($key, $default);
}

/**
 * 检查协程上下文变量是否存在
 * 
 * @param string $key 键名
 * @return bool
 */
function has_context(string $key): bool
{
    return \PfinalClub\Asyncio\Resource\Context::has($key);
}

/**
 * 删除协程上下文变量
 * 
 * @param string $key 键名
 */
function delete_context(string $key): void
{
    \PfinalClub\Asyncio\Resource\Context::delete($key);
}

/**
 * 获取所有协程上下文
 * 
 * @param bool $includeParent 是否包含父协程上下文
 * @return array
 */
function get_all_context(bool $includeParent = true): array
{
    return \PfinalClub\Asyncio\Resource\Context::getAll($includeParent);
}

/**
 * 清理当前协程的所有上下文
 */
function clear_context(): void
{
    \PfinalClub\Asyncio\Resource\Context::clear();
}
