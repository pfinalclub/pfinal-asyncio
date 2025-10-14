# 生产环境三大工具

PfinalClub/Asyncio 提供三个生产环境必备工具，帮助你监控、调试和优化异步应用。

## 📊 1. AsyncIO Monitor - 监控器

### 功能
- 实时监控任务数量（总计/待处理/已完成/失败）
- 内存使用跟踪（当前/峰值）
- 运行时间统计
- 支持 JSON 导出
- 支持实时刷新

### 快速开始

```php
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

$monitor = AsyncioMonitor::getInstance();

// 获取快照
$snapshot = $monitor->snapshot();
/*
[
    'timestamp' => 1697234567,
    'uptime_seconds' => 3600,
    'tasks' => ['total' => 100, 'pending' => 10, ...],
    'memory' => ['current_mb' => 45.23, 'peak_mb' => 58.76],
]
*/

// 生成报告
echo $monitor->report();

// 导出 JSON
file_put_contents('monitor.json', $monitor->toJson());
```

### 示例输出

```
╔════════════════════════════════════════════════════════════╗
║          PfinalClub AsyncIO - 实时监控报告                 ║
╚════════════════════════════════════════════════════════════╝

⏱️  运行时间: 1h 23m 45s
📅 时间戳: 2025-10-14 05:35:18

📊 任务统计:
  ├─ 总计: 100
  ├─ 待处理: 10
  ├─ 已完成: 85
  └─ 失败: 5

💾 内存使用:
  ├─ 当前: 45.23 MB
  └─ 峰值: 58.76 MB
```

---

## 🐛 2. AsyncIO Debugger - 调试器

### 功能
- 追踪协程调用链
- 记录 yield 操作
- 捕获异常堆栈
- 可视化调用树
- 性能分析（耗时统计）

### 快速开始

```php
use PfinalClub\Asyncio\Debug\AsyncioDebugger;

$debugger = AsyncioDebugger::getInstance();
$debugger->enable();  // ⚠️ 仅开发环境使用

// 运行你的代码...

// 查看报告
echo $debugger->report();

// 可视化调用链
echo $debugger->visualizeCallChain();

// 导出追踪数据
file_put_contents('traces.json', $debugger->toJson());
```

### 示例输出

```
[05:35:18.123]   → fetchData(1) (#fetch-1)
[05:35:19.234]     ← fetchData(1) (1110.00ms)
[05:35:19.245]   → processData(1) (#process-1)
[05:35:19.756]     ← processData(1) (511.00ms)
```

**调用树可视化**:
```
🌳 协程调用链可视化:
├─→ main()
│  ├─→ fetchData(1)
│  └─← fetchData(1) (1110.00ms)
│  ├─→ processData(1)
│  └─← processData(1) (511.00ms)
└─← main() (1850.00ms)
```

### ⚠️ 重要提示

**不要在生产环境启用调试器！**
- 会产生性能开销
- 会记录大量追踪数据
- 仅用于开发和调试

```php
// ✅ 推荐：通过环境变量控制
if (getenv('APP_DEBUG') === 'true') {
    $debugger->enable();
}
```

---

## 🌐 3. AsyncIO HTTP Client - HTTP 客户端

### 功能
- 支持 GET/POST/PUT/DELETE/PATCH
- 自动 SSL/HTTPS
- 自动跟随重定向
- 并发请求
- 自定义请求头
- 超时控制
- JSON 支持

### 快速开始

```php
use PfinalClub\Asyncio\Http\AsyncHttpClient;
use function PfinalClub\Asyncio\{run, create_task};

$client = new AsyncHttpClient([
    'timeout' => 30,
    'follow_redirects' => true,
]);

// GET 请求
function simpleGet(): \Generator {
    global $client;
    
    $future = $client->get('https://httpbin.org/get');
    $response = yield $future;
    
    echo "Status: {$response->getStatusCode()}\n";
    echo "Body: {$response->getBody()}\n";
    
    return $response->json();
}

run(simpleGet(), useWorkerman: true);
```

### POST 请求

```php
// 表单数据
$future = $client->post('https://api.example.com/users', [
    'name' => 'John',
    'email' => 'john@example.com',
]);

// JSON 数据
$future = $client->post(
    'https://api.example.com/users',
    json_encode(['name' => 'John']),
    ['Content-Type' => 'application/json']
);
```

### 并发请求

```php
function concurrentRequests(): \Generator {
    global $client;
    
    // 创建多个任务
    $tasks = [];
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task((function() use ($client, $i) {
            $future = $client->get("https://api.example.com/item/{$i}");
            $response = yield $future;
            return $response->json();
        })());
    }
    
    // 等待所有完成
    $results = yield $tasks;
    
    return $results;
}

run(concurrentRequests(), useWorkerman: true);
```

### 自定义请求头

```php
$future = $client->get('https://api.example.com/data', [
    'Authorization' => 'Bearer your-token',
    'X-Custom-Header' => 'value',
]);
```

### 响应处理

```php
$response = yield $client->get('https://api.example.com/data');

// 状态码
$response->getStatusCode();  // 200

// 响应头
$response->getHeaders();               // 所有头
$response->getHeader('Content-Type');  // 单个头

// 响应体
$response->getBody();    // 原始字符串
$response->json();       // 解析为数组

// 状态判断
$response->isSuccess();   // 2xx
$response->isRedirect();  // 3xx
$response->isError();     // 4xx/5xx
```

---

## 🚀 综合使用示例

### 监控 + HTTP

```php
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;
use PfinalClub\Asyncio\Http\AsyncHttpClient;

function monitoredRequests(): \Generator {
    $monitor = AsyncioMonitor::getInstance();
    $client = new AsyncHttpClient();
    
    echo $monitor->report();
    
    // 发送请求
    $tasks = [];
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task((function() use ($client, $i) {
            $future = $client->get("https://httpbin.org/delay/1");
            return yield $future;
        })());
    }
    
    $results = yield $tasks;
    
    echo "\n最终状态:\n";
    echo $monitor->report();
    
    return $results;
}

run(monitoredRequests(), useWorkerman: true);
```

---

## 📚 完整文档

详细文档请查看：
- [生产环境使用指南](docs/PRODUCTION.md)
- [主 README](README.md)
- [开发路线图](ROADMAP.md)

## 🎯 运行示例

```bash
# 监控器
php examples/monitor_example.php start

# 调试器
php examples/debug_example.php start

# HTTP 客户端
php examples/http_client_example.php start
```

---

**提示**: 所有示例都需要在 CLI 模式下运行，并带 `start` 参数（Workerman 要求）。

