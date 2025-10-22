# Workerman 性能优化指南

## 概述

本文档详细说明 AsyncIO v2.0.3 中对 Workerman 性能的全面优化，包括事件循环优化、多进程模式、生产工具等。

## 核心优化

### 1. 事件循环自动选择 ⚡

**背景**: Workerman 支持多种事件循环实现，性能差异巨大（10-100倍）

**实现**: 自动选择最优事件循环
- **优先级**: Ev (libev) > Event (libevent) > Select
- **自动检测**: 运行时自动检测并选择最优方案
- **友好提示**: 自动提示性能优化建议

**性能对比**:

| 事件循环 | 并发能力 | 吞吐量 (100任务) | 相对性能 | 推荐场景 |
|---------|---------|-----------------|---------|---------|
| **Select** | < 1K | 80 tasks/s | 1x (基准) | 开发测试 |
| **Event** (libevent) | > 10K | 322 tasks/s | **4x** ⚡ | 中等并发 |
| **Ev** (libev) | > 100K | 833 tasks/s | **10.4x** 🚀 | 高并发生产环境 |

**安装方法**:

```bash
# macOS
brew install libev
pecl install ev

# Ubuntu/Debian
sudo apt-get install libev-dev
pecl install ev

# CentOS/RHEL
sudo yum install libev-devel
pecl install ev
```

**API**:

```php
use PfinalClub\Asyncio\EventLoop;

// 获取当前事件循环类型
$type = EventLoop::getEventLoopType(); // 'Ev', 'Event', 或 'Select'

// 运行时自动选择并提示
run(function() {
    // ⚠️  使用 Select 事件循环 - 基础性能 (<1K 并发)
    // 💡 提示: 安装 ev 或 event 扩展可提升性能 10-100 倍
});
```

---

### 2. 多进程模式 🔄

**背景**: 单进程只能利用一个 CPU 核心，无法充分利用多核处理器

**实现**: 可选的多进程模式
- **进程管理**: 基于 Workerman 的完整进程管理
- **自动重启**: 进程崩溃自动重启
- **优雅重载**: 支持无中断重载
- **守护进程**: 可选的守护进程模式

**性能提升**:

```
单进程模式 (1 核):
- QPS: 1,000
- CPU 利用率: 12.5% (1/8核)

多进程模式 (8 核):
- QPS: 8,000  (8x 提升)
- CPU 利用率: 100% (8/8核)
```

**使用方式**:

```php
use function PfinalClub\Asyncio\Production\run_multiprocess;

run_multiprocess(function() {
    // 在每个 Worker 进程中运行的异步任务
    // ...
}, [
    'worker_count' => 8,              // Worker 进程数（默认：CPU 核心数）
    'name' => 'AsyncIO-Worker',       // Worker 名称
    'daemon' => false,                // 是否守护进程
    'log_file' => './asyncio.log',   // 日志文件
    'pid_file' => './asyncio.pid',   // PID 文件
]);
```

**控制命令**:

```bash
# 启动
php your_script.php start

# 停止
php your_script.php stop

# 重载（优雅重启）
php your_script.php reload

# 状态
php your_script.php status

# 守护进程模式
php your_script.php start -d
```

---

### 3. 并发控制 - Semaphore 🚦

**背景**: 需要限制同时执行的任务数量，防止资源耗尽

**实现**: 完整的信号量（Semaphore）实现

**使用方式**:

```php
use function PfinalClub\Asyncio\semaphore;

// 创建信号量（最多 5 个并发）
$sem = semaphore(5);

// 方式 1: 手动控制
$sem->acquire();  // 获取许可
try {
    // 执行任务
} finally {
    $sem->release();  // 释放许可
}

// 方式 2: with 语法糖
$result = $sem->with(function() {
    // 执行任务
    return '结果';
});

// 获取统计
$stats = $sem->getStats();
// ['max' => 5, 'available' => 3, 'in_use' => 2, 'waiting' => 0]
```

**示例 - 限制并发 HTTP 请求**:

```php
use function PfinalClub\Asyncio\{run, create_task, gather, semaphore};
use function PfinalClub\Asyncio\Http\http_get;

run(function() {
    $sem = semaphore(10);  // 最多 10 个并发请求
    
    $tasks = [];
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = create_task(function() use ($sem, $i) {
            return $sem->with(function() use ($i) {
                return http_get("http://example.com/api/{$i}");
            });
        });
    }
    
    $results = gather(...$tasks);
    // 100 个请求，但最多 10 个并发
});
```

---

### 4. 生产工具包 🛠️

#### 4.1 HealthCheck - 健康检查 💊

```php
use function PfinalClub\Asyncio\Production\health_check;

$hc = health_check();

// 注册自定义检查
$hc->registerCheck('database', function() {
    // 检查数据库连接
    return $pdo->query('SELECT 1')->fetch() !== false;
});

// 执行检查
$result = $hc->check();
if ($result['healthy']) {
    echo "系统健康 ✓\n";
} else {
    echo "系统异常 ✗\n";
    print_r($result['checks']);
}

// 获取详细报告
$report = $hc->getReport();
```

#### 4.2 GracefulShutdown - 优雅关闭 🛑

```php
use function PfinalClub\Asyncio\Production\graceful_shutdown;

$gs = graceful_shutdown(30);  // 30秒优雅期

// 注册信号处理
$gs->register();

// 注册关闭回调
$gs->onShutdown(function() {
    echo "正在优雅关闭...\n";
    // 保存状态、关闭连接等
});

// 运行主任务
run(function() {
    // 你的异步代码
});
```

#### 4.3 ResourceLimits - 资源限制 📏

```php
use function PfinalClub\Asyncio\Production\resource_limits;

$limits = resource_limits([
    'max_memory_mb' => 512,      // 最大内存 512MB
    'max_tasks' => 1000,         // 最多 1000 个活跃任务
    'enforce' => true,           // 强制执行（超限抛异常）
]);

// 运行主任务
run(function() {
    // 资源限制会自动检查和记录
});

// 检查是否超限
if ($limits->hasViolations()) {
    $violations = $limits->getViolations();
    echo "资源限制违规: " . count($violations) . "\n";
}

// 获取统计
$stats = $limits->getStats();
```

---

## HTTP 连接优化

### Keep-Alive 支持

虽然 Workerman 的 `AsyncTcpConnection` 限制了真正的连接复用，但我们添加了：

1. **Keep-Alive 头**: 客户端声明支持连接复用
2. **连接池统计**: 跟踪连接使用情况
3. **TCP 层优化**: 系统级 TCP 连接复用

```php
use function PfinalClub\Asyncio\Http\http_get;

// 自动添加 Keep-Alive 头
$response1 = http_get('http://example.com/api1');
$response2 = http_get('http://example.com/api2');
// TCP 层可能会复用连接
```

---

## 性能测试

### 基准测试脚本

```bash
# 事件循环性能测试
php benchmarks/06_event_loop_performance.php

# 查看完整 benchmark 报告
php benchmarks/run_all.php
```

### 预期性能提升

#### 组合优化效果

假设场景：8核 CPU，1000 并发任务

| 配置 | QPS | CPU 利用率 | 总体性能 |
|-----|-----|-----------|---------|
| 单进程 + Select | 1,000 | 12.5% (1/8核) | 1x (基准) |
| 单进程 + Ev | 10,000 | 12.5% (1/8核) | **10x** ⚡ |
| 8进程 + Select | 8,000 | 100% (8/8核) | **8x** ⚡ |
| 8进程 + Ev | **80,000** | 100% (8/8核) | **80x** 🚀🚀🚀 |

**最佳实践**: 8进程 + Ev = **80倍性能提升**！

---

## 生产部署建议

### 1. 基础配置

```php
// production.php
use function PfinalClub\Asyncio\Production\{
    run_multiprocess, 
    health_check, 
    graceful_shutdown, 
    resource_limits
};

// 配置资源限制
$limits = resource_limits([
    'max_memory_mb' => 512,
    'max_tasks' => 1000,
    'enforce' => true,
]);

// 配置优雅关闭
$shutdown = graceful_shutdown(30);
$shutdown->register();

// 配置健康检查
$health = health_check();
$health->registerCheck('custom', function() {
    // 自定义检查
    return true;
});

// 启动多进程模式
run_multiprocess(function() use ($health) {
    // 定期健康检查
    // 你的主业务逻辑
}, [
    'worker_count' => 8,
    'name' => 'MyApp',
    'daemon' => true,
    'log_file' => '/var/log/myapp/asyncio.log',
    'pid_file' => '/var/run/myapp/asyncio.pid',
]);
```

### 2. 监控集成

```php
use function PfinalClub\Asyncio\Monitor\{export_metrics, get_performance_snapshot};

// 导出 Prometheus 指标
$metrics = export_metrics('prometheus');
file_put_contents('/var/metrics/asyncio.prom', $metrics);

// 或 JSON 格式
$snapshot = get_performance_snapshot();
file_put_contents('/var/metrics/asyncio.json', json_encode($snapshot));
```

### 3. 日志和告警

```php
// 监控慢任务
use function PfinalClub\Asyncio\Monitor\set_slow_task_threshold;
set_slow_task_threshold(1.0); // 1秒

// 检查性能指标
$snapshot = get_performance_snapshot();
if ($snapshot['slow_tasks_count'] > 10) {
    // 触发告警
    error_log('WARNING: Too many slow tasks: ' . $snapshot['slow_tasks_count']);
}
```

---

## 故障排查

### 问题 1: 性能未提升

**症状**: 安装 Ev 后性能没有明显提升

**排查**:
```php
use PfinalClub\Asyncio\EventLoop;
echo EventLoop::getEventLoopType(); // 确认使用的事件循环
```

**解决**:
- 确认 Ev 扩展已正确安装: `php -m | grep ev`
- 重启 PHP 进程
- 检查 PHP 版本 >= 8.1

### 问题 2: 多进程模式无法启动

**症状**: 多进程模式启动失败

**排查**:
```bash
# 检查端口占用
netstat -anp | grep <端口>

# 检查进程
ps aux | grep asyncio

# 查看日志
tail -f /var/log/myapp/asyncio.log
```

**解决**:
- 确保端口未被占用
- 检查文件权限（log_file, pid_file）
- 确认 Workerman 版本 >= 4.1

### 问题 3: 内存持续增长

**症状**: 长时间运行后内存不断增长

**排查**:
```php
use function PfinalClub\Asyncio\Monitor\get_performance_snapshot;

$snapshot = get_performance_snapshot();
echo "活跃 Fiber 数: " . $snapshot['total_fibers'] . "\n";
echo "内存使用: " . ($snapshot['memory_usage_mb']) . " MB\n";
```

**解决**:
- 确认使用 v2.0.2+ (包含自动 Fiber 清理)
- 设置资源限制: `resource_limits(['max_memory_mb' => 512])`
- 检查是否有循环引用
- 定期重启 Worker (多进程模式)

---

## 总结

### 优化清单

- [ ] **安装 Ev 扩展** (性能提升 10x)
- [ ] **启用多进程模式** (性能提升 8x，8核CPU)
- [ ] **使用 Semaphore** 限制并发
- [ ] **配置 HealthCheck** 监控应用健康
- [ ] **启用 GracefulShutdown** 优雅关闭
- [ ] **设置 ResourceLimits** 防止资源耗尽
- [ ] **集成性能监控** (Prometheus/JSON)
- [ ] **配置日志和告警**

### 最佳性能配置

```php
// 完整示例
use function PfinalClub\Asyncio\Production\{run_multiprocess, health_check, graceful_shutdown, resource_limits};

graceful_shutdown(30)->register();
resource_limits(['max_memory_mb' => 512, 'max_tasks' => 1000])->enforce();
health_check()->registerCheck('custom', fn() => true);

run_multiprocess(function() {
    // 你的异步代码
}, [
    'worker_count' => 8,  // 或 CPU 核心数
    'daemon' => true,
]);
```

**预期效果**: 
- **80倍** 性能提升 (8核 CPU + Ev)
- **100%** CPU 利用率
- **稳定** 长时间运行
- **可观测** 完整监控

---

## 参考资料

- [Workerman 官方文档](https://www.workerman.net/)
- [libev 官网](http://software.schmorp.de/pkg/libev.html)
- [libevent 官网](https://libevent.org/)
- [AsyncIO GitHub](https://github.com/pfinalclub/asyncio)

