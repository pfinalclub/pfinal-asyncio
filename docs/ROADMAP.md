# 更新计划 (Roadmap)

## 🎯 设计哲学

### 核心原则

1. **从核心开始，逐步扩展** - 先把调度/协程/控制流做精做透
2. **模块解耦，插件化设计** - 核心包轻量可靠，扩展功能可选插拔
3. **Python asyncio 风格** - API 设计保持与 Python 一致
4. **性能与兼容性并重** - 基于 PHP 8.1+ Fiber 原生协程
5. **测试驱动开发** - 高测试覆盖率，性能基准，监控工具

---

## 📦 架构设计

```
pfinalclub/asyncio (核心包)
    ├─ EventLoop      # 事件循环调度
    ├─ Task/Future    # 任务和异步对象
    ├─ Awaitable      # 可等待接口
    └─ 控制流         # gather/wait_for/timeout/cancel

pfinalclub/asyncio-http (HTTP 插件) - 可选
pfinalclub/asyncio-db (数据库插件) - 可选  
pfinalclub/asyncio-file (文件 I/O 插件) - 可选
pfinalclub/asyncio-worker (Worker 池插件) - 可选
```

**优势：**
- 核心包保持轻量（< 1MB）
- 用户按需引入功能
- 降低核心包的复杂度和维护成本
- 独立测试和版本管理

---

## 📋 版本规划

### ✅ v1.0.0 - 当前版本（核心已完成）

**已实现：**
- [x] 事件循环（基于 Workerman）
- [x] Task/Future 对象
- [x] 协程支持（PHP Fiber 原生实现）
- [x] 核心控制流：run, create_task, sleep, gather, wait_for
- [x] 异常处理：TimeoutException, TaskCancelledException
- [x] HTTP 客户端（实验性，后续将拆分为插件）
- [x] 基础单元测试
- [x] 完整文档

**现状评估：**
- ✅ 已迁移到 v2.0（基于 Fiber）
- ✅ 性能提升 2-3 倍
- ✅ 测试覆盖率良好
- ✅ 监控和调试工具完善

---

### ✅ v2.0.x - Fiber 迁移和性能优化（已完成）

**v2.0.0 - Fiber 迁移：**
- [x] 完全迁移到 PHP Fiber（PHP 8.1+）
- [x] 性能提升 2-3 倍
- [x] 代码简化（无需 yield）
- [x] 完整文档更新

**v2.0.1 - 性能优化：**
- [x] 完全事件驱动架构
- [x] 零延迟 Fiber 恢复
- [x] 精确定时器 (±0.1ms)

**v2.0.2 - 生产增强：**
- [x] Fiber 自动清理
- [x] HTTP 连接池
- [x] 性能监控系统

---

### ⏳ v2.1.0 - 功能扩展（计划中）

**目标：把核心做精做透**

#### 1. 完善测试体系

**单元测试覆盖：**
- [ ] Task 各种状态转换（pending/running/done/cancelled）
- [ ] 嵌套 await 场景
- [ ] 异常传播和错误堆栈
- [ ] 超时取消的边界情况
- [ ] gather 的并发控制
- [ ] 循环依赖检测

**集成测试：**
- [ ] 复杂场景组合测试
- [ ] 压力测试（1000+ 并发任务）
- [ ] 内存泄漏测试

**目标：测试覆盖率 > 85%**

#### 2. 性能基准测试

```php
// 新增 benchmarks/ 目录
benchmarks/
├── task_creation.php      # 任务创建开销
├── context_switch.php     # 上下文切换延迟
├── concurrent_tasks.php   # 并发任务吞吐量
├── memory_usage.php       # 内存使用分析
└── compare_with_others.php # 与其他库对比
```

**指标：**
- 创建 10000 个任务的时间
- 100 并发任务的调度延迟
- 内存占用（任务数量 vs 内存）
- 与原生回调、ReactPHP 对比

#### 3. 监控和调试工具

```php
use PfinalClub\Asyncio\Debug\Monitor;

$monitor = Monitor::getInstance();

// 实时监控
echo $monitor->getStats();
// Output:
// Tasks: 15 pending, 42 completed, 3 cancelled
// Avg schedule delay: 2.3ms
// Memory: 8.5MB

// 调试模式
Monitor::enableDebug();
// 详细记录每个任务的生命周期
```

**功能：**
- [ ] 任务统计（pending/completed/cancelled）
- [ ] 调度延迟分析
- [ ] 内存使用跟踪
- [ ] 慢任务预警
- [ ] 死锁检测

#### 4. 错误堆栈优化

**改进前：**
```
Exception in Task-123
Stack trace:
#0 EventLoop.php(89): ...
```

**改进后：**
```
Exception in Task-123 (my_task)
  at examples/demo.php:45 in task1()
  at examples/demo.php:67 in main()
Caused by:
  TimeoutException: Task timed out after 5.0s
Stack trace:
  #0 EventLoop.php(89): step()
  #1 Task.php(42): run()
```

**实现：**
- [ ] 保留协程调用链
- [ ] 显示用户代码位置
- [ ] 异常上下文信息

#### 5. API 完善

**新增核心 API：**
```php
// 等待第一个成功
$result = yield wait_any($task1, $task2, $task3);

// 屏蔽取消（已有，需优化）
$result = yield shield($task);

// 超时装饰器
$wrapped = with_timeout($coro, 5.0);

// 任务组管理
$group = create_task_group();
$group->add($task1);
$group->add($task2);
yield $group->wait();
```

---

### 🔧 v1.2.0 - Fiber 支持（性能优化）

**目标：引入 Fiber，提升性能**

**新增功能：**

1. **异步文件读取**
   ```php
   $content = yield from read_file('file.txt');
   ```

2. **异步文件写入**
   ```php
   yield from write_file('file.txt', $content);
   yield from append_file('file.txt', "\nNew line");
   ```

3. **流式读取（大文件）**
   ```php
   yield from stream_file('large.txt', function($chunk) {
       echo "读取: " . strlen($chunk) . " 字节\n";
   });
   ```

4. **文件监听（可选）**
   ```php
   yield from watch_file('config.txt', function($path) {
       echo "文件已修改: {$path}\n";
   });
   ```

**策略：混合模式，向后兼容**

```php
namespace PfinalClub\Asyncio\Runtime;

// 运行时检测
if (class_exists('Fiber')) {
    // PHP 8.1+ 使用 Fiber 后端
    class Runtime extends FiberRuntime {}
} else {
    // 降级到 Generator
    class Runtime extends GeneratorRuntime {}
}
```

**Fiber 优势：**
- 更低的上下文切换开销
- 更清晰的调用栈
- 更好的性能（理论上快 20-30%）

**实现计划：**
1. [ ] 抽象 Runtime 接口
2. [ ] 实现 FiberRuntime
3. [ ] 性能对比测试
4. [ ] 文档说明差异

**目标：保持 API 不变，后端可选**

---

### 🔌 v1.3.0 - 插件生态（拆分扩展功能）

**HTTP 插件：pfinalclub/asyncio-http**

```bash
composer require pfinalclub/asyncio-http
```

```php
use PfinalClub\Asyncio\Http\Client;

$client = new Client();
$response = yield from $client->get('https://api.example.com');
```

**功能：**
- [ ] HTTP/1.1 和 HTTP/2 支持
- [ ] 连接池管理
- [ ] 重试和超时策略
- [ ] 中间件支持（before/after hooks）
- [ ] WebSocket 客户端

---

**数据库插件：pfinalclub/asyncio-db**

```bash
composer require pfinalclub/asyncio-db
```

```php
use PfinalClub\Asyncio\DB\MySQL;

$db = new MySQL($config);
$results = yield from $db->query("SELECT * FROM users");
```

**功能：**
- [ ] MySQL（基于 Workerman）
- [ ] PostgreSQL 支持
- [ ] 连接池管理
- [ ] 事务支持
- [ ] 查询构建器

---

**文件插件：pfinalclub/asyncio-file**

```bash
composer require pfinalclub/asyncio-file
```

参考：fhylabs/asyncio 的 DB 模块

**新增功能：**

1. **MySQL 异步查询**
   ```php
   use PfinalClub\Asyncio\DB\MySQL;
   
   $db = new MySQL('host', 'user', 'pass', 'dbname');
   $results = yield from $db->query("SELECT * FROM users LIMIT 10");
   ```

2. **PostgreSQL 支持**
   ```php
   use PfinalClub\Asyncio\DB\PostgreSQL;
   
   $db = new PostgreSQL('host', 'user', 'pass', 'dbname');
   $results = yield from $db->query("SELECT * FROM products");
   ```

3. **连接池管理**
   ```php
   $pool = new DBPool($config, maxConnections: 10);
   $results = yield from $pool->query("SELECT ...");
   ```

4. **事务支持**
   ```php
   yield from $db->begin();
   yield from $db->query("INSERT ...");
   yield from $db->query("UPDATE ...");
   yield from $db->commit();
   ```

**实现方式：**
- 基于 Workerman MySQL 扩展
- 或使用 Swoole 的协程 MySQL 客户端（可选）

---

```php
use PfinalClub\Asyncio\File\File;

$content = yield from File::read('large.txt');
yield from File::write('output.txt', $content);
```

**功能：**
- [ ] 异步读写
- [ ] 流式处理大文件
- [ ] 文件监听（inotify/fsevents）

---

**Worker 插件：pfinalclub/asyncio-worker**

```bash
composer require pfinalclub/asyncio-worker
```

```php
use PfinalClub\Asyncio\Worker\Pool;

$pool = new Pool(workers: 4);
$result = yield from $pool->submit(function() {
    return expensive_computation();
});
```

**功能：**
- [ ] 多进程/多线程池
- [ ] 阻塞任务隔离
- [ ] 任务队列管理
- [ ] 负载均衡

**关键：不让阻塞任务堵塞主事件循环**

---

### 📊 v1.4.0 - 工具和监控（辅助功能）

**日志插件：pfinalclub/asyncio-logger**

参考：fhylabs/asyncio 的 Logger 模块

**新增功能：**

1. **统一日志接口**
   ```php
   use function PfinalClub\Asyncio\Logger\{log, debug, info, warning, error};
   
   info("用户登录", ['user_id' => 123]);
   error("数据库连接失败", ['error' => $e->getMessage()]);
   debug("调试信息", ['data' => $debugData]);
   ```

2. **异步日志写入**
   ```php
   // 非阻塞日志写入
   yield from log_async("重要操作", ['details' => '...']);
   ```

3. **多种输出方式**
   ```php
   Logger::addHandler(new FileHandler('app.log'));
   Logger::addHandler(new ConsoleHandler());
   Logger::addHandler(new SyslogHandler());
   ```

4. **日志级别过滤**
   ```php
   Logger::setLevel(LogLevel::INFO); // 只记录 INFO 及以上级别
   ```

**实现方式：**
- 实现 PSR-3 Logger 接口
- 异步批量写入日志文件

---

```bash
composer require pfinalclub/asyncio-logger
```

**功能：**
- [ ] PSR-3 兼容
- [ ] 异步批量写入
- [ ] 多种输出（文件/控制台/Syslog）

**工具函数：pfinalclub/asyncio-utils**

```bash
composer require pfinalclub/asyncio-utils
```

```php
use function PfinalClub\Asyncio\Utils\{async_map, retry, throttle};

// 异步映射
$results = yield from async_map($items, $asyncFunc);

// 重试机制
$result = yield from retry($operation, attempts: 3);

// 限流
$results = yield from throttle($tasks, concurrency: 10);
```

---

### 🚀 v2.0.0 - 高级特性（远期计划）

1. **WebSocket 支持**
   ```php
   $ws = yield from connect_websocket('wss://example.com');
   yield from $ws->send('Hello');
   $message = yield from $ws->receive();
   ```

2. **gRPC 客户端**
   ```php
   $client = new GrpcClient('service.example.com:50051');
   $response = yield from $client->call('method', $request);
   ```

3. **消息队列集成**
   ```php
   // Redis Pub/Sub
   $subscriber = yield from subscribe('channel');
   yield from $subscriber->listen(function($message) {
       // 处理消息
   });
   ```

4. **缓存支持**
   ```php
   $cache = new AsyncCache(new RedisAdapter());
   $value = yield from $cache->get('key');
   yield from $cache->set('key', $value, ttl: 3600);
   ```

5. **服务发现**
   ```php
   $service = yield from discover_service('user-service');
   $response = yield from fetch_url($service->url . '/api/users');
   ```

---

## 🎯 开发原则

### 1. 核心优先，逐步扩展

**Phase 1（v1.1-1.2）：** 核心打磨
- 完善测试（覆盖率 > 85%）
- 性能基准测试
- 监控和调试工具
- Fiber 支持

**Phase 2（v1.3-1.4）：** 生态建设
- 拆分扩展功能为独立插件
- 按需引入，降低核心复杂度

**Phase 3（v2.0+）：** 高级特性
- WebSocket、gRPC 等

### 2. 模块解耦，插件化

```
核心包（必需）          扩展包（可选）
┌────────────┐         ┌──────────────┐
│ EventLoop  │         │ HTTP Client  │
│ Task       │    →    │ Database     │
│ Future     │         │ File I/O     │
│ 控制流     │         │ Worker Pool  │
└────────────┘         └──────────────┘
```

**原则：**
- 核心包保持 < 1MB，依赖最小化
- 扩展功能独立版本管理
- 用户可以只用核心，也可以全家桶

### 3. Generator + Fiber 混合

```php
// 统一 API，后端可选
$result = yield from async_operation();

// 内部根据环境选择：
// - PHP 8.1+ 且开启 Fiber → 使用 Fiber
// - 其他情况 → 使用 Generator
```

### 4. 测试驱动开发

**测试金字塔：**
```
         /\
        /  \  E2E 测试（少量）
       /----\
      /      \ 集成测试（适量）
     /--------\
    /          \ 单元测试（大量，80%+）
   /____________\
```

**性能基准：**
- 每次 PR 必须运行基准测试
- 性能退化 > 10% 需要说明原因

### 5. 用户体验至上

**清晰的错误信息：**
```php
// ❌ 差的错误信息
Exception in Task-123

// ✅ 好的错误信息
TimeoutException in Task "fetch_user_data"
  at examples/api.php:45
  Task timed out after 5.0 seconds
  
  Call stack:
    1. fetch_user_data() at api.php:45
    2. main() at api.php:78
```

**完善的文档：**
- 快速开始（5分钟上手）
- API 参考（每个函数详细说明）
- 最佳实践（常见场景和陷阱）
- 性能优化指南

---

## 📊 开发优先级

### Q1 2025：核心完善（Critical）

| 版本 | 内容 | 优先级 | 时间 | 状态 |
|------|------|--------|------|------|
| v1.1.0 | 测试体系完善 | 🔴 Critical | 2周 | 📋 计划中 |
| v1.1.0 | 性能基准测试 | 🔴 Critical | 1周 | 📋 计划中 |
| v1.1.0 | 监控调试工具 | 🔴 Critical | 1周 | 📋 计划中 |
| v1.1.0 | 错误堆栈优化 | 🟡 High | 1周 | 📋 计划中 |

### Q2 2025：性能优化

| 版本 | 内容 | 优先级 | 时间 | 状态 |
|------|------|--------|------|------|
| v1.2.0 | Fiber 支持 | 🟡 High | 3周 | 📋 计划中 |
| v1.2.0 | 性能对比测试 | 🟡 High | 1周 | 📋 计划中 |

### Q3 2025：插件生态

| 插件 | 功能 | 优先级 | 时间 | 状态 |
|------|------|--------|------|------|
| asyncio-http | HTTP 客户端重构 | 🟡 High | 2周 | 📋 计划中 |
| asyncio-db | 数据库支持 | 🟢 Medium | 3周 | 📋 计划中 |
| asyncio-worker | Worker 池 | 🟢 Medium | 2周 | 📋 计划中 |
| asyncio-file | 文件 I/O | 🟢 Medium | 2周 | 📋 计划中 |
| asyncio-utils | 工具函数 | 🟢 Medium | 1周 | 📋 计划中 |

### Q4 2025：高级特性

| 版本 | 内容 | 优先级 | 时间 | 状态 |
|------|------|--------|------|------|
| v2.0.0 | WebSocket | 🔵 Low | TBD | 🔮 未来 |
| v2.0.0 | gRPC | 🔵 Low | TBD | 🔮 未来 |

---

## 🎯 里程碑

### Milestone 1: 核心稳定（2025 Q1）
- ✅ 测试覆盖率 > 85%
- ✅ 性能基准测试完成
- ✅ 监控工具可用
- ✅ 错误提示友好

### Milestone 2: 性能优化（2025 Q2）
- ✅ Fiber 支持
- ✅ 性能提升 20%+
- ✅ 内存优化

### Milestone 3: 生态建设（2025 Q3）
- ✅ 3+ 核心插件发布
- ✅ 文档完善
- ✅ 社区活跃

---

## 🤝 贡献

欢迎提交 Issue 和 Pull Request！

如果你想参与开发某个功能，请：
1. 先在 Issues 中讨论
2. Fork 项目并创建分支
3. 实现功能并添加测试
4. 提交 PR

---

## 📝 参考项目

- [Python asyncio](https://docs.python.org/3/library/asyncio.html) - 核心 API 参考
- [fhylabs/asyncio](https://github.com/fitri-hy/asyncio) - 功能参考
- [Workerman](https://www.workerman.net/) - 底层框架
- [ReactPHP](https://reactphp.org/) - 异步实现参考

---

## 📝 开发清单

### 当前进行中（v1.1.0）

- [ ] 编写全面的单元测试
  - [ ] Task 状态机测试
  - [ ] 嵌套 await 测试
  - [ ] 异常传播测试
  - [ ] 边界条件测试
- [ ] 性能基准测试框架
  - [ ] 任务创建开销
  - [ ] 上下文切换延迟
  - [ ] 内存使用分析
- [ ] 监控工具实现
  - [ ] 任务统计
  - [ ] 性能分析
  - [ ] 死锁检测
- [ ] 改进错误堆栈信息

### 下一步（v1.2.0）

- [ ] Fiber Runtime 抽象层设计
- [ ] FiberRuntime 实现
- [ ] 性能对比测试
- [ ] 向后兼容性验证

---

## 🤝 贡献指南

### 我们需要你的帮助！

**优先领域：**
1. 🧪 编写测试用例
2. 📊 性能基准测试
3. 📖 文档改进
4. 🐛 Bug 修复
5. 💡 功能建议

**参与方式：**
1. 查看 [Issues](https://github.com/pfinalclub/asyncio/issues)
2. 选择一个任务
3. Fork 项目并实现
4. 提交 PR

**测试要求：**
- 新功能必须有测试
- 测试覆盖率不能下降
- 性能基准测试必须通过

---

## 📚 参考资料

- [Python asyncio 文档](https://docs.python.org/3/library/asyncio.html) - API 设计参考
- [PHP Fiber RFC](https://wiki.php.net/rfc/fibers) - Fiber 技术细节
- [Workerman 文档](https://www.workerman.net/) - 事件循环实现
- [ReactPHP](https://reactphp.org/) - 异步模式参考
- [Swoole](https://www.swoole.com/) - 协程实现参考

---

**最后更新：** 2025-01-14 (重构版)

