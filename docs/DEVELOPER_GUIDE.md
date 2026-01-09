# pfinal-asyncio 开发者指南

## 目录

1. [快速入门](#快速入门)
2. [核心概念](#核心概念)
3. [异步编程模式](#异步编程模式)
4. [结构化并发](#结构化并发)
5. [资源管理](#资源管理)
6. [可观测性](#可观测性)
7. [性能优化](#性能优化)
8. [常见问题](#常见问题)
9. [最佳实践](#最佳实践)

## 快速入门

### 安装

```bash
composer require pfinal/asyncio
```

### 基本用法

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use function PfinalClub\Asyncio\{run, create_task, sleep};

// 主函数必须通过 run() 执行
$result = run(function() {
    // 创建异步任务
    $task1 = create_task(function() {
        sleep(1); // 异步睡眠，不会阻塞主线程
        return 'Hello from task 1';
    });
    
    $task2 = create_task(function() {
        sleep(0.5);
        return 'Hello from task 2';
    });
    
    // 等待所有任务完成
    $result1 = $task1->getResult();
    $result2 = $task2->getResult();
    
    return [$result1, $result2];
});

print_r($result);
// 输出:
// Array
// (
//     [0] => Hello from task 1
//     [1] => Hello from task 2
// )
```

## 核心概念

### 事件循环

事件循环是异步运行时的核心，负责调度和执行异步任务。pfinal-asyncio 基于 Workerman 实现，支持多种事件循环后端：

- **Ev**：推荐，最高性能
- **Event**：次选，高性能
- **Select**：基础性能，兼容性最好

### 任务

任务是对 Fiber 的封装，管理异步操作的生命周期。每个任务都有明确的状态，由 `TaskState` 枚举管理：

- **PENDING**：任务已创建但未开始执行
- **RUNNING**：任务正在执行中
- **COMPLETED**：任务已成功完成
- **CANCELLED**：任务已被取消
- **FAILED**：任务执行失败

### 结构化并发

结构化并发是一种并发编程范式，确保并发操作的可预测性和可维护性。pfinal-asyncio 提供了以下机制：

- **CancellationScope**：用于取消一组相关任务
- **TaskGroup**：用于管理一组相关任务，确保它们要么全部成功，要么全部失败
- **gather 策略**：用于并行执行多个任务，支持不同的错误处理策略

### 资源管理

资源管理负责管理异步资源的生命周期，确保资源在不再使用时被正确释放。主要组件包括：

- **AsyncResource**：异步资源接口
- **AsyncResourceManager**：资源管理器，负责注册和清理资源
- **Context**：上下文，用于在任务间传递状态

## 异步编程模式

### 基本异步操作

```php
use function PfinalClub\Asyncio\{run, create_task, sleep};

run(function() {
    // 基本异步睡眠
    sleep(1);
    
    // 创建并等待任务
    $task = create_task(function() {
        sleep(0.5);
        return 'result';
    });
    
    $result = $task->getResult();
    echo $result;
});
```

### 并行任务

```php
use function PfinalClub\Asyncio\{run, create_task, gather, sleep};

run(function() {
    $tasks = [];
    
    // 创建多个并行任务
    for ($i = 0; $i < 10; $i++) {
        $tasks[] = create_task(function() use ($i) {
            sleep(0.1);
            return "result-{$i}";
        });
    }
    
    // 等待所有任务完成
    $results = gather(...$tasks);
    print_r($results);
});
```

### 异步错误处理

```php
use function PfinalClub\Asyncio\{run, create_task};

run(function() {
    try {
        $task = create_task(function() {
            throw new \Exception('Task failed');
        });
        
        $result = $task->getResult();
    } catch (\Exception $e) {
        echo "Caught exception: {$e->getMessage()}";
    }
});
```

## 结构化并发

### CancellationScope

用于取消一组相关任务：

```php
use PfinalClub\Asyncio\Concurrency\CancellationScope;
use function PfinalClub\Asyncio\{run, create_task, sleep};

run(function() {
    // 创建取消作用域
    $scope = new CancellationScope();
    
    // 在作用域内创建任务
    $task1 = create_task(function() use ($scope) {
        try {
            while (true) {
                sleep(0.1);
                echo "Task 1 running...\n";
                
                // 检查是否被取消
                $scope->checkCancelled();
            }
        } catch (\PfinalClub\Asyncio\Exception\TaskCancelledException $e) {
            echo "Task 1 cancelled\n";
        }
    });
    
    $task2 = create_task(function() use ($scope) {
        try {
            sleep(1);
            echo "Task 2 completed\n";
        } catch (\PfinalClub\Asyncio\Exception\TaskCancelledException $e) {
            echo "Task 2 cancelled\n";
        }
    });
    
    // 500ms 后取消所有任务
    sleep(0.5);
    $scope->cancel();
    
    // 等待所有任务完成
    $task1->getResult();
    $task2->getResult();
});
```

### TaskGroup

用于管理一组相关任务，确保它们要么全部成功，要么全部失败：

```php
use PfinalClub\Asyncio\Concurrency\TaskGroup;
use function PfinalClub\Asyncio\{run, sleep};

run(function() {
    $group = new TaskGroup();
    
    // 添加任务到组
    $group->add(function() {
        sleep(0.5);
        return 'result1';
    });
    
    $group->add(function() {
        sleep(0.3);
        return 'result2';
    });
    
    // 等待所有任务完成
    $results = $group->waitAll();
    print_r($results);
});
```

### Gather 策略

支持三种不同的错误处理策略：

```php
use PfinalClub\Asyncio\Concurrency\GatherStrategy;
use function PfinalClub\Asyncio\{run, create_task, gather, sleep};

run(function() {
    $tasks = [];
    
    $tasks[] = create_task(function() {
        sleep(0.1);
        return 'success1';
    });
    
    $tasks[] = create_task(function() {
        sleep(0.2);
        throw new \Exception('failure');
    });
    
    $tasks[] = create_task(function() {
        sleep(0.3);
        return 'success3';
    });
    
    // 策略1: FAIL_FAST（默认） - 第一个错误立即抛出
    try {
        $results = gather(...$tasks, GatherStrategy::FAIL_FAST);
    } catch (\PfinalClub\Asyncio\Exception\GatherException $e) {
        echo "FAIL_FAST: {$e->getMessage()}\n";
    }
    
    // 策略2: WAIT_ALL - 等待所有任务完成，然后抛出所有错误
    try {
        $results = gather(...$tasks, GatherStrategy::WAIT_ALL);
    } catch (\PfinalClub\Asyncio\Exception\GatherException $e) {
        echo "WAIT_ALL: {$e->getMessage()}\n";
        echo "Errors count: " . count($e->getExceptions()) . "\n";
    }
    
    // 策略3: RETURN_PARTIAL - 返回已完成的结果，忽略错误
    $results = gather(...$tasks, GatherStrategy::RETURN_PARTIAL);
    echo "RETURN_PARTIAL results count: " . count($results) . "\n";
});
```

## 资源管理

### 自定义异步资源

```php
use PfinalClub\Asyncio\Resource\AsyncResource;
use PfinalClub\Asyncio\Resource\AsyncResourceManager;
use function PfinalClub\Asyncio\{run};

class CustomResource implements AsyncResource {
    private $handle;
    private $closed = false;
    
    public function __construct($handle) {
        $this->handle = $handle;
        // 注册资源到管理器
        AsyncResourceManager::getInstance()->registerResource($this);
    }
    
    public function close(): void {
        if (!$this->closed) {
            fclose($this->handle);
            $this->closed = true;
        }
    }
    
    public function isClosed(): bool {
        return $this->closed;
    }
    
    public function onCancellation(): void {
        $this->close();
    }
}

run(function() {
    $handle = fopen('/tmp/test.txt', 'w');
    $resource = new CustomResource($handle);
    
    // 使用资源
    fwrite($handle, 'Hello, world!');
    
    // 资源会在任务完成后自动清理
});
```

### 上下文管理

```php
use PfinalClub\Asyncio\Resource\Context;
use function PfinalClub\Asyncio\{run, create_task};

run(function() {
    // 设置根上下文值
    $context = Context::getCurrent();
    $context->set('app.name', 'MyApp');
    $context->set('app.version', '1.0.0');
    
    // 创建子任务，继承上下文
    $task = create_task(function() {
        $context = Context::getCurrent();
        $appName = $context->get('app.name');
        $appVersion = $context->get('app.version');
        
        echo "App: {$appName} v{$appVersion}\n";
        
        // 在子任务中设置新值，不会影响父上下文
        $context->set('request.id', '12345');
        
        // 创建嵌套子任务
        $nestedTask = create_task(function() {
            $context = Context::getCurrent();
            echo "Request ID: {$context->get('request.id')}\n";
            echo "App: {$context->get('app.name')}\n";
        });
        
        $nestedTask->getResult();
    });
    
    $task->getResult();
    
    // 检查父上下文不受影响
    $requestId = Context::getCurrent()->get('request.id');
    echo "Parent request ID: " . ($requestId ?? 'null') . "\n";
});
```

## 可观测性

### 事件订阅

```php
use PfinalClub\Asyncio\Observable\Observable;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;
use function PfinalClub\Asyncio\{run, create_task, sleep};

run(function() {
    // 订阅任务事件
    Observable::subscribe(TaskEvent::class, function(TaskEvent $event) {
        echo "Task event: {$event->getType()} - Task ID: {$event->getTaskId()}\n";
    });
    
    // 创建任务，会触发事件
    $task = create_task(function() {
        sleep(0.1);
        return 'result';
    });
    
    $task->getResult();
});
```

### 支持的事件类型

- **TaskEvent**：任务生命周期事件
  - STARTED：任务开始执行
  - COMPLETED：任务成功完成
  - FAILED：任务执行失败
  - CANCELLED：任务被取消
  
- **ScopeEvent**：作用域事件
  - CREATED：作用域创建
  - CANCELLED：作用域被取消
  - CLOSED：作用域关闭

## 性能优化

### 事件循环优化

1. **使用高性能事件循环后端**
   - 安装 Ev 扩展：`pecl install ev`
   - 或安装 Event 扩展：`pecl install event`
   
2. **避免阻塞操作**
   - 不要在异步任务中使用阻塞 I/O
   - 不要使用 `sleep()`，使用 `PfinalClub\Asyncio\sleep()` 替代
   - 避免长时间运行的 CPU 密集型操作

3. **合理使用并发**
   - 根据系统资源调整并发数
   - 使用 Semaphore 控制并发访问
   - 避免创建过多的小任务

### 内存优化

1. **及时释放资源**
   - 实现 AsyncResource 接口，确保资源自动清理
   - 避免内存泄漏
   
2. **使用延迟清理池**
   - pfinal-asyncio 内置了 Fiber 延迟清理池，自动管理 Fiber 资源
   - 可以通过环境变量调整池大小：`PFINAL_ASYNCIO_FIBER_POOL_SIZE`

### 调度优化

1. **合理设置任务优先级**
   - 使用三级调度模型：SYSTEM（最高）、CONTROL、WORK（最低）
   - 系统关键任务使用 SYSTEM 优先级
   - 控制任务使用 CONTROL 优先级
   - 普通工作任务使用 WORK 优先级

2. **避免长时间运行的任务**
   - 将长时间运行的任务拆分为多个小任务
   - 使用 `sleep(0)` 主动让出 CPU 时间

## 常见问题

### 1. 为什么我的代码没有异步执行？

- 确保所有异步操作都在 `run()` 函数内执行
- 确保使用了 `pfinal/asyncio` 提供的异步函数，如 `sleep()` 而不是 `\sleep()`
- 确保 PHP 版本 >= 8.1

### 2. 如何处理异步错误？

- 使用 try-catch 块捕获异步任务抛出的异常
- 使用 `gather()` 策略处理多个任务的错误
- 实现 `AsyncResource::onCancellation()` 处理取消事件

### 3. 如何调试异步代码？

- 使用事件订阅机制监控任务执行
- 在关键位置添加日志
- 使用 `Observable::subscribe()` 订阅事件

### 4. 为什么我的资源没有被释放？

- 确保资源实现了 `AsyncResource` 接口
- 确保资源已注册到 `AsyncResourceManager`
- 检查是否有循环引用

### 5. 如何处理并发访问？

- 使用 `Semaphore` 控制并发访问
- 使用 `TaskGroup` 管理相关任务
- 使用 `CancellationScope` 取消相关任务

## 最佳实践

1. **始终使用 run() 包装主函数**
   - 所有异步操作必须在 `run()` 函数内执行

2. **使用结构化并发**
   - 优先使用 `TaskGroup` 和 `CancellationScope` 管理任务
   - 避免手动管理任务生命周期

3. **实现资源自动管理**
   - 为所有异步资源实现 `AsyncResource` 接口
   - 确保资源在不再使用时被正确释放

4. **使用可观测性**
   - 订阅事件以监控系统状态
   - 实现自定义事件以满足特定需求

5. **优化事件循环**
   - 安装高性能事件循环后端
   - 避免阻塞操作

6. **合理设置任务优先级**
   - 根据任务重要性设置适当的优先级
   - 避免滥用高优先级

7. **编写可测试的代码**
   - 使用依赖注入
   - 避免全局状态
   - 编写单元测试和集成测试

8. **遵循 PSR 标准**
   - 遵循 PSR-4 自动加载标准
   - 遵循 PSR-12 代码风格

## 进阶主题

### 自定义事件循环

```php
use PfinalClub\Asyncio\Core\EventLoopInterface;

class CustomEventLoop implements EventLoopInterface {
    // 实现事件循环接口
    // ...
}

// 注册自定义事件循环
EventLoopInterface::setInstance(new CustomEventLoop());
```

### 自定义调度器

```php
use PfinalClub\Asyncio\Core\SchedulerInterface;

class CustomScheduler implements SchedulerInterface {
    // 实现调度器接口
    // ...
}

// 使用自定义调度器
$eventLoop = \PfinalClub\Asyncio\Core\EventLoop::getInstance();
$eventLoop->setScheduler(new CustomScheduler());
```

### 多进程模式

```php
use PfinalClub\Asyncio\Production\MultiProcessMode;

// 创建多进程实例
$multiProcess = new MultiProcessMode();

// 设置进程数
$multiProcess->setProcessCount(4);

// 设置主函数
$multiProcess->setMainFunction(function() {
    // 异步代码
    use function PfinalClub\Asyncio\{run, create_task, sleep};
    
    run(function() {
        while (true) {
            echo "Worker running...\n";
            sleep(1);
        }
    });
});

// 启动多进程
$multiProcess->run();
```

## 生产部署

### 资源限制

```php
use PfinalClub\Asyncio\Production\ResourceLimits;

$limits = new ResourceLimits();

// 设置最大内存使用量（MB）
$limits->setMaxMemory(128);

// 设置最大执行时间（秒）
$limits->setMaxExecutionTime(3600);

// 设置最大文件描述符
$limits->setMaxFileDescriptors(1024);

// 应用限制
$limits->apply();
```

### 健康检查

```php
use PfinalClub\Asyncio\Production\HealthCheck;

$healthCheck = new HealthCheck();

// 添加健康检查项
$healthCheck->addCheck('memory', function() {
    $memoryUsage = memory_get_usage(true) / 1024 / 1024;
    return $memoryUsage < 128; // 内存使用量 < 128MB
});

$healthCheck->addCheck('cpu', function() {
    // 简单的CPU使用率检查
    $load = sys_getloadavg();
    return $load[0] < 4.0; // 1分钟负载 < 4.0
});

// 运行健康检查
$results = $healthCheck->run();
if ($healthCheck->isHealthy()) {
    echo "System is healthy\n";
} else {
    echo "System is unhealthy\n";
    foreach ($results as $check => $result) {
        echo "{$check}: " . ($result ? 'OK' : 'FAIL') . "\n";
    }
}
```

### 优雅关闭

```php
use PfinalClub\Asyncio\Production\GracefulShutdown;

$shutdown = new GracefulShutdown();

// 注册关闭回调
$shutdown->register(function() {
    echo "Shutting down gracefully...\n";
    // 清理资源
    // ...
});

// 启动异步代码
use function PfinalClub\Asyncio\{run};

run(function() {
    // 异步代码
    // ...
});
```

## 结论

pfinal-asyncio 提供了一个强大的异步运行时内核，支持结构化并发、资源管理和可观测性。通过遵循本指南中的最佳实践，您可以编写高效、可靠和可维护的异步应用程序。

如需更多信息，请参考：
- [API 文档](./API_DOCUMENTATION.md)
- [架构设计文档](./ARCHITECTURE_DESIGN.md)
- [GitHub 仓库](https://github.com/pfinal/asyncio)
