<?php

namespace Pfinal\Async\Http;

use Pfinal\Async\Future;
use Workerman\Connection\AsyncTcpConnection;

/**
 * 异步 HTTP 客户端
 * 基于 Workerman 的异步 TCP 连接实现
 */
class AsyncHttpClient
{
    /**
     * 异步 GET 请求
     * 
     * @param string $url 请求 URL
     * @param array $headers 请求头
     * @param float $timeout 超时时间（秒）
     * @return \Generator
     */
    public static function get(string $url, array $headers = [], float $timeout = 10.0): \Generator
    {
        return yield from self::request('GET', $url, null, $headers, $timeout);
    }
    
    /**
     * 异步 POST 请求
     * 
     * @param string $url 请求 URL
     * @param mixed $data 请求数据
     * @param array $headers 请求头
     * @param float $timeout 超时时间（秒）
     * @return \Generator
     */
    public static function post(string $url, $data = null, array $headers = [], float $timeout = 10.0): \Generator
    {
        return yield from self::request('POST', $url, $data, $headers, $timeout);
    }
    
    /**
     * 异步 HTTP 请求
     * 
     * @param string $method HTTP 方法
     * @param string $url 请求 URL
     * @param mixed $data 请求数据
     * @param array $headers 请求头
     * @param float $timeout 超时时间
     * @return \Generator
     */
    public static function request(string $method, string $url, $data = null, array $headers = [], float $timeout = 10.0): \Generator
    {
        $future = new Future();
        
        // 解析 URL
        $urlInfo = parse_url($url);
        if (!$urlInfo) {
            $future->setException(new \InvalidArgumentException("Invalid URL: {$url}"));
            return yield $future;
        }
        
        $scheme = $urlInfo['scheme'] ?? 'http';
        $host = $urlInfo['host'] ?? 'localhost';
        $port = $urlInfo['port'] ?? ($scheme === 'https' ? 443 : 80);
        $path = $urlInfo['path'] ?? '/';
        $query = isset($urlInfo['query']) ? '?' . $urlInfo['query'] : '';
        
        // 构建请求
        $requestPath = $path . $query;
        
        // 默认请求头
        $defaultHeaders = [
            'Host' => $host,
            'User-Agent' => 'PHP-AsyncIO/1.0',
            'Connection' => 'close',
        ];
        
        $headers = array_merge($defaultHeaders, $headers);
        
        // 处理请求体
        $body = '';
        if ($data !== null) {
            if (is_array($data)) {
                $body = json_encode($data);
                $headers['Content-Type'] = 'application/json';
            } else {
                $body = (string)$data;
            }
            $headers['Content-Length'] = strlen($body);
        }
        
        // 构建 HTTP 请求
        $request = "{$method} {$requestPath} HTTP/1.1\r\n";
        foreach ($headers as $key => $value) {
            $request .= "{$key}: {$value}\r\n";
        }
        $request .= "\r\n";
        if ($body) {
            $request .= $body;
        }
        
        // 创建异步连接
        $address = ($scheme === 'https' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $connection = new AsyncTcpConnection($address);
        
        // 设置 SSL 上下文（用于 HTTPS）
        if ($scheme === 'https') {
            $connection->transport = 'ssl';
        }
        
        $responseData = '';
        
        // 连接成功
        $connection->onConnect = function ($connection) use ($request) {
            $connection->send($request);
        };
        
        // 接收数据
        $connection->onMessage = function ($connection, $data) use (&$responseData) {
            $responseData .= $data;
        };
        
        // 连接关闭（请求完成）
        $connection->onClose = function ($connection) use ($future, &$responseData) {
            try {
                $response = self::parseResponse($responseData);
                $future->setResult($response);
            } catch (\Throwable $e) {
                $future->setException($e);
            }
        };
        
        // 连接错误
        $connection->onError = function ($connection, $code, $msg) use ($future) {
            $future->setException(new \RuntimeException("Connection error: {$msg} (code: {$code})"));
        };
        
        // 发起连接
        $connection->connect();
        
        // 设置超时
        if ($timeout > 0) {
            \Workerman\Timer::add($timeout, function () use ($connection, $future, $url, $timeout) {
                if (!$future->isDone()) {
                    $connection->close();
                    $future->setException(new \RuntimeException("Request timeout after {$timeout}s: {$url}"));
                }
            }, [], false);
        }
        
        return yield $future;
    }
    
    /**
     * 解析 HTTP 响应
     * 
     * @param string $response 原始响应数据
     * @return array
     */
    private static function parseResponse(string $response): array
    {
        if (empty($response)) {
            throw new \RuntimeException("Empty response");
        }
        
        // 分离头部和正文
        $parts = explode("\r\n\r\n", $response, 2);
        if (count($parts) < 2) {
            throw new \RuntimeException("Invalid HTTP response");
        }
        
        [$headersPart, $body] = $parts;
        
        // 解析状态行
        $lines = explode("\r\n", $headersPart);
        $statusLine = array_shift($lines);
        
        if (!preg_match('/^HTTP\/[\d.]+\s+(\d+)\s+(.*)$/', $statusLine, $matches)) {
            throw new \RuntimeException("Invalid HTTP status line: {$statusLine}");
        }
        
        $statusCode = (int)$matches[1];
        $statusText = $matches[2];
        
        // 解析响应头
        $headers = [];
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                [$key, $value] = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return [
            'status_code' => $statusCode,
            'status_text' => $statusText,
            'headers' => $headers,
            'body' => $body,
        ];
    }
}

