# AsyncIO 示例索引

所有示例按功能分类，便于查找和学习。

## 📁 示例文件列表 (11 个)

### 基础功能 (3 个)

| 文件 | 说明 | 难度 |
|------|------|------|
| `basic_usage.php` | 基础用法：run, sleep, create_task, await | ⭐ |
| `concurrent.php` | 并发执行多个任务，gather 的使用 | ⭐⭐ |
| `timeout.php` | 超时控制、任务取消、异常处理 | ⭐⭐ |

### HTTP 客户端 (3 个)

| 文件 | 说明 | 难度 |
|------|------|------|
| `http_client.php` | 基础 HTTP 请求 (GET/POST) | ⭐⭐ |
| `http_concurrent.php` | 并发 HTTP 请求、API 聚合 | ⭐⭐ |
| `http_server.php` | 简单的 HTTP 服务器示例 | ⭐⭐⭐ |

### 监控调试 (3 个)

| 文件 | 说明 | 难度 |
|------|------|------|
| `monitor.php` | AsyncioMonitor 使用，任务监控 | ⭐⭐ |
| `debug.php` | AsyncioDebugger 使用，调用链追踪 | ⭐⭐ |
| `performance.php` | 性能监控、慢任务追踪 (v2.0.2) | ⭐⭐ |

### 高级用法 (2 个)

| 文件 | 说明 | 难度 |
|------|------|------|
| `advanced_patterns.php` | Future、生产者-消费者、管道 | ⭐⭐⭐ |
| `real_world.php` | 完整应用：API 聚合服务 | ⭐⭐⭐⭐ |

## 🚀 快速开始

**新手推荐路径：**
```bash
php basic_usage.php      # 1. 了解基础概念
php concurrent.php       # 2. 学习并发控制
php http_client.php      # 3. 实际应用
php monitor.php          # 4. 生产监控
```

**常见场景查找：**
- 我想并发下载文件 → `concurrent.php`
- 我想请求 API → `http_client.php`
- 我想并发请求多个 API → `http_concurrent.php`
- 我想控制超时 → `timeout.php`
- 我想监控性能 → `performance.php`
- 我想看完整应用 → `real_world.php`

## 📊 示例统计

- **总示例数：** 11 个
- **代码行数：** ~1000+ 行
- **覆盖功能：** 基础、HTTP、监控、高级模式
- **适用场景：** 学习、开发、生产

## 💡 使用建议

1. **按顺序学习** - 从简单到复杂
2. **动手运行** - 每个示例都可以直接运行
3. **查看注释** - 代码中包含详细说明
4. **修改实验** - 尝试修改参数观察效果

## 🔗 相关文档

- [主文档](../README.md)
- [API 参考](../README.md#api-参考)
- [生产部署](../docs/PRODUCTION.md)

---

**提示:** 所有示例基于 AsyncIO v2.0.2

