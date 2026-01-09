# pfinal-asyncio API 文档

## 目录

1. [核心概念](#核心概念)
2. [事件循环 API](#事件循环-api)
3. [任务管理 API](#任务管理-api)
4. [结构化并发 API](#结构化并发-api)
5. [资源管理 API](#资源管理-api)
6. [可观测性 API](#可观测性-api)
7. [核心函数 API](#核心函数-api)
8. [异常处理](#异常处理)

## 核心概念

### 事件循环
事件循环是异步运行时的核心，负责调度和执行异步任务。事件循环基于 Workerman 实现，支持多种事件循环后端（Ev、Event、Select）。

### 任务
任务是对 Fiber 的封装，管理异步操作的生命周期。每个任务都有明确的状态，由 TaskState 枚举管理。

### 结构化并发
结构化并发是一种并发编程范式，确保并发操作的可预测性和可维护性。pfinal-asyncio 提供了 CancellationScope、TaskGroup 和 gather 策略来支持结构化并发。

### 资源管理
资源管理负责管理异步资源的生命周期，确保资源在不再使用时被正确释放。

### 可观测性
可观测性允许监控和调试异步运行时，提供事件发布订阅机制。

## 事件循环 API

### EventLoopInterface

EventLoopInterface 是事件循环的稳定接口，定义了核心 API。

```php
interface EventLoopInterface {
    public static function getInstance(): self;
    public function run(callable $main): mixed;
    public function addTimer(float $interval, callable $callback, bool $persistent = true): int;
    public function delTimer(int $timerId): void;
    public function stop(): void;
    public static function getEventLoopType(): ?string;
}
```

#### 方法说明

##### getInstance()
获取事件循环单例实例。

```php
$eventLoop = EventLoop::getInstance();
```

##### run(callable $main)
运行主协程，执行异步任务。

```php
$result = $eventLoop->run(function() {
    return 'completed';
});
```

##### addTimer(float $interval, callable $callback, bool $persistent = true)
添加定时器，定期执行回调函数。

```php
$timerId = $eventLoop->addTimer(1.0, function() {
    echo 'Timer executed';
}, false); // false 表示只执行一次
```

##### delTimer(int $timerId)
删除定时器。

```php
$eventLoop->delTimer($timerId);
```

##### stop()
停止事件循环。

```php
$eventLoop->stop();
```

##### getEventLoopType()
获取当前使用的事件循环类型。

```php
$type = EventLoop::getEventLoopType();
// 返回 'Ev'、'Event' 或 'Select'
```

### EventLoop

EventLoop 是 EventLoopInterface 的实现，提供了高性能的事件循环。

#### 额外方法

##### getScheduler()
获取优先级调度器实例。

```php
$scheduler = $eventLoop->getScheduler();
```

##### schedule(callable $callback, int $priority = PriorityScheduler::PRIORITY_WORK, string $name = '')
调度任务。

```php
$task = $eventLoop->schedule(function() {
    return 'task-result';
}, PriorityScheduler::PRIORITY_CONTROL, 'control-task');
```

## 任务管理 API

### Task

Task 是对 Fiber 的封装，管理异步操作的生命周期。

#### 属性

- `id`: 任务 ID
- `name`: 任务名称
- `state`: 任务状态（TaskState 枚举）
- `createdAt`: 任务创建时间
- `startedAt`: 任务开始时间
- `completedAt`: 任务完成时间

#### 方法

##### getId(): int
获取任务 ID。

##### getName(): string
获取任务名称。

##### getCallable(): callable
获取任务的回调函数。

##### isDone(): bool
检查任务是否已完成。

##### isRunning(): bool
检查任务是否正在运行。

##### isCancelled(): bool
检查任务是否已取消。

##### getResult(): mixed
获取任务结果。如果任务失败，会抛出异常。

##### getException(): ?\Throwable
获取任务异常。如果任务成功，返回 null。

##### cancel(): void
取消任务。

##### addDoneCallback(callable $callback): void
添加任务完成回调。

### TaskState

TaskState 是任务状态的枚举，定义了任务的生命周期状态。

```php
enum TaskState: string {
    case PENDING = 'pending';    // 任务已创建，但尚未开始
    case RUNNING = 'running';    // 任务正在运行
    case COMPLETED = 'completed'; // 任务已成功完成
    case FAILED = 'failed';       // 任务执行失败
    case CANCELLED = 'cancelled'; // 任务被取消
}
```

#### 方法

##### isTerminal(): bool
检查状态是否为终止状态（COMPLETED、FAILED、CANCELLED）。

##### canTransitionTo(TaskState $target): bool
检查是否可以从当前状态转换到目标状态。

## 结构化并发 API

### CancellationScope

CancellationScope 管理任务的取消和资源清理。

#### 方法

##### run(callable $callback): mixed
运行作用域，执行回调函数。

```php
$result = CancellationScope::run(function() {
    // 在作用域内创建的任务会被自动管理
    $task = create_task(function() {
        return 'task-result';
    });
    return $task->getResult();
});
```

##### current(): ?CancellationScope
获取当前活动的取消作用域。

```php
$scope = CancellationScope::current();
```

##### cancel(): void
取消作用域，终止所有关联的任务。

```php
$scope->cancel();
```

##### isCancelled(): bool
检查作用域是否已取消。

```php
$isCancelled = $scope->isCancelled();
```

##### registerTask(Task $task): void
注册任务到作用域。

```php
$scope->registerTask($task);
```

### TaskGroup

TaskGroup 管理一组相关的任务。

#### 方法

##### __construct()
创建任务组。

```php
$group = new TaskGroup();
```

##### addTask(Task $task): void
添加任务到任务组。

```php
$group->addTask($task);
```

##### getRunningTaskCount(): int
获取正在运行的任务数量。

```php
$count = $group->getRunningTaskCount();
```

##### waitAll(): array
等待所有任务完成。

```php
$results = $group->waitAll();
```

##### cancel(): void
取消所有任务。

```php
$group->cancel();
```

### GatherStrategy

GatherStrategy 定义了 gather 函数的行为。

```php
enum GatherStrategy {
    case FAIL_FAST;   // 一旦有任务失败，立即取消所有其他任务
    case WAIT_ALL;    // 等待所有任务完成，无论成功或失败
    case RETURN_PARTIAL; // 返回已成功的结果
}
```

## 资源管理 API

### AsyncResource

AsyncResource 是异步资源的接口，定义了资源的生命周期管理方法。

#### 方法

##### close(): void
关闭资源。

##### isClosed(): bool
检查资源是否已关闭。

##### onCancellation(): void
处理资源取消。

### AsyncResourceManager

AsyncResourceManager 管理异步资源的生命周期。

#### 方法

##### register(AsyncResource $resource): void
注册资源到当前取消作用域。

```php
AsyncResourceManager::register($resource);
```

##### deregister(AsyncResource $resource): bool
从当前取消作用域注销资源。

```php
AsyncResourceManager::deregister($resource);
```

##### cleanupScope(CancellationScope $scope): int
清理指定取消作用域的所有资源。

```php
$cleanedCount = AsyncResourceManager::cleanupScope($scope);
```

##### cleanupExpired(): int
清理过期资源。

```php
$cleanedCount = AsyncResourceManager::cleanupExpired();
```

##### registerBatch(AsyncResource ...$resources): void
批量注册资源。

```php
AsyncResourceManager::registerBatch($resource1, $resource2, $resource3);
```

##### getResourceCount(CancellationScope $scope): int
获取指定作用域的资源数量。

```php
$count = AsyncResourceManager::getResourceCount($scope);
```

##### getStats(): array
获取资源统计信息。

```php
$stats = AsyncResourceManager::getStats();
```

##### detectLeaks(): array
检测资源泄漏。

```php
$leaks = AsyncResourceManager::detectLeaks();
```

## 可观测性 API

### Observable

Observable 提供事件发布订阅机制。

#### 方法

##### getInstance(): Observable
获取可观测性实例。

##### isEnabled(): bool
检查可观测性是否已启用。

##### enable(): void
启用可观测性。

##### disable(): void
禁用可观测性。

##### addObserver(Observer $observer): void
添加观察者。

##### removeObserver(Observer $observer): void
移除观察者。

##### emitTaskEvent(TaskEvent $event): void
发送任务事件。

##### emitScopeEvent(ScopeEvent $event): void
发送作用域事件。

##### emitResourceEvent(ResourceEvent $event): void
发送资源事件。

### Observer

Observer 是观察者接口，定义了事件处理方法。

#### 方法

##### onTaskEvent(TaskEvent $event): void
处理任务事件。

##### onScopeEvent(ScopeEvent $event): void
处理作用域事件。

##### onResourceEvent(ResourceEvent $event): void
处理资源事件。

### TaskEvent

TaskEvent 是任务事件的类，包含任务生命周期事件。

#### 事件类型

- `TaskEvent::CREATED`: 任务创建
- `TaskEvent::STARTED`: 任务开始
- `TaskEvent::COMPLETED`: 任务完成
- `TaskEvent::FAILED`: 任务失败
- `TaskEvent::CANCELLED`: 任务取消

#### 方法

##### getType(): string
获取事件类型。

##### getTask(): Task
获取事件关联的任务。

### ScopeEvent

ScopeEvent 是作用域事件的类，包含作用域生命周期事件。

#### 事件类型

- `ScopeEvent::CREATED`: 作用域创建
- `ScopeEvent::CANCELLED`: 作用域取消
- `ScopeEvent::COMPLETED`: 作用域完成

#### 方法

##### getType(): string
获取事件类型。

##### getScope(): CancellationScope
获取事件关联的作用域。

## 核心函数 API

### run(callable $main): mixed
运行主协程，自动创建 CancellationScope。

```php
$result = run(function() {
    return 'completed';
});
```

### create_task(callable $callback, string $name = ''): Task
创建异步任务。

```php
$task = create_task(function() {
    return 'task-result';
}, 'my-task');
```

### await(Task $task): mixed
等待任务完成，获取任务结果。

```php
$result = await($task);
```

### sleep(float $seconds): void
异步睡眠，非阻塞。

```php
sleep(1.0); // 等待1秒，不会阻塞其他任务
```

### gather(Task ...$tasks, GatherStrategy $strategy = GatherStrategy::FAIL_FAST): array
并发运行多个任务，等待它们完成。

```php
$results = gather($task1, $task2, $task3, GatherStrategy::WAIT_ALL);
```

### create_future(): Future
创建 Future 对象。

```php
$future = create_future();
```

### await_future(Future $future): mixed
等待 Future 完成，获取结果。

```php
$result = await_future($future);
```

### semaphore(int $max): Semaphore
创建信号量，限制并发数量。

```php
$sem = semaphore(10); // 最多10个并发
```

### with_semaphore(Semaphore $sem, callable $callback): mixed
使用信号量执行回调函数。

```php
$result = with_semaphore($sem, function() {
    return 'result-with-semaphore';
});
```

## 异常处理

### GatherException

GatherException 是 gather 函数抛出的异常，包含所有任务的结果和异常。

#### 方法

##### getExceptions(): array
获取所有失败任务的异常。

##### getResults(): array
获取所有成功任务的结果。

##### getTaskNames(): array
获取任务名称映射。

##### getFailedCount(): int
获取失败任务的数量。

##### getSuccessCount(): int
获取成功任务的数量。

##### hasFailed(int $index): bool
检查指定索引的任务是否失败。

##### getException(int $index): ?\Throwable
获取指定索引任务的异常。

##### getFirstException(): ?\Throwable
获取第一个失败任务的异常。

##### getDetailedReport(): string
获取详细的错误报告。

##### toJson(): string
将异常转换为 JSON 格式。

### TimeoutException

TimeoutException 是超时抛出的异常。

### TaskCancelledException

TaskCancelledException 是任务取消抛出的异常。

## 示例

### 基本使用

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep};

run(function() {
    echo "Hello, ";
    sleep(1);  // 非阻塞睡眠
    echo "AsyncIO v3.0!\n";
});
```

### 结构化并发

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, gather, sleep};
use PfinalClub\Asyncio\Concurrency\{CancellationScope, TaskGroup};
use PfinalClub\Asyncio\Concurrency\GatherStrategy;

run(function() {
    // 使用 CancellationScope
    $result1 = CancellationScope::run(function() {
        $task1 = create_task(function() {
            sleep(0.5);
            return "task1-result";
        });
        
        $task2 = create_task(function() {
            sleep(0.3);
            return "task2-result";
        });
        
        return gather($task1, $task2, GatherStrategy::WAIT_ALL);
    });
    
    // 使用 TaskGroup
    $result2 = run(function() {
        $group = new TaskGroup();
        
        for ($i = 0; $i < 5; $i++) {
            $taskId = $i;
            $task = create_task(function() use ($taskId) {
                sleep(0.1 * $taskId);
                return "task{$taskId}-result";
            });
            $group->addTask($task);
        }
        
        return $group->waitAll();
    });
    
    print_r($result1);
    print_r($result2);
});
```

### 资源管理

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, sleep};
use PfinalClub\Asyncio\Resource\{AsyncResource, AsyncResourceManager};

class MyResource implements AsyncResource {
    private bool $closed = false;
    
    public function close(): void {
        $this->closed = true;
        echo "Resource closed\n";
    }
    
    public function isClosed(): bool {
        return $this->closed;
    }
    
    public function onCancellation(): void {
        $this->close();
    }
}

run(function() {
    $resource = new MyResource();
    AsyncResourceManager::register($resource);
    
    $task = create_task(function() use ($resource) {
        sleep(1);
        return "task-result";
    });
    
    $result = await($task);
    echo "Result: {$result}\n";
});
```

## 最佳实践

1. **使用结构化并发**：尽量使用 CancellationScope、TaskGroup 和 gather 策略来管理并发操作。
2. **注册资源**：确保所有异步资源都通过 AsyncResourceManager 注册，以便在不再使用时被正确释放。
3. **处理异常**：使用 try-catch 块处理异步操作可能抛出的异常。
4. **使用适当的优先级**：根据任务的重要性和紧急程度，使用适当的优先级（SYSTEM、CONTROL、WORK）。
5. **监控和调试**：在开发和调试阶段，启用可观测性，监控异步运行时的行为。

## 性能优化

1. **使用高性能事件循环**：安装 ev 或 event 扩展，提高事件循环性能。
2. **减少任务创建开销**：尽量复用任务，减少任务创建和销毁的开销。
3. **优化资源管理**：及时释放不再使用的资源，减少内存占用。
4. **使用批量操作**：尽量使用批量操作，减少函数调用开销。
5. **避免长时间运行的任务**：将长时间运行的任务拆分为多个短任务，提高事件循环的响应性。

## 兼容性

pfinal-asyncio 兼容 PHP 8.1+，支持多种事件循环后端（Ev、Event、Select）。

## 迁移指南

### 从 v2.x 迁移到 v3.0

1. **核心 API 变更**：v3.0 重构了核心 API，移除了一些不必要的功能，简化了 API 设计。
2. **命名空间变更**：部分类和函数的命名空间发生了变更。
3. **结构化并发**：v3.0 引入了结构化并发，推荐使用 CancellationScope、TaskGroup 和 gather 策略来管理并发操作。
4. **资源管理**：v3.0 引入了资源管理，推荐使用 AsyncResourceManager 来管理异步资源的生命周期。
5. **可观测性**：v3.0 引入了可观测性，推荐使用 Observable 来监控和调试异步运行时。

## 故障排除

1. **任务未执行**：检查任务是否在 CancellationScope 中创建，确保事件循环正在运行。
2. **资源泄漏**：使用 AsyncResourceManager::detectLeaks() 检测资源泄漏，确保所有资源都通过 AsyncResourceManager 注册。
3. **性能问题**：检查事件循环类型，确保使用了高性能的事件循环后端（Ev、Event）。
4. **异常未捕获**：使用 try-catch 块处理异步操作可能抛出的异常，使用 GatherException 获取详细的失败信息。

## 常见问题

### Q: 如何选择事件循环后端？
A: 推荐使用 ev 扩展，其次是 event 扩展，最后是 select。ev 扩展性能最高，event 扩展次之，select 是兜底方案。

### Q: 如何监控和调试异步运行时？
A: 启用可观测性，添加观察者来监控任务、作用域和资源的生命周期事件。

### Q: 如何处理长时间运行的任务？
A: 将长时间运行的任务拆分为多个短任务，或者使用适当的优先级，避免阻塞事件循环。

### Q: 如何管理异步资源？
A: 实现 AsyncResource 接口，通过 AsyncResourceManager 注册资源，确保资源在不再使用时被正确释放。

### Q: 如何处理异常？
A: 使用 try-catch 块处理异步操作可能抛出的异常，使用 GatherException 获取详细的失败信息。

## 贡献

欢迎提交 Issue 和 Pull Request！请遵循项目的代码风格和贡献指南。

## 许可证

pfinal-asyncio 采用 MIT 许可证，详见 LICENSE 文件。

## 版本历史

详见 CHANGELOG.md 文件。
