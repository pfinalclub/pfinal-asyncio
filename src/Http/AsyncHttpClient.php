<?php

namespace PfinalClub\Asyncio\Http;

use PfinalClub\Asyncio\Future;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Timer;

/**
 * 异步 HTTP 客户端
 * 基于 Workerman 和 Fiber 提供完整的 HTTP 请求功能
 */
class AsyncHttpClient
{
    private array $defaultHeaders = [];
    private int $timeout = 30;
    private bool $followRedirects = true;
    private int $maxRedirects = 5;
    private static ?ConnectionPool $connectionPool = null;
    private bool $useConnectionPool = true;
    
    public function __construct(array $options = [])
    {
        $this->defaultHeaders = $options['headers'] ?? [
            'User-Agent' => 'PfinalClub-AsyncIO-HTTP/2.0',
            'Accept' => '*/*',
            'Connection' => 'keep-alive',
        ];
        
        $this->timeout = $options['timeout'] ?? 30;
        $this->followRedirects = $options['follow_redirects'] ?? true;
        $this->maxRedirects = $options['max_redirects'] ?? 5;
        $this->useConnectionPool = $options['use_connection_pool'] ?? true;
        
        // 初始化全局连接池
        if (self::$connectionPool === null && $this->useConnectionPool) {
            self::$connectionPool = new ConnectionPool([
                'max_connections' => $options['pool_max_connections'] ?? 10,
                'connection_timeout' => $options['pool_connection_timeout'] ?? 60.0,
                'idle_timeout' => $options['pool_idle_timeout'] ?? 30.0,
            ]);
        }
    }
    
    /**
     * 发送 GET 请求
     */
    public function get(string $url, array $headers = []): HttpResponse
    {
        return $this->request('GET', $url, null, $headers);
    }
    
    /**
     * 发送 POST 请求
     */
    public function post(string $url, $data = null, array $headers = []): HttpResponse
    {
        return $this->request('POST', $url, $data, $headers);
    }
    
    /**
     * 发送 PUT 请求
     */
    public function put(string $url, $data = null, array $headers = []): HttpResponse
    {
        return $this->request('PUT', $url, $data, $headers);
    }
    
    /**
     * 发送 DELETE 请求
     */
    public function delete(string $url, array $headers = []): HttpResponse
    {
        return $this->request('DELETE', $url, null, $headers);
    }
    
    /**
     * 获取连接池实例
     */
    public static function getConnectionPool(): ?ConnectionPool
    {
        return self::$connectionPool;
    }
    
    /**
     * 获取连接池统计信息
     */
    public function getConnectionPoolStats(): array
    {
        return self::$connectionPool ? self::$connectionPool->getStats() : [];
    }
    
    /**
     * 发送 HTTP 请求
     * 
     * 注意：此方法会暂停当前 Fiber 直到请求完成
     */
    public function request(string $method, string $url, $data = null, array $headers = [], int $redirectCount = 0): HttpResponse
    {
        $currentFiber = \Fiber::getCurrent();
        
        if (!$currentFiber) {
            throw new \RuntimeException("HTTP requests must be called within a Fiber context");
        }
        
        $future = new Future();
        
        // 解析 URL
        $urlParts = parse_url($url);
        if (!$urlParts || !isset($urlParts['host'])) {
            throw new \InvalidArgumentException("Invalid URL: {$url}");
        }
        
        $scheme = $urlParts['scheme'] ?? 'http';
        $host = $urlParts['host'];
        $port = $urlParts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $urlParts['path'] ?? '/';
        if (isset($urlParts['query'])) {
            $path .= '?' . $urlParts['query'];
        }
        
        // SSL 支持
        $address = ($scheme === 'https' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        
        // 合并请求头
        $requestHeaders = array_merge($this->defaultHeaders, $headers);
        $requestHeaders['Host'] = $host;
        
        // 处理请求体
        $body = '';
        if ($data !== null) {
            if (is_array($data)) {
                $body = http_build_query($data);
                $requestHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
            } elseif (is_string($data)) {
                $body = $data;
            } elseif (is_object($data)) {
                $body = json_encode($data);
                $requestHeaders['Content-Type'] = 'application/json';
            }
            $requestHeaders['Content-Length'] = strlen($body);
        }
        
        // 添加 Keep-Alive 支持
        $requestHeaders['Connection'] = 'keep-alive';
        $requestHeaders['Keep-Alive'] = 'timeout=30, max=100';
        
        // 构建 HTTP 请求
        $request = "{$method} {$path} HTTP/1.1\r\n";
        foreach ($requestHeaders as $key => $value) {
            $request .= "{$key}: {$value}\r\n";
        }
        $request .= "\r\n";
        if ($body) {
            $request .= $body;
        }
        
        // 创建新连接
        // 注意：由于 Workerman AsyncTcpConnection 的限制，每个请求都需要新连接
        // Keep-Alive 头会被发送，但连接复用由 TCP/IP 栈在更低层处理
        $connection = new AsyncTcpConnection($address);
        $reusedConnection = false;
        
        // 添加到连接池统计
        if ($this->useConnectionPool && self::$connectionPool) {
            self::$connectionPool->addConnection($host, $port, $connection);
        }
        
        // SSL 上下文（仅对新连接）
        if (!$reusedConnection && $scheme === 'https') {
            $connection->transport = 'ssl';
            $connection->context = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ]
            ];
        }
        
        $responseData = '';
        $responseHeaders = [];
        $statusCode = 0;
        $headersParsed = false;
        $expectedLength = null;
        $responseComplete = false;
        
        // 连接成功后发送请求
        $connection->onConnect = function($connection) use ($request) {
            $connection->send($request);
        };
        
        // 接收数据
        $connection->onMessage = function($connection, $data) use (
            &$responseData, &$responseHeaders, &$statusCode, &$headersParsed, 
            &$expectedLength, &$responseComplete, $future, $url, $method, 
            $redirectCount, $host, $port
        ) {
            $responseData .= $data;
            
            // 解析响应头
            if (!$headersParsed && strpos($responseData, "\r\n\r\n") !== false) {
                list($headersPart, $bodyPart) = explode("\r\n\r\n", $responseData, 2);
                $responseData = $bodyPart;
                
                $headerLines = explode("\r\n", $headersPart);
                $statusLine = array_shift($headerLines);
                
                if (preg_match('/HTTP\/[\d.]+\s+(\d+)/', $statusLine, $matches)) {
                    $statusCode = (int)$matches[1];
                }
                
                foreach ($headerLines as $line) {
                    if (strpos($line, ':') !== false) {
                        list($key, $value) = explode(':', $line, 2);
                        $responseHeaders[trim($key)] = trim($value);
                    }
                }
                
                // 获取预期的响应体长度
                if (isset($responseHeaders['Content-Length'])) {
                    $expectedLength = (int)$responseHeaders['Content-Length'];
                }
                
                $headersParsed = true;
            }
            
            // 检查响应是否完整
            if ($headersParsed && !$responseComplete) {
                if ($expectedLength !== null && strlen($responseData) >= $expectedLength) {
                    $responseComplete = true;
                    
                    // 创建响应对象
                    $response = new HttpResponse(
                        $statusCode,
                        $responseHeaders,
                        $responseData
                    );
                    
                    // 处理重定向
                    if ($this->followRedirects && $redirectCount < $this->maxRedirects && in_array($statusCode, [301, 302, 303, 307, 308])) {
                        if (isset($responseHeaders['Location'])) {
                            $redirectUrl = $responseHeaders['Location'];
                            
                            // 处理相对URL
                            if (!parse_url($redirectUrl, PHP_URL_SCHEME)) {
                                $urlParts = parse_url($url);
                                $scheme = $urlParts['scheme'] ?? 'http';
                                $redirectHost = $urlParts['host'];
                                $redirectPort = $urlParts['port'] ?? ($scheme === 'https' ? 443 : 80);
                                $portSuffix = (($scheme === 'http' && $redirectPort == 80) || ($scheme === 'https' && $redirectPort == 443)) ? '' : ":{$redirectPort}";
                                
                                if (strpos($redirectUrl, '/') === 0) {
                                    $redirectUrl = "{$scheme}://{$redirectHost}{$portSuffix}{$redirectUrl}";
                                } else {
                                    $path = $urlParts['path'] ?? '/';
                                    $basePath = dirname($path);
                                    $redirectUrl = "{$scheme}://{$redirectHost}{$portSuffix}{$basePath}/{$redirectUrl}";
                                }
                            }
                            
                            // 释放连接回连接池
                            if ($this->useConnectionPool && self::$connectionPool) {
                                self::$connectionPool->releaseConnection($host, $port, $connection);
                            }
                            
                            // 递归请求重定向URL
                            $this->request($redirectUrl, $method, null, [], $redirectCount + 1, $future);
                            return;
                        }
                    }
                    
                    // 释放连接回连接池（Keep-Alive）
                    if ($this->useConnectionPool && self::$connectionPool) {
                        self::$connectionPool->releaseConnection($host, $port, $connection);
                    }
                    
                    // 设置 Future 结果
                    $future->setResult($response);
                }
            }
        };
        
        // 连接关闭（兜底处理，如果服务器不支持 Keep-Alive 或响应没有 Content-Length）
        $connection->onClose = function($connection) use (
            $future, &$responseData, &$responseHeaders, &$statusCode, 
            $url, $method, $redirectCount, &$responseComplete
        ) {
            // 如果响应已经在 onMessage 中处理完成，则忽略 onClose
            if ($responseComplete) {
                return;
            }
            
            // 兜底：响应未完成，服务器关闭了连接（可能是没有 Content-Length 的响应）
            $response = new HttpResponse(
                $statusCode,
                $responseHeaders,
                $responseData
            );
            
            // 处理重定向
            if ($this->followRedirects && $redirectCount < $this->maxRedirects && in_array($statusCode, [301, 302, 303, 307, 308])) {
                if (isset($responseHeaders['Location'])) {
                    $redirectUrl = $responseHeaders['Location'];
                    
                    // 处理相对URL
                    if (!parse_url($redirectUrl, PHP_URL_SCHEME)) {
                        $urlParts = parse_url($url);
                        $scheme = $urlParts['scheme'] ?? 'http';
                        $host = $urlParts['host'];
                        $port = $urlParts['port'] ?? ($scheme === 'https' ? 443 : 80);
                        $portSuffix = (($scheme === 'http' && $port == 80) || ($scheme === 'https' && $port == 443)) ? '' : ":{$port}";
                        
                        if ($redirectUrl[0] === '/') {
                            $redirectUrl = "{$scheme}://{$host}{$portSuffix}{$redirectUrl}";
                        } else {
                            $path = $urlParts['path'] ?? '/';
                            $dir = dirname($path);
                            $redirectUrl = "{$scheme}://{$host}{$portSuffix}{$dir}/{$redirectUrl}";
                        }
                    }
                    
                    // 303 总是使用 GET
                    $newMethod = ($statusCode === 303) ? 'GET' : $method;
                    
                    try {
                        $redirectResponse = $this->request($newMethod, $redirectUrl, null, [], $redirectCount + 1);
                        $future->setResult($redirectResponse);
                    } catch (\Throwable $e) {
                        $future->setException($e);
                    }
                    return;
                }
            }
            
            $future->setResult($response);
        };
        
        // 连接错误
        $connection->onError = function($connection, $code, $msg) use ($future) {
            $future->setException(new \RuntimeException("Connection error: {$msg} (code: {$code})"));
        };
        
        // 启动连接
        $connection->connect();
        
        // 等待 Future 完成，立即恢复
        $future->addDoneCallback(function () use ($currentFiber, $future) {
            if ($currentFiber->isSuspended()) {
                try {
                    if ($future->hasException()) {
                        $currentFiber->throw($future->getException());
                    } else {
                        $currentFiber->resume($future->getResult());
                    }
                } catch (\Throwable $e) {
                    error_log("Error resuming fiber in HTTP request: " . $e->getMessage());
                }
            }
        });
        
        return \Fiber::suspend();
    }
}

/**
 * HTTP 响应类
 */
class HttpResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;
    
    public function __construct(int $statusCode, array $headers, string $body)
    {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
    }
    
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
    
    public function getHeaders(): array
    {
        return $this->headers;
    }
    
    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }
    
    public function getBody(): string
    {
        return $this->body;
    }
    
    public function json(): array
    {
        return json_decode($this->body, true) ?? [];
    }
    
    public function isSuccess(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
    
    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }
    
    public function isError(): bool
    {
        return $this->statusCode >= 400;
    }
}
