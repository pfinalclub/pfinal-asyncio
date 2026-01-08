<?php

namespace PfinalClub\Asyncio;

use Workerman\Timer;
use PfinalClub\Asyncio\Core\EventLoop;
use PfinalClub\Asyncio\Core\Task;
use PfinalClub\Asyncio\Concurrency\CancellationScope;
use PfinalClub\Asyncio\Concurrency\GatherStrategy;
use PfinalClub\Asyncio\GatherException;

/**
 * 核心异步函数 - 提供类似 Python asyncio 的 API
 * 基于 Fiber 实现
 */

/**
 * 创建并调度一个异步任务
 * 类似 asyncio.create_task()
 * 
 * 所有任务必须在一个 CancellationScope 中创建。
 * run() 函数会自动创建 Scope，所以通常不需要手动创建。
 * 
 * @api-stable
 * @param callable $callback 要执行的回调函数
 * @param string $name 任务名称（可选）
 * @return Task 创建的任务对象
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
 * 运行主协程直到完成
 * 类似 asyncio.run()
 * 
 * 自动创建 CancellationScope，确保所有任务都有作用域管理
 * 
 * @param callable $main 要运行的主函数
 * @api-stable
 */
function run(callable $main): mixed
{
    return \PfinalClub\Asyncio\Concurrency\CancellationScope::run(function() use ($main) {
        return EventLoop::getInstance()->run($main);
    });
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
 * 获取当前运行的事件循环
 * 类似 asyncio.get_event_loop()
 * 
 * @api-stable
 */
function get_event_loop(): EventLoop
{
    return EventLoop::getInstance();
}

/**
 * 创建信号量
 * 
 * @param int $max 最大并发数
 * @return Semaphore
 * @api-stable
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
 * @api-stable
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
 * @api-stable
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
 * @api-stable
 */
function has_context(string $key): bool
{
    return \PfinalClub\Asyncio\Resource\Context::has($key);
}

/**
 * 删除协程上下文变量
 * 
 * @param string $key 键名
 * @api-stable
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
 * @api-stable
 */
function get_all_context(bool $includeParent = true): array
{
    return \PfinalClub\Asyncio\Resource\Context::getAll($includeParent);
}

/**
 * 清理当前协程的所有上下文
 * 
 * @api-stable
 */
function clear_context(): void
{
    \PfinalClub\Asyncio\Resource\Context::clear();
}