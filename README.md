# PHP AsyncIO

基于 Workerman 框架实现的 PHP 异步 IO 扩展包，提供类似 Python asyncio 的 API 和功能。

## 特性

### 核心功能
- 🚀 基于 Workerman 的高性能事件循环
- 🔄 协程支持（使用 PHP Generator）
- ⚡ 异步任务调度和管理
- ⏰ 定时器和延迟执行
- 🎯 并发控制（gather, wait_for 等）
- 🛡️ 异常处理和任务取消
- 📦 简洁的 API，类似 Python asyncio

### 生产工具 ⭐️ NEW
- 📊 **AsyncIO Monitor** - 实时监控任务、内存、性能指标
- 🐛 **AsyncIO Debugger** - 追踪 await 链路，可视化协程调用栈
- 🌐 **AsyncIO HTTP Client** - 完整的异步 HTTP 客户端（支持 SSL、重定向等）

## 安装

```bash
composer require pfinalclub/asyncio
```

## 要求

- PHP >= 8.3
- Workerman >= 4.1

## 快速开始

### 基础示例

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use function PfinalClub\Asyncio\{run, sleep};

// 定义一个异步函数
function hello_world(): \Generator
{
    echo "Hello\n";
    yield sleep(1); // 异步睡眠 1 秒
    echo "World\n";
    return "Done!";
}

// 运行协程
$result = run(hello_world());
echo "Result: {$result}\n";
```

### 并发任务

```php
<?php
use function PfinalClub\Asyncio\{run, create_task, gather, sleep};

function task1(): \Generator
{
    echo "Task 1 开始\n";
    yield sleep(2);
    echo "Task 1 完成\n";
    return "结果 1";
}

function task2(): \Generator
{
    echo "Task 2 开始\n";
    yield sleep(1);
    echo "Task 2 完成\n";
    return "结果 2";
}

function main(): \Generator
{
    // 创建任务
    $t1 = create_task(task1());
    $t2 = create_task(task2());
    
    // 并发等待所有任务完成
    $results = yield gather($t1, $t2);
    
    print_r($results); // ['结果 1', '结果 2']
}

run(main());
```

### 超时控制

```php
<?php
use function PfinalClub\Asyncio\{run, wait_for, sleep};
use PfinalClub\Asyncio\TimeoutException;

function slow_task(): \Generator
{
    yield sleep(5);
    return "完成";
}

function main(): \Generator
{
    try {
        // 最多等待 2 秒
        $result = yield wait_for(slow_task(), 2.0);
        echo "结果: {$result}\n";
    } catch (TimeoutException $e) {
        echo "任务超时: {$e->getMessage()}\n";
    }
}

run(main());
```

### 任务管理

```php
<?php
use function PfinalClub\Asyncio\{run, create_task, sleep};

function background_task(string $name): \Generator
{
    for ($i = 1; $i <= 5; $i++) {
        echo "{$name}: 步骤 {$i}\n";
        yield sleep(0.5);
    }
    return "{$name} 完成";
}

function main(): \Generator
{
    // 创建多个后台任务
    $task1 = create_task(background_task("任务A"));
    $task2 = create_task(background_task("任务B"));
    
    // 等待一段时间
    yield sleep(2);
    
    // 检查任务状态
    echo "任务1 完成: " . ($task1->isDone() ? "是" : "否") . "\n";
    echo "任务2 完成: " . ($task2->isDone() ? "是" : "否") . "\n";
    
    // 等待任务完成
    $result1 = yield $task1;
    $result2 = yield $task2;
    
    echo "{$result1}, {$result2}\n";
}

run(main());
```

### HTTP 客户端示例

```php
<?php
use function PfinalClub\Asyncio\{run, create_task, gather, sleep};

function fetch_url(string $url): \Generator
{
    echo "开始获取: {$url}\n";
    
    // 模拟网络请求
    yield sleep(rand(1, 3));
    
    echo "完成获取: {$url}\n";
    return "来自 {$url} 的数据";
}

function main(): \Generator
{
    $urls = [
        'https://api.example.com/users',
        'https://api.example.com/posts',
        'https://api.example.com/comments',
    ];
    
    // 并发请求所有 URL
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(fetch_url($url));
    }
    
    // 等待所有请求完成
    $results = yield gather(...$tasks);
    
    foreach ($results as $result) {
        echo "{$result}\n";
    }
}

run(main());
```

## API 参考

### 核心函数

#### `run(\Generator $coroutine): mixed`
运行协程直到完成并返回结果。这是程序的主入口点。

```php
$result = run(my_coroutine());
```

#### `create_task(\Generator $coroutine, string $name = ''): Task`
创建并调度一个任务，立即开始执行。

```php
$task = create_task(my_coroutine(), 'my-task');
```

#### `sleep(float $seconds): Sleep`
异步睡眠指定的秒数。

```php
yield sleep(1.5); // 睡眠 1.5 秒
```

#### `gather(Task ...$tasks): \Generator`
并发运行多个任务并等待它们全部完成。

```php
$results = yield gather($task1, $task2, $task3);
```

#### `wait_for(\Generator|Task $awaitable, float $timeout): \Generator`
等待任务完成，如果超时则抛出 TimeoutException。

```php
try {
    $result = yield wait_for($task, 5.0);
} catch (TimeoutException $e) {
    echo "超时!\n";
}
```

### 事件循环

#### `get_event_loop(): EventLoop`
获取当前事件循环实例。

```php
$loop = get_event_loop();
```

### Task 类

#### `isDone(): bool`
检查任务是否已完成。

#### `getResult(): mixed`
获取任务结果（如果任务未完成会抛出异常）。

#### `cancel(): bool`
取消任务。

#### `addDoneCallback(callable $callback): void`
添加任务完成时的回调。

### Future 类

Future 表示一个未来的结果，可以手动设置。

```php
$future = create_future();

// 在某处设置结果
$future->setResult("结果");

// 等待结果
$result = yield $future;
```

## 高级用法

### 自定义事件循环

```php
use PfinalClub\Asyncio\EventLoop;

$loop = EventLoop::getInstance();

// 添加定时器
$timerId = $loop->addTimer(1.0, function() {
    echo "每秒执行一次\n";
}, true); // true = 重复执行

// 删除定时器
$loop->delTimer($timerId);
```

### 异常处理

```php
function risky_task(): \Generator
{
    yield sleep(1);
    throw new \Exception("出错了!");
}

function main(): \Generator
{
    try {
        yield risky_task();
    } catch (\Exception $e) {
        echo "捕获异常: {$e->getMessage()}\n";
    }
}

run(main());
```

### 任务取消

```php
function cancellable_task(): \Generator
{
    for ($i = 0; $i < 10; $i++) {
        echo "步骤 {$i}\n";
        yield sleep(1);
    }
}

function main(): \Generator
{
    $task = create_task(cancellable_task());
    
    yield sleep(3);
    
    // 取消任务
    if ($task->cancel()) {
        echo "任务已取消\n";
    }
}

run(main());
```

## 与 Python asyncio 的对比

| Python asyncio | PHP AsyncIO |
|---------------|-------------|
| `asyncio.run()` | `run()` |
| `asyncio.create_task()` | `create_task()` |
| `asyncio.sleep()` | `sleep()` |
| `asyncio.gather()` | `gather()` |
| `asyncio.wait_for()` | `wait_for()` |
| `async def func():` | `function func(): \Generator` |
| `await expr` | `yield expr` |
| `asyncio.get_event_loop()` | `get_event_loop()` |

## 性能建议

1. **避免阻塞操作**：在协程中避免使用阻塞的函数调用（如 `file_get_contents`、`sleep` 等），使用异步版本。

2. **合理使用并发**：使用 `gather()` 来并发执行独立的任务，提高效率。

3. **设置超时**：对于外部请求，始终使用 `wait_for()` 设置超时。

4. **错误处理**：在协程中适当地处理异常，避免未捕获的异常。

## 实际应用示例

### Web 爬虫

```php
function crawl_website(array $urls): \Generator
{
    $tasks = [];
    foreach ($urls as $url) {
        $tasks[] = create_task(fetch_page($url));
    }
    
    return yield gather(...$tasks);
}

function fetch_page(string $url): \Generator
{
    try {
        $result = yield wait_for(http_get($url), 10.0);
        return parse_html($result);
    } catch (TimeoutException $e) {
        return null;
    }
}
```

### 批量数据处理

```php
function process_batch(array $items): \Generator
{
    $tasks = [];
    foreach ($items as $item) {
        $tasks[] = create_task(process_item($item));
    }
    
    return yield gather(...$tasks);
}

function process_item($item): \Generator
{
    // 模拟异步处理
    yield sleep(0.1);
    return $item * 2;
}
```

## 注意事项

1. **PHP Generator 限制**：PHP 的 Generator 不支持真正的异步，但通过 Workerman 的事件循环，我们可以实现协作式多任务。

2. **命名空间**：所有函数都在 `PfinalClub\Asyncio` 命名空间下，使用时需要导入。

3. **返回值**：协程函数必须返回 Generator 对象（使用 `yield`）。

4. **Workerman 集成**：此包基于 Workerman，继承了其所有特性和限制。

## 许可证

MIT License

## 贡献

欢迎提交 Issue 和 Pull Request！

## 相关链接

- [Workerman 文档](https://www.workerman.net/)
- [Python asyncio 文档](https://docs.python.org/3/library/asyncio.html)

