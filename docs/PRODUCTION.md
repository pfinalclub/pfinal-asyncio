# 生产环境使用指南

本文档介绍如何在生产环境中使用 PfinalClub/Asyncio 的监控、调试和 HTTP 工具。

## 📊 一、AsyncIO 监控器 (Monitor)

监控当前任务、Future、Timer 数量和系统状态。

### 基本使用

```php
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

// 获取监控器实例
$monitor = AsyncioMonitor::getInstance();

// 获取实时快照
$snapshot = $monitor->snapshot();
print_r($snapshot);

// 生成监控报告
echo $monitor->report();

// 导出为 JSON
file_put_contents('monitor.json', $monitor->toJson());
```

### 快照数据结构

```php
[
    'timestamp' => 1697234567,
    'uptime_seconds' => 3600,
    'tasks' => [
        'total' => 100,
        'pending' => 10,
        'completed' => 85,
        'failed' => 5,
    ],
    'memory' => [
        'current_mb' => 45.23,
        'peak_mb' => 58.76,
    ],
    'event_loop' => [
        'mode' => 'workerman',  // or 'lightweight'
    ],
]
```

### 实时监控

```php
// 启动实时监控（每 5 秒刷新）
$monitor->startRealTimeMonitor(5);
```

### 监控报告示例

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

⚙️  事件循环模式: workerman
```

---

## 🐛 二、AsyncIO 调试器 (Debug)

追踪 await 链路和协程调用栈，帮助调试复杂的异步流程。

### 基本使用

```php
use PfinalClub\Asyncio\Debug\AsyncioDebugger;

// 获取调试器实例
$debugger = AsyncioDebugger::getInstance();

// 启用调试（仅在开发/调试环境）
$debugger->enable();

// 运行你的异步代码...

// 查看调试报告
echo $debugger->report();

// 可视化调用链
echo $debugger->visualizeCallChain();

// 导出追踪数据
file_put_contents('traces.json', $debugger->toJson());
```

### 手动追踪

```php
function myTask(int $id): \Generator {
    global $debugger;
    
    // 记录进入
    $debugger->traceCoroutineCall("task-{$id}", "myTask({$id})");
    
    yield sleep(1);
    
    // 记录返回
    $debugger->traceCoroutineReturn("task-{$id}", "result");
    
    return "result";
}
```

### 追踪输出示例

```
[05:35:18.123]   → fetchData(1) (#fetch-1)
[05:35:19.234]     ← fetchData(1) (1110.00ms)
[05:35:19.245]   → processData(1) (#process-1)
[05:35:19.756]     ← processData(1) (511.00ms)
```

### 调用链可视化

```
🌳 协程调用链可视化:
────────────────────────────────────────────────────────────
├─→ main()
│  ├─→ fetchData(1)
│  └─← fetchData(1) (1110.00ms)
│  ├─→ processData(1)
│  └─← processData(1) (511.00ms)
└─← main() (1850.00ms)
────────────────────────────────────────────────────────────
```

### 注意事项

⚠️ **生产环境建议**：
- 调试器会产生性能开销，**不要在生产环境启用**
- 仅在开发、测试或临时排查问题时使用
- 可以通过环境变量控制：

```php
if (getenv('APP_DEBUG') === 'true') {
    $debugger->enable();
}
```

---

## 🌐 三、AsyncIO HTTP 客户端

基于 Workerman 的完整异步 HTTP 客户端，支持 GET/POST/PUT/DELETE、SSL、重定向等。

### 创建客户端

```php
use PfinalClub\Asyncio\Http\AsyncHttpClient;

$client = new AsyncHttpClient([
    'timeout' => 30,              // 超时时间（秒）
    'follow_redirects' => true,   // 自动跟随重定向
    'max_redirects' => 5,         // 最大重定向次数
    'headers' => [                // 默认请求头
        'User-Agent' => 'MyApp/1.0',
    ],
]);
```

### GET 请求

```php
function fetchApi(): \Generator {
    $client = new AsyncHttpClient();
    
    // 发送请求
    $future = $client->get('https://api.example.com/data');
    
    // 等待响应
    $response = yield $future;
    
    // 处理响应
    echo "状态码: {$response->getStatusCode()}\n";
    echo "响应体: {$response->getBody()}\n";
    
    // 解析 JSON
    $data = $response->json();
    
    return $data;
}

run(fetchApi(), useWorkerman: true);
```

### POST 请求

```php
// 表单数据
$future = $client->post('https://api.example.com/users', [
    'name' => 'John',
    'email' => 'john@example.com',
]);

// JSON 数据
$future = $client->post('https://api.example.com/users', 
    json_encode(['name' => 'John']),
    ['Content-Type' => 'application/json']
);

// 对象（自动转 JSON）
$future = $client->post('https://api.example.com/users', 
    (object)['name' => 'John']
);
```

### 其他请求方法

```php
// PUT
$future = $client->put('https://api.example.com/users/1', $data);

// DELETE
$future = $client->delete('https://api.example.com/users/1');

// 自定义方法
$future = $client->request('PATCH', 'https://api.example.com/users/1', $data);
```

### 自定义请求头

```php
$future = $client->get('https://api.example.com/data', [
    'Authorization' => 'Bearer your-token',
    'X-Custom-Header' => 'value',
]);
```

### 并发请求

```php
function fetchMultiple(): \Generator {
    $client = new AsyncHttpClient();
    
    // 创建多个并发请求
    $tasks = [];
    for ($i = 1; $i <= 5; $i++) {
        $tasks[] = create_task((function() use ($client, $i) {
            $future = $client->get("https://api.example.com/item/{$i}");
            $response = yield $future;
            return $response->json();
        })());
    }
    
    // 等待所有请求完成
    $results = yield $tasks;
    
    return $results;
}

run(fetchMultiple(), useWorkerman: true);
```

### 响应对象方法

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

### SSL/HTTPS 支持

客户端自动支持 HTTPS，无需额外配置：

```php
$future = $client->get('https://secure-api.example.com/data');
```

### 错误处理

```php
function fetchWithErrorHandling(): \Generator {
    $client = new AsyncHttpClient(['timeout' => 10]);
    
    try {
        $future = $client->get('https://api.example.com/data');
        $response = yield $future;
        
        if ($response->isSuccess()) {
            return $response->json();
        } else {
            echo "HTTP 错误: {$response->getStatusCode()}\n";
        }
    } catch (\Exception $e) {
        echo "请求失败: {$e->getMessage()}\n";
    }
    
    return null;
}
```

---

## 🚀 四、综合示例

### 监控 + HTTP

```php
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;
use PfinalClub\Asyncio\Http\AsyncHttpClient;

function monitoredHttpRequests(): \Generator {
    $monitor = AsyncioMonitor::getInstance();
    $client = new AsyncHttpClient();
    
    echo "初始状态:\n";
    echo $monitor->report();
    
    // 发送 10 个并发请求
    $tasks = [];
    for ($i = 1; $i <= 10; $i++) {
        $tasks[] = create_task((function() use ($client, $i) {
            $future = $client->get("https://httpbin.org/delay/{$i}");
            $response = yield $future;
            return $response->getStatusCode();
        })());
    }
    
    // 中途检查
    yield sleep(2);
    echo "\n进行中:\n";
    echo $monitor->report();
    
    // 等待完成
    $results = yield $tasks;
    
    echo "\n最终状态:\n";
    echo $monitor->report();
    
    return $results;
}

run(monitoredHttpRequests(), useWorkerman: true);
```

### 调试 + HTTP

```php
use PfinalClub\Asyncio\Debug\AsyncioDebugger;
use PfinalClub\Asyncio\Http\AsyncHttpClient;

$debugger = AsyncioDebugger::getInstance();
$debugger->enable();

function debuggedApiCall(): \Generator {
    global $debugger;
    $client = new AsyncHttpClient();
    
    $debugger->traceCoroutineCall('api-call', 'debuggedApiCall()');
    
    // 第一个请求
    $future1 = $client->get('https://httpbin.org/get');
    $response1 = yield $future1;
    
    // 基于第一个请求的结果，发送第二个请求
    $future2 = $client->post('https://httpbin.org/post', [
        'previous_status' => $response1->getStatusCode(),
    ]);
    $response2 = yield $future2;
    
    $debugger->traceCoroutineReturn('api-call', [
        'status1' => $response1->getStatusCode(),
        'status2' => $response2->getStatusCode(),
    ]);
    
    return [$response1, $response2];
}

run(debuggedApiCall(), useWorkerman: true);

// 显示调试报告
echo $debugger->visualizeCallChain();
```

---

## 📋 五、最佳实践

### 1. 监控最佳实践

```php
// ✅ 推荐：定期采样
$monitor = AsyncioMonitor::getInstance();

function periodicMonitoring(): \Generator {
    global $monitor;
    
    while (true) {
        yield sleep(60);  // 每分钟采样
        
        $snapshot = $monitor->snapshot();
        
        // 发送到监控系统
        sendToMonitoringSystem($snapshot);
        
        // 或记录到日志
        error_log("AsyncIO Stats: " . json_encode($snapshot));
    }
}

// 在后台运行监控任务
create_task(periodicMonitoring());
```

### 2. 调试最佳实践

```php
// ✅ 推荐：通过环境变量控制
if (getenv('ASYNCIO_DEBUG') === 'true') {
    AsyncioDebugger::getInstance()->enable();
}

// ✅ 推荐：临时调试特定函数
function problematicFunction(): \Generator {
    $debugger = AsyncioDebugger::getInstance();
    $wasEnabled = $debugger->isEnabled();
    
    // 临时启用
    if (!$wasEnabled) {
        $debugger->enable();
    }
    
    // ... 你的代码 ...
    
    // 恢复状态
    if (!$wasEnabled) {
        $debugger->disable();
    }
}
```

### 3. HTTP 客户端最佳实践

```php
// ✅ 推荐：使用连接池（复用客户端实例）
class ApiService {
    private AsyncHttpClient $client;
    
    public function __construct() {
        $this->client = new AsyncHttpClient([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'MyApp/1.0',
                'Accept' => 'application/json',
            ],
        ]);
    }
    
    public function getUser(int $id): \Generator {
        $future = $this->client->get("https://api.example.com/users/{$id}");
        $response = yield $future;
        return $response->json();
    }
}

// ✅ 推荐：统一错误处理
function safeHttpRequest(callable $requestFn): \Generator {
    try {
        $response = yield $requestFn();
        
        if (!$response->isSuccess()) {
            throw new \RuntimeException(
                "HTTP {$response->getStatusCode()}: {$response->getBody()}"
            );
        }
        
        return $response;
    } catch (\Exception $e) {
        // 记录错误
        error_log("HTTP request failed: " . $e->getMessage());
        
        // 可以重试
        throw $e;
    }
}
```

---

## 🔒 六、安全建议

1. **调试器安全**
   - ⚠️ 永远不要在生产环境启用调试器
   - 调试数据可能包含敏感信息
   - 使用环境变量严格控制

2. **HTTP 客户端安全**
   - 验证 SSL 证书（生产环境）
   - 使用环境变量存储敏感令牌
   - 设置合理的超时时间
   - 限制重定向次数

3. **监控数据**
   - 监控数据可安全用于生产
   - 定期清理历史记录
   - 注意内存使用

---

## 📚 相关文档

- [基础使用指南](../README.md)
- [API 参考](./API.md)
- [开发路线图](../ROADMAP.md)
- [性能基准测试](../benchmarks/README.md)

