# 生产环境部署指南

## 前置要求

### 系统要求

- **操作系统:** Linux (推荐) 或 macOS
- **PHP:** >= 8.1 (推荐 8.2+)
- **内存:** 最少 512MB，推荐 2GB+
- **CPU:** 根据并发量调整

### PHP 扩展

**必需:**
```bash
php -m | grep -E "(pcntl|posix)"
```

**推荐:**
```bash
- event 或 libevent (提升性能)
- opcache (提升 PHP 性能)
- apcu (缓存)
```

### Composer 依赖

```bash
composer require pfinalclub/asyncio:^2.0 --optimize-autoloader --no-dev
```

## 配置优化

### PHP 配置 (php.ini)

```ini
; 内存限制
memory_limit = 512M

; 执行时间（如果需要长时间运行）
max_execution_time = 0

; OPcache 优化
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  ; 生产环境禁用
opcache.save_comments=0

; 错误处理
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Fiber 栈大小（可选调整）
fiber.stack_size = 1048576  ; 1MB
```

### Workerman 配置

```php
use Workerman\Worker;

// 设置进程数
Worker::$daemonize = true;  // 守护进程
Worker::$pidFile = '/var/run/asyncio.pid';
Worker::$logFile = '/var/log/asyncio.log';
Worker::$stdoutFile = '/dev/null';

// 进程数量（根据 CPU 核心数调整）
$worker_count = 4;  // 或 cpu_count()
```

## 性能优化

### 1. 并发控制

限制并发任务数量：

```php
class ConcurrencyLimiter
{
    private int $max;
    private int $current = 0;
    private array $waiting = [];
    
    public function __construct(int $max = 100)
    {
        $this->max = $max;
    }
    
    public function acquire(): void
    {
        while ($this->current >= $this->max) {
            \PfinalClub\Asyncio\sleep(0.01);
        }
        $this->current++;
    }
    
    public function release(): void
    {
        $this->current--;
    }
}

// 使用
$limiter = new ConcurrencyLimiter(100);

\PfinalClub\Asyncio\run(function() use ($limiter) {
    $limiter->acquire();
    try {
        // 执行任务
    } finally {
        $limiter->release();
    }
});
```

### 2. 连接池

实现 HTTP 连接池：

```php
class HttpConnectionPool
{
    private array $connections = [];
    private int $maxConnections;
    
    public function __construct(int $max = 100)
    {
        $this->maxConnections = $max;
    }
    
    public function getClient(): AsyncHttpClient
    {
        if (empty($this->connections)) {
            return new AsyncHttpClient();
        }
        return array_pop($this->connections);
    }
    
    public function returnClient(AsyncHttpClient $client): void
    {
        if (count($this->connections) < $this->maxConnections) {
            $this->connections[] = $client;
        }
    }
}
```

### 3. 批量处理

分批处理大量任务：

```php
function process_in_batches(array $items, int $batchSize = 100): array
{
    $results = [];
    $batches = array_chunk($items, $batchSize);
    
    foreach ($batches as $batch) {
        $tasks = [];
        foreach ($batch as $item) {
            $tasks[] = \PfinalClub\Asyncio\create_task(
                fn() => process_item($item)
            );
        }
        $batchResults = \PfinalClub\Asyncio\gather(...$tasks);
        $results = array_merge($results, $batchResults);
    }
    
    return $results;
}
```

### 4. 内存管理

定期检查和清理内存：

```php
function monitor_memory(): void
{
    $threshold = 400 * 1024 * 1024; // 400MB
    
    \PfinalClub\Asyncio\create_task(function() use ($threshold) {
        while (true) {
            \PfinalClub\Asyncio\sleep(60);  // 每分钟检查
            
            $usage = memory_get_usage(true);
            if ($usage > $threshold) {
                error_log("High memory usage: " . ($usage / 1024 / 1024) . "MB");
                gc_collect_cycles();
            }
        }
    }, 'memory-monitor');
}
```

## 错误处理

### 全局错误处理器

```php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno]: $errstr in $errfile:$errline");
    return true;
});

set_exception_handler(function(\Throwable $e) {
    error_log("Uncaught exception: " . $e->getMessage());
    error_log($e->getTraceAsString());
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Fatal error: " . json_encode($error));
    }
});
```

### 任务级错误处理

```php
\PfinalClub\Asyncio\run(function() {
    $tasks = [];
    
    for ($i = 0; $i < 100; $i++) {
        $tasks[] = \PfinalClub\Asyncio\create_task(function() use ($i) {
            try {
                return process_item($i);
            } catch (\Throwable $e) {
                error_log("Task $i failed: " . $e->getMessage());
                return null;
            }
        }, "task-$i");
    }
    
    $results = \PfinalClub\Asyncio\gather(...$tasks);
    $results = array_filter($results); // 过滤失败的任务
});
```

## 监控和日志

### 集成 AsyncioMonitor

```php
use PfinalClub\Asyncio\Monitor\AsyncioMonitor;

$monitor = AsyncioMonitor::getInstance();

// 定期报告
\PfinalClub\Asyncio\create_task(function() use ($monitor) {
    while (true) {
        \PfinalClub\Asyncio\sleep(300);  // 每 5 分钟
        
        $snapshot = $monitor->snapshot();
        error_log("AsyncIO Stats: " . json_encode($snapshot));
        
        // 发送到监控系统
        send_to_metrics_system($snapshot);
    }
}, 'monitor-reporter');
```

### 结构化日志

```php
class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $log = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ];
        
        error_log(json_encode($log));
    }
}

// 使用
Logger::log('INFO', 'Task completed', ['task_id' => 123, 'duration' => 1.5]);
```

## 部署

### Systemd 服务

创建 `/etc/systemd/system/asyncio-worker.service`:

```ini
[Unit]
Description=AsyncIO Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/asyncio
ExecStart=/usr/bin/php /var/www/asyncio/worker.php
Restart=always
RestartSec=5
StandardOutput=syslog
StandardError=syslog
SyslogIdentifier=asyncio-worker

; 资源限制
LimitNOFILE=65536
LimitNPROC=4096

[Install]
WantedBy=multi-user.target
```

启动服务：

```bash
sudo systemctl daemon-reload
sudo systemctl enable asyncio-worker
sudo systemctl start asyncio-worker
sudo systemctl status asyncio-worker
```

### Supervisor 配置

创建 `/etc/supervisor/conf.d/asyncio-worker.conf`:

```ini
[program:asyncio-worker]
command=/usr/bin/php /var/www/asyncio/worker.php
directory=/var/www/asyncio
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/asyncio/worker.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
```

启动：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start asyncio-worker
```

### Docker 部署

`Dockerfile`:

```dockerfile
FROM php:8.2-cli

# 安装扩展
RUN apt-get update && apt-get install -y \
    libev-dev \
    && docker-php-ext-install pcntl posix \
    && pecl install event \
    && docker-php-ext-enable event opcache

# 复制代码
COPY . /app
WORKDIR /app

# 安装依赖
RUN composer install --no-dev --optimize-autoloader

# 运行
CMD ["php", "worker.php"]
```

`docker-compose.yml`:

```yaml
version: '3.8'

services:
  asyncio-worker:
    build: .
    restart: always
    volumes:
      - ./logs:/app/logs
    environment:
      - PHP_MEMORY_LIMIT=512M
    deploy:
      replicas: 4
      resources:
        limits:
          cpus: '2'
          memory: 512M
```

## 安全建议

### 1. 限制资源

```php
// 设置内存限制
ini_set('memory_limit', '512M');

// 设置最大执行时间（针对单个请求）
set_time_limit(30);
```

### 2. 输入验证

```php
function validate_url(string $url): bool
{
    $parsed = parse_url($url);
    
    // 只允许 http/https
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
        return false;
    }
    
    // 禁止内网地址
    $ip = gethostbyname($parsed['host'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return false;
    }
    
    return true;
}
```

### 3. SSL 验证

在生产环境启用 SSL 验证：

```php
$client = new AsyncHttpClient([
    'context' => [
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'cafile' => '/path/to/ca-bundle.crt',
        ]
    ]
]);
```

## 故障排查

### 常见问题

**1. 内存泄漏**

```bash
# 监控内存
watch -n 1 'ps aux | grep php | grep -v grep'

# 检查日志
tail -f /var/log/asyncio/worker.log | grep memory
```

**2. 进程卡死**

```bash
# 查看进程状态
ps aux | grep php

# 发送信号
kill -USR1 <pid>  # 重载
kill -TERM <pid>  # 优雅停止
```

**3. 性能问题**

```php
// 启用性能分析
$debugger = \PfinalClub\Asyncio\Debug\AsyncioDebugger::getInstance();
$debugger->enable();

// 查看报告
echo $debugger->report();
```

## 性能基准

### 预期性能

- **并发任务:** 1000+
- **任务创建:** ~3ms / 1000 tasks
- **HTTP 请求:** 100+ req/s (单进程)
- **内存使用:** ~50MB 基础 + 任务数据

### 优化目标

- CPU 使用率: < 80%
- 内存使用: < 80% 可用内存
- 响应时间: P99 < 100ms

## 维护

### 定期任务

1. **日志轮转** - 每天
2. **监控检查** - 每小时
3. **性能测试** - 每周
4. **更新依赖** - 每月

### 升级流程

1. 在测试环境验证
2. 备份当前代码
3. 滚动升级（逐个进程）
4. 监控错误日志
5. 回滚计划

---

**版本:** 2.0.0  
**日期:** 2025-01-20
