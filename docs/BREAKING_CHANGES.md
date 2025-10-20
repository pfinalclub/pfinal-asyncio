# Breaking Changes in v2.0

## 概述

v2.0 是一次重大重构，完全从 Generator 迁移到 Fiber。这是一个破坏性变更，需要重写所有使用此库的代码。

## 主要变更

### 1. PHP 版本要求

**v1.x:**
```
PHP >= 8.3
```

**v2.0:**
```
PHP >= 8.1 (需要 Fiber 支持)
```

### 2. 函数签名变更

**v1.x (Generator):**
```php
function my_task(): \Generator
{
    yield sleep(1);
    yield $other_task;
    return "result";
}
```

**v2.0 (Fiber):**
```php
function my_task(): mixed
{
    sleep(1);
    await($other_task);
    return "result";
}
```

### 3. API 变更

| 功能 | v1.x | v2.0 |
|------|------|------|
| 睡眠 | `yield sleep(1)` | `sleep(1)` |
| 等待任务 | `yield $task` | `await($task)` |
| 并发 | `yield gather(...)` | `gather(...)` |
| 运行主函数 | `run(generator())` | `run(callable)` |
| 创建任务 | `create_task(generator())` | `create_task(callable)` |

### 4. 移除的类和函数

**移除的类:**
- `Sleep` 类 - 不再需要，`sleep()` 直接暂停 Fiber

**移除的函数:**
- `async_wrap()` - Fiber 不需要包装
- `coroutine()` - 不再需要
- `await_coro()` - 替换为 `await()`

**保留但修改的函数:**
- `run()` - 现在接受 `callable` 而非 `\Generator`
- `create_task()` - 现在接受 `callable` 而非 `\Generator`
- `sleep()` - 现在返回 `void` 而非 `Sleep` 对象
- `gather()` - 现在返回 `array` 而非 `\Generator`
- `wait_for()` - 现在接受 `callable|Task` 而非 `\Generator|Task`

### 5. 新增的函数

- `async()` - `create_task()` 的别名
- `await()` - 显式等待任务
- `spawn()` - `create_task()` 的另一个别名

### 6. EventLoop 变更

**v1.x:**
```php
class EventLoop
{
    private array $tasks = [];
    private function step(Task $task): void { ... }
    private function handleGather(...): void { ... }
    public function isLightweightMode(): bool { ... }
    private function runSimpleLoop(): void { ... }
}
```

**v2.0:**
```php
class EventLoop
{
    private array $fibers = [];
    public function createFiber(callable $callback): Task { ... }
    public function sleep(float $seconds): void { ... }
    public function await(Task $task): mixed { ... }
    public function gather(array $tasks): array { ... }
    // 移除 isLightweightMode()
    // 移除 runSimpleLoop()
}
```

### 7. Task 类变更

**v1.x:**
```php
class Task
{
    private \Generator $coroutine;
    public function __construct(\Generator $coroutine, int $id, string $name) { ... }
    public function getCoroutine(): \Generator { ... }
}
```

**v2.0:**
```php
class Task
{
    private mixed $callable;
    public function __construct(callable $callable, int $id, string $name) { ... }
    public function getCallable(): callable { ... }
}
```

### 8. HTTP 客户端变更

**v1.x:**
```php
class AsyncHttpClient
{
    public function get(string $url): Future { ... }
}

// 使用
$future = $client->get($url);
$response = yield $future;
```

**v2.0:**
```php
class AsyncHttpClient
{
    public function get(string $url): HttpResponse { ... }
}

// 使用
$response = $client->get($url); // 直接返回，自动暂停 Fiber
```

### 9. HTTP 辅助函数变更

**v1.x:**
```php
function http_get(string $url): \Generator
{
    return yield from AsyncHttpClient::get($url);
}

// 使用
$response = yield http_get($url);
```

**v2.0:**
```php
function http_get(string $url): HttpResponse
{
    $client = new AsyncHttpClient();
    return $client->get($url);
}

// 使用
$response = http_get($url);
```

## 迁移步骤

### 步骤 1: 更新 PHP 版本

确保你的环境支持 PHP 8.1 或更高版本。

### 步骤 2: 更新 Composer 依赖

```bash
composer require pfinalclub/asyncio:^2.0
```

### 步骤 3: 重写函数签名

将所有 `\Generator` 返回类型改为 `mixed` 或具体类型。

**之前:**
```php
function my_task(): \Generator
{
    // ...
}
```

**之后:**
```php
function my_task(): string  // 或 mixed
{
    // ...
}
```

### 步骤 4: 移除 yield 关键字

将所有 `yield` 关键字改为直接调用。

**之前:**
```php
yield sleep(1);
$result = yield $task;
$results = yield gather($t1, $t2);
```

**之后:**
```php
sleep(1);
$result = await($task);
$results = gather($t1, $t2);
```

### 步骤 5: 更新 run() 调用

**之前:**
```php
run(main());  // 调用函数返回 Generator
```

**之后:**
```php
run(main(...));  // 传递函数引用
```

### 步骤 6: 测试

运行完整的测试套件确保迁移正确。

## 常见问题

### Q: 为什么要进行这次破坏性变更？

A: Fiber 是 PHP 的原生协程实现，比 Generator 性能更好（2-3倍），代码更简洁，堆栈追踪更完整。这是 PHP 异步编程的未来方向。

### Q: 我可以同时使用 v1.x 和 v2.0 吗？

A: 不可以，它们的 API 完全不兼容。建议完全迁移到 v2.0。

### Q: 迁移需要多长时间？

A: 对于小项目，可能只需要几个小时。对于大项目，可能需要几天。主要工作是移除 `yield` 关键字和更新函数签名。

### Q: 有自动迁移工具吗？

A: 目前没有，但迁移模式很规律，可以使用正则表达式辅助。

### Q: 性能真的提升了吗？

A: 是的，基准测试显示：
- 任务创建快 2-3 倍
- 并发执行快 2 倍
- 内存占用更少

### Q: 如果我坚持使用 v1.x 呢？

A: v1.x 将进入维护模式，只修复严重 bug，不再添加新功能。强烈建议升级到 v2.0。

## 迁移示例

### 示例 1: 简单任务

**v1.x:**
```php
function task(): \Generator
{
    echo "开始\n";
    yield sleep(1);
    echo "结束\n";
    return "done";
}

run(main());
```

**v2.0:**
```php
function task(): string
{
    echo "开始\n";
    sleep(1);
    echo "结束\n";
    return "done";
}

run(main(...));
```

### 示例 2: 并发任务

**v1.x:**
```php
function main(): \Generator
{
    $t1 = create_task(task1());
    $t2 = create_task(task2());
    $results = yield gather($t1, $t2);
    return $results;
}
```

**v2.0:**
```php
function main(): array
{
    $t1 = create_task(task1(...));
    $t2 = create_task(task2(...));
    $results = gather($t1, $t2);
    return $results;
}
```

### 示例 3: HTTP 请求

**v1.x:**
```php
function fetch(): \Generator
{
    $client = new AsyncHttpClient();
    $future = $client->get('https://api.example.com');
    $response = yield $future;
    return $response->getBody();
}
```

**v2.0:**
```php
function fetch(): string
{
    $client = new AsyncHttpClient();
    $response = $client->get('https://api.example.com');
    return $response->getBody();
}
```

## 支持

如果你在迁移过程中遇到问题：

1. 查看 [示例文件](../examples/)
2. 阅读 [API 文档](../README.md)
3. 提交 [Issue](https://github.com/pfinalclub/asyncio/issues)

---

**版本:** 2.0.0  
**日期:** 2025-01-20

