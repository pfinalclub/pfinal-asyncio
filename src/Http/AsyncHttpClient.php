<?php

namespace PfinalClub\Asyncio\Http;

use PfinalClub\Asyncio\Future;
use Workerman\Connection\AsyncTcpConnection;

/**
 * 异步 HTTP 客户端
 * 基于 Workerman 提供完整的 HTTP 请求功能
 */
class AsyncHttpClient
{
    private array $defaultHeaders = [];
    private int $timeout = 30;
    private bool $followRedirects = true;
    private int $maxRedirects = 5;
    
    public function __construct(array $options = [])
    {
        $this->defaultHeaders = $options['headers'] ?? [
            'User-Agent' => 'PfinalClub-AsyncIO-HTTP/1.0',
            'Accept' => '*/*',
            'Connection' => 'close',
        ];
        
        $this->timeout = $options['timeout'] ?? 30;
        $this->followRedirects = $options['follow_redirects'] ?? true;
        $this->maxRedirects = $options['max_redirects'] ?? 5;
    }
    
    /**
     * 发送 GET 请求
     */
    public function get(string $url, array $headers = []): Future
    {
        return $this->request('GET', $url, null, $headers);
    }
    
    /**
     * 发送 POST 请求
     */
    public function post(string $url, $data = null, array $headers = []): Future
    {
        return $this->request('POST', $url, $data, $headers);
    }
    
    /**
     * 发送 PUT 请求
     */
    public function put(string $url, $data = null, array $headers = []): Future
    {
        return $this->request('PUT', $url, $data, $headers);
    }
    
    /**
     * 发送 DELETE 请求
     */
    public function delete(string $url, array $headers = []): Future
    {
        return $this->request('DELETE', $url, null, $headers);
    }
    
    /**
     * 发送 HTTP 请求
     */
    public function request(string $method, string $url, $data = null, array $headers = [], int $redirectCount = 0): Future
    {
        $future = new Future();
        
        // 解析 URL
        $urlParts = parse_url($url);
        if (!$urlParts || !isset($urlParts['host'])) {
            $future->setException(new \InvalidArgumentException("Invalid URL: {$url}"));
            return $future;
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
        
        // 构建 HTTP 请求
        $request = "{$method} {$path} HTTP/1.1\r\n";
        foreach ($requestHeaders as $key => $value) {
            $request .= "{$key}: {$value}\r\n";
        }
        $request .= "\r\n";
        if ($body) {
            $request .= $body;
        }
        
        // 创建异步连接
        $connection = new AsyncTcpConnection($address);
        
        // SSL 上下文
        if ($scheme === 'https') {
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
        
        // 连接成功
        $connection->onConnect = function($connection) use ($request) {
            $connection->send($request);
        };
        
        // 接收数据
        $connection->onMessage = function($connection, $data) use (&$responseData, &$responseHeaders, &$statusCode, &$headersParsed) {
            $responseData .= $data;
            
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
                
                $headersParsed = true;
            }
        };
        
        // 连接关闭
        $connection->onClose = function($connection) use ($future, &$responseData, &$responseHeaders, &$statusCode, $url, $method, $redirectCount) {
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
                    
                    $redirectFuture = $this->request($newMethod, $redirectUrl, null, [], $redirectCount + 1);
                    $redirectFuture->addDoneCallback(function() use ($future, $redirectFuture) {
                        try {
                            $future->setResult($redirectFuture->getResult());
                        } catch (\Throwable $e) {
                            $future->setException($e);
                        }
                    });
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
        
        return $future;
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
