# AsyncIO 使用示例

展示 AsyncIO 各种功能的实用示例代码。

## 📁 示例列表

### 基础功能

| 文件 | 说明 | 关键 API |
|------|------|---------|
| `show_async.php` | **直观展示异步效果**（推荐首先查看） | 时间戳对比、并发 vs 顺序 |
| `basic_usage.php` | 基本使用：run, sleep, create_task, await | `run()`, `sleep()`, `create_task()`, `await()` |
| `concurrent.php` | 并发执行多个任务 | `gather()`, `create_task()` |
| `timeout.php` | 超时控制和任务取消 | `wait_for()`, `Task::cancel()` |

### HTTP 客户端

| 文件 | 说明 | 关键 API |
|------|------|---------|
| `http_client.php` | HTTP GET/POST 请求 | `AsyncHttpClient`, `get()`, `post()` |
| `http_concurrent.php` | 并发 HTTP 请求 | `AsyncHttpClient`, `gather()` |
| `http_server.php` | 简单的 HTTP 服务器 | 基于 Workerman |

### 监控和调试

| 文件 | 说明 | 关键 API |
|------|------|---------|
| `monitor.php` | 任务监控和状态查看 | `AsyncioMonitor` |
| `debug.php` | 调试和调用链追踪 | `AsyncioDebugger` |
| `performance.php` *(v2.0.2)* | 性能监控和慢任务追踪 | `PerformanceMonitor`, `export_metrics()` |

### 高级用法

| 文件 | 说明 | 关键 API |
|------|------|---------|
| `advanced_patterns.php` | 生产者-消费者、Future、管道 | `Future`, `create_future()`, `await_future()` |
| `real_world.php` | 完整应用示例（API 聚合服务） | 综合示例 |

## 🚀 快速开始

```bash
# 运行任何示例
php examples/basic_usage.php
php examples/concurrent.php
php examples/http_client.php
```

## 💡 常见场景

**并发下载文件** → `concurrent.php`  
**HTTP API 请求** → `http_client.php`  
**超时控制** → `timeout.php`  
**性能分析** → `performance.php`  
**生产监控** → `monitor.php`

## 📖 文档

详细文档请查看 [主 README](../README.md)
