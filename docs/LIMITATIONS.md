# 限制和注意事项

## PHP Fiber 的限制

### 1. 必须在 Fiber 上下文中调用

以下函数**必须**在 Fiber 上下文中调用：

```php
- sleep()
- await()
- gather()
- wait_for()
- await_future()
```

**错误示例:**
```php
// ❌ 在非 Fiber 上下文中调用
sleep(1);  // 抛出异常或阻塞
```

**正确示例:**
```php
// ✅ 在 Fiber 上下文中调用
run(function() {
    sleep(1);  // 正确
});
```

### 2. Fiber 不能序列化

Fiber 对象不能被序列化，因此：

- 不能使用 `serialize()` / `unserialize()`
- 不能存储到会话中
- 不能通过网络传输

### 3. Fiber 的生命周期

一个 Fiber 只能被启动一次：

```php
$fiber = new Fiber(function() {
    echo "Hello";
});

$fiber->start();   // ✅ 正确
$fiber->start();   // ❌ 错误: Fiber 已启动
```

### 4. 资源限制

每个 Fiber 会消耗内存和栈空间：

- 默认栈大小: ~1MB 每个 Fiber
- 建议不要创建超过 10,000 个并发 Fiber
- 监控内存使用情况

## EventLoop 的限制

### 1. 单线程模型

EventLoop 是单线程的：

- 不支持真正的并行执行
- CPU 密集型任务会阻塞事件循环
- 只能并发处理 I/O 密集型任务

**解决方案:**
- 使用进程池处理 CPU 密集型任务
- 将计算任务分解为小块
- 考虑使用 Swoole 或 parallel 扩展

### 2. 阻塞操作

以下操作会阻塞事件循环：

```php
// ❌ 阻塞操作
file_get_contents($url);  // 阻塞 IO
sleep(1);                 // 阻塞睡眠（如果不在 Fiber 中）
mysqli_query($conn, $sql); // 阻塞数据库查询

// ✅ 非阻塞操作
$client->get($url);       // 异步 HTTP
sleep(1);                 // 异步睡眠（在 Fiber 中）
// 使用异步数据库驱动
```

### 3. 嵌套 run() 调用

不能嵌套调用 `run()`：

```php
// ❌ 错误
run(function() {
    run(function() {  // 嵌套 run
        // ...
    });
});

// ✅ 正确
run(function() {
    $task = create_task(function() {
        // ...
    });
    await($task);
});
```

## HTTP 客户端的限制

### 1. SSL 验证默认关闭

为了简化使用，SSL 证书验证默认关闭：

```php
$connection->context = [
    'ssl' => [
        'verify_peer' => false,  // 默认
        'verify_peer_name' => false,
    ]
];
```

**生产环境建议:**
启用 SSL 验证以提高安全性。

### 2. 连接池

当前实现没有连接池：

- 每个请求创建新连接
- 可能导致连接数过多
- 建议控制并发请求数量

**解决方案:**
```php
// 限制并发数
$semaphore = new Semaphore(10);  // 最多 10 个并发
```

### 3. 超时限制

HTTP 请求有默认超时：

- 默认: 30 秒
- 可配置但不能超过 PHP 的 max_execution_time

## 性能限制

### 1. 大量小任务

创建大量非常小的任务会有开销：

```php
// ❌ 低效
for ($i = 0; $i < 100000; $i++) {
    create_task(function() use ($i) {
        // 很小的任务
    });
}

// ✅ 更好
$batchSize = 100;
for ($i = 0; $i < 1000; $i++) {
    create_task(function() use ($i, $batchSize) {
        for ($j = 0; $j < $batchSize; $j++) {
            // 批量处理
        }
    });
}
```

### 2. 内存使用

监控内存使用：

```php
$monitor = AsyncioMonitor::getInstance();
$snapshot = $monitor->snapshot();
echo "内存使用: {$snapshot['memory']['current_mb']} MB\n";
```

### 3. Workerman 的限制

基于 Workerman，继承其所有限制：

- 需要 PCNTL 扩展（Linux/Mac）
- Windows 支持有限
- 某些系统调用可能不可用

## 平台限制

### 1. Windows

在 Windows 上：

- Fiber 支持完整
- Workerman 功能受限
- 建议使用 WSL2

### 2. PHP 版本

- **最低要求:** PHP 8.1
- **推荐:** PHP 8.2 或更高
- JIT 可提升性能

### 3. 扩展依赖

可选但推荐的扩展：

```
- pcntl (Linux/Mac)
- event (高性能)
- libevent
```

## 调试限制

### 1. 堆栈追踪

Fiber 的堆栈追踪可能不够完整：

```php
// 使用 AsyncioDebugger 获取更好的追踪
$debugger = AsyncioDebugger::getInstance();
$debugger->enable();
```

### 2. 断点调试

传统的断点调试在 Fiber 中可能不够直观：

- 断点可能在错误的 Fiber 中暂停
- 单步调试可能跳过 Fiber 切换
- 建议使用日志调试

## 最佳实践

### 1. 错误处理

总是捕获异常：

```php
run(function() {
    try {
        $result = risky_operation();
    } catch (\Throwable $e) {
        error_log("Error: " . $e->getMessage());
    }
});
```

### 2. 资源清理

确保资源被正确清理：

```php
run(function() {
    $resource = acquire_resource();
    try {
        // 使用资源
    } finally {
        release_resource($resource);
    }
});
```

### 3. 超时控制

对所有外部操作设置超时：

```php
try {
    $result = wait_for(external_operation(...), 10.0);
} catch (TimeoutException $e) {
    // 处理超时
}
```

### 4. 并发控制

不要创建过多并发任务：

```php
// ❌ 太多并发
$tasks = [];
for ($i = 0; $i < 10000; $i++) {
    $tasks[] = create_task(task(...));
}

// ✅ 批量处理
$batchSize = 100;
for ($i = 0; $i < 100; $i++) {
    $tasks = [];
    for ($j = 0; $j < $batchSize; $j++) {
        $tasks[] = create_task(task(...));
    }
    gather(...$tasks);
}
```

## 已知问题

### 1. 内存泄漏检测

在长时间运行的应用中监控内存：

```php
register_shutdown_function(function() {
    $peak = memory_get_peak_usage(true) / 1024 / 1024;
    error_log("Peak memory: {$peak} MB");
});
```

### 2. 信号处理

Fiber 可能影响信号处理：

- 某些信号可能被延迟
- 建议使用 Workerman 的信号处理机制

### 3. 扩展兼容性

某些 PHP 扩展可能与 Fiber 不兼容：

- 测试你的扩展
- 查看扩展文档

## 不支持的功能

以下功能在当前版本中不支持：

1. **多进程 Fiber 共享** - Fiber 不能跨进程
2. **Fiber 序列化** - 不能序列化 Fiber
3. **真正的并行执行** - 单线程模型
4. **自动重启** - 需要外部进程管理器

## 获取帮助

如果遇到限制相关的问题：

1. 查看 [示例代码](../examples/)
2. 阅读 [文档](../README.md)
3. 提交 [Issue](https://github.com/pfinalclub/asyncio/issues)

---

**版本:** 2.0.0  
**日期:** 2025-01-20
