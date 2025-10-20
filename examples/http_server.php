<?php
/**
 * HTTP 服务器示例
 * 
 * 展示如何使用 Workerman 创建简单的 HTTP 服务器
 * 并在处理请求时使用异步操作
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Workerman\Worker;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Protocols\Http\Response;
use function PfinalClub\Asyncio\{run, sleep, create_task, await};

echo "=== AsyncIO HTTP 服务器示例 ===\n\n";

// 创建 HTTP 服务器
$http_worker = new Worker("http://0.0.0.0:8080");
$http_worker->count = 1;

// 处理请求
$http_worker->onMessage = function(TcpConnection $connection, Request $request) {
    $path = $request->path();
    
    // 路由处理
    if ($path === '/') {
        $response = new Response(200, ['Content-Type' => 'text/html'], "
            <h1>AsyncIO HTTP 服务器</h1>
            <p>尝试访问:</p>
            <ul>
                <li><a href='/hello'>/hello</a> - 简单响应</li>
                <li><a href='/async'>/async</a> - 异步处理</li>
                <li><a href='/sleep'>/sleep</a> - 异步睡眠</li>
            </ul>
        ");
        
    } elseif ($path === '/hello') {
        $response = new Response(200, ['Content-Type' => 'text/plain'], "Hello from AsyncIO!");
        
    } elseif ($path === '/async') {
        // 使用异步任务处理
        $result = run(function() {
            $task = create_task(function() {
                sleep(0.5);  // 异步操作
                return "异步任务完成于 " . date('H:i:s');
            });
            return await($task);
        });
        
        $response = new Response(200, ['Content-Type' => 'text/plain'], $result);
        
    } elseif ($path === '/sleep') {
        // 异步睡眠（不阻塞其他请求）
        run(function() {
            sleep(2);  // 异步睡眠 2 秒
        });
        
        $response = new Response(200, ['Content-Type' => 'text/plain'], 
            "异步睡眠 2 秒后返回\n时间: " . date('H:i:s'));
        
    } else {
        $response = new Response(404, ['Content-Type' => 'text/plain'], "404 Not Found");
    }
    
    $connection->send($response);
};

echo "服务器启动在 http://0.0.0.0:8080\n";
echo "访问 http://localhost:8080 查看可用路由\n";
echo "按 Ctrl+C 停止服务器\n\n";

// 启动服务器
Worker::runAll();

