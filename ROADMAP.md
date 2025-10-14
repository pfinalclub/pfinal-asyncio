# 更新计划 (Roadmap)

## 🎯 核心定位

保持 **Python asyncio 风格的 API**，同时参考 [fhylabs/asyncio](https://github.com/fitri-hy/asyncio) 增加实用功能。

---

## 📋 版本规划

### ✅ v1.0.0 - 已完成（当前版本）

- [x] 核心事件循环（基于 Workerman）
- [x] Task 任务管理
- [x] Future 对象
- [x] 协程支持（Generator + yield from）
- [x] 基础函数：run, create_task, sleep, gather, wait_for
- [x] 异常处理：TimeoutException, TaskCancelledException
- [x] HTTP 异步客户端（基于 AsyncTcpConnection）
- [x] 单元测试
- [x] 完整文档和示例

---

### 🚀 v1.1.0 - 文件异步 I/O（计划中）

参考：fhylabs/asyncio 的 File 模块

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

**实现方式：**
- 使用 Workerman 的异步文件操作
- 或使用 PHP 的 stream_select 实现非阻塞读写

---

### 🚀 v1.2.0 - 数据库异步查询（计划中）

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

### 🚀 v1.3.0 - Worker Pool（计划中）

参考：fhylabs/asyncio 的 Worker 模块

**新增功能：**

1. **CPU 密集型任务池**
   ```php
   use function PfinalClub\Asyncio\Worker\{create_worker_pool, submit_task};
   
   $pool = create_worker_pool(workers: 4);
   
   // 提交 CPU 密集型任务
   $result = yield from submit_task($pool, function() {
       return heavy_computation();
   });
   ```

2. **并发控制**
   ```php
   // 限制同时运行的任务数
   $pool = create_worker_pool(workers: 4, maxQueue: 100);
   ```

3. **任务调度器**
   ```php
   // 定期执行任务
   schedule_worker_task($pool, function() {
       // 定期清理任务
   }, interval: 60); // 每60秒
   ```

**实现方式：**
- 使用 PHP 的 pcntl_fork 或
- 基于 Workerman 的多进程模型

---

### 🚀 v1.4.0 - 日志系统（计划中）

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

### 🚀 v1.5.0 - 工具函数（计划中）

参考：fhylabs/asyncio 的 Utils 模块

**新增功能：**

1. **async_map - 异步映射**
   ```php
   use function PfinalClub\Asyncio\Utils\async_map;
   
   $results = yield from async_map([1, 2, 3], function($n) {
       return yield from fetch_url("https://api.com/item/{$n}");
   });
   ```

2. **async_filter - 异步过滤**
   ```php
   $filtered = yield from async_filter($items, function($item) {
       return yield from check_valid($item);
   });
   ```

3. **async_reduce - 异步归约**
   ```php
   $sum = yield from async_reduce($numbers, function($acc, $n) {
       return $acc + (yield from process($n));
   }, 0);
   ```

4. **retry - 重试机制**
   ```php
   $result = yield from retry(function() {
       return yield from unstable_operation();
   }, maxAttempts: 3, delay: 1.0);
   ```

5. **throttle - 限流**
   ```php
   // 限制并发数量
   $results = yield from throttle($tasks, maxConcurrent: 5);
   ```

6. **debounce - 防抖**
   ```php
   $debounced = debounce(function() {
       yield from save_data();
   }, delay: 0.5);
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

## 🎯 设计原则

1. **保持 Python asyncio 风格**
   - API 命名和使用方式与 Python 保持一致
   - 使用 `yield from` 语法（等价于 Python 的 await）

2. **基于 Workerman**
   - 利用 Workerman 的成熟事件循环
   - 确保高性能和稳定性

3. **类型安全**
   - 使用 PHP 8.3+ 的类型系统
   - 提供完整的类型提示

4. **测试覆盖**
   - 每个新功能都要有单元测试
   - 保持测试覆盖率 > 80%

5. **文档完善**
   - 每个功能都有完整的示例
   - 中英文双语文档

---

## 📊 优先级

| 版本 | 功能 | 优先级 | 预计时间 |
|------|------|--------|---------|
| v1.1.0 | 文件异步 I/O | 🔴 高 | 1-2周 |
| v1.2.0 | 数据库异步查询 | 🔴 高 | 2-3周 |
| v1.3.0 | Worker Pool | 🟡 中 | 3-4周 |
| v1.4.0 | 日志系统 | 🟡 中 | 1-2周 |
| v1.5.0 | 工具函数 | 🟢 低 | 2-3周 |
| v2.0.0 | 高级特性 | 🟢 低 | 未定 |

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

**最后更新：** 2025-01-14

