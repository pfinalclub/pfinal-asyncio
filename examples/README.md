# AsyncIO 使用示例

本目录包含 PHP AsyncIO 的完整使用示例，从基础到高级，涵盖各种实际应用场景。

## 📚 示例列表

### 基础入门

| 文件 | 说明 | 难度 |
|------|------|------|
| `01_hello_world.php` | 最简单的异步程序 | ⭐ |
| `02_concurrent_tasks.php` | 并发任务执行 | ⭐⭐ |
| `03_timeout_cancel.php` | 超时控制和任务取消 | ⭐⭐ |
| `05_error_handling.php` | 异常处理 | ⭐⭐ |

### 网络编程

| 文件 | 说明 | 难度 |
|------|------|------|
| `04_http_client.php` | HTTP 客户端请求 | ⭐⭐ |
| `06_real_world_crawler.php` | 真实案例：网页爬虫 | ⭐⭐⭐ |

### 高级主题

| 文件 | 说明 | 难度 |
|------|------|------|
| `07_monitor_performance.php` | 性能监控 | ⭐⭐⭐ |
| `08_async_queue.php` | 异步队列（生产者-消费者） | ⭐⭐⭐ |

## 🚀 快速开始

### 运行单个示例

```bash
# 基础示例
php examples/01_hello_world.php

# HTTP 客户端
php examples/04_http_client.php

# 性能监控
php examples/07_monitor_performance.php
```

### 使用 Composer 脚本

```bash
# 查看所有可用命令
composer list

# 运行示例
composer demo
composer example-basic
composer example-concurrent
```

## 📖 学习路径

### 第一步：理解异步基础

1. **01_hello_world.php** - 了解 `run()` 和 `sleep()` 的基本用法
2. **02_concurrent_tasks.php** - 学习如何创建和管理并发任务
3. **03_timeout_cancel.php** - 掌握超时和取消机制

### 第二步：错误处理

4. **05_error_handling.php** - 学习异步代码中的异常处理

### 第三步：网络编程

5. **04_http_client.php** - 掌握异步 HTTP 客户端
6. **06_real_world_crawler.php** - 完整的爬虫应用

### 第四步：高级特性

7. **07_monitor_performance.php** - 性能监控和优化
8. **08_async_queue.php** - 高级异步模式

## 💡 核心概念

### 1. 异步函数

AsyncIO 中的异步函数是普通的 PHP 函数，通过 `sleep()` 等异步操作暂停执行：

```php
function myAsyncFunction(): mixed
{
    echo "开始\n";
    sleep(1);  // 异步等待，不阻塞其他任务
    echo "结束\n";
    return "结果";
}
```

### 2. 创建任务

使用 `create_task()` 将函数转换为并发任务：

```php
$task = create_task(myAsyncFunction(...));
// 任务立即开始执行，不会阻塞
```

### 3. 等待结果

- `await($task)` - 等待单个任务
- `gather($task1, $task2, ...)` - 等待多个任务

```php
// 并发执行
$t1 = create_task(fn() => fetchUser(1));
$t2 = create_task(fn() => fetchUser(2));

// 等待所有完成
$results = gather($t1, $t2);
```

### 4. 运行主函数

所有异步代码必须在 `run()` 中执行：

```php
$result = run(function() {
    // 异步代码
    return "结果";
});
```

## 🔧 常见模式

### 模式 1: 并发请求

```php
run(function() use ($client) {
    $urls = ['url1', 'url2', 'url3'];
    
    $tasks = array_map(
        fn($url) => create_task(fn() => $client->get($url)),
        $urls
    );
    
    $responses = gather(...$tasks);
});
```

### 模式 2: 超时保护

```php
try {
    $result = wait_for(function() {
        // 可能很慢的操作
        return doSlowThing();
    }, timeout: 5.0);
} catch (TimeoutException $e) {
    echo "操作超时\n";
}
```

### 模式 3: 错误处理

```php
$tasks = [
    create_task($operation1),
    create_task($operation2),
];

foreach ($tasks as $task) {
    try {
        $result = await($task);
        // 处理结果
    } catch (\Exception $e) {
        // 处理错误
    }
}
```

## 🎯 性能提示

1. **批量操作优先使用并发**
   ```php
   // ✅ 好 - 并发执行
   $tasks = array_map(fn($id) => create_task(fn() => fetch($id)), $ids);
   gather(...$tasks);
   
   // ❌ 差 - 顺序执行
   foreach ($ids as $id) {
       $result = await(create_task(fn() => fetch($id)));
   }
   ```

2. **合理设置超时**
   ```php
   // 为长时间操作设置合适的超时
   wait_for($operation, timeout: 30.0);
   ```

3. **使用连接池**
   ```php
   $client = new AsyncHttpClient([
       'use_connection_pool' => true,
       'max_connections' => 20,
   ]);
   ```

## 🐛 调试技巧

### 1. 使用监控工具

```php
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

$monitor = AsyncioMonitor::getInstance();
$snapshot = $monitor->snapshot();
print_r($snapshot);
```

### 2. 追踪慢任务

```php
use PfinalClub\Asyncio\Monitor\PerformanceMonitor;

$perfMonitor = PerformanceMonitor::getInstance();
$slowTasks = $perfMonitor->getSlowTasks();
```

### 3. 检查 Fiber 状态

```php
$loop = EventLoop::getInstance();
$fibers = $loop->getActiveFibers();
echo "活跃 Fiber: " . count($fibers) . "\n";
```

## 📚 更多资源

- [项目 README](../README.md) - 完整文档
- [API 文档](../docs/) - 详细 API 说明
- [性能优化指南](../docs/PERFORMANCE_OPTIMIZATION.md)
- [生产部署指南](../docs/PRODUCTION.md)

## 🤝 贡献

欢迎提交新的示例！请确保：

1. 代码清晰，注释详细
2. 包含完整的输出说明
3. 测试通过
4. 更新本 README

## 📝 许可证

MIT License - 详见 [LICENSE](../LICENSE)

