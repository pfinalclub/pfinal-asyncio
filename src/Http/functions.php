<?php

namespace PfinalClub\Asyncio\Http;

/**
 * HTTP 辅助函数 - 基于 Fiber
 */

/**
 * 异步获取 URL 内容
 * 
 * @param string $url 要获取的 URL
 * @param array $headers 自定义请求头
 * @param float $timeout 超时时间（秒）
 * @return HttpResponse 响应对象
 */
function fetch_url(string $url, array $headers = [], float $timeout = 10.0): HttpResponse
{
    $client = new AsyncHttpClient(['timeout' => $timeout]);
    return $client->get($url, $headers);
}

/**
 * 异步 GET 请求
 * 
 * @param string $url URL
 * @param array $headers 请求头
 * @param float $timeout 超时时间
 * @return HttpResponse 响应对象
 */
function http_get(string $url, array $headers = [], float $timeout = 10.0): HttpResponse
{
    $client = new AsyncHttpClient(['timeout' => $timeout]);
    return $client->get($url, $headers);
}

/**
 * 异步 POST 请求
 * 
 * @param string $url URL
 * @param mixed $data 请求数据
 * @param array $headers 请求头
 * @param float $timeout 超时时间
 * @return HttpResponse 响应对象
 */
function http_post(string $url, $data = null, array $headers = [], float $timeout = 10.0): HttpResponse
{
    $client = new AsyncHttpClient(['timeout' => $timeout]);
    return $client->post($url, $data, $headers);
}

/**
 * 异步 PUT 请求
 */
function http_put(string $url, $data = null, array $headers = [], float $timeout = 10.0): HttpResponse
{
    $client = new AsyncHttpClient(['timeout' => $timeout]);
    return $client->put($url, $data, $headers);
}

/**
 * 异步 DELETE 请求
 */
function http_delete(string $url, array $headers = [], float $timeout = 10.0): HttpResponse
{
    $client = new AsyncHttpClient(['timeout' => $timeout]);
    return $client->delete($url, $headers);
}

/**
 * 异步 HTTP 请求
 * 
 * @param string $method HTTP 方法
 * @param string $url URL
 * @param mixed $data 请求数据
 * @param array $headers 请求头
 * @param float $timeout 超时时间
 * @return HttpResponse 响应对象
 */
function http_request(string $method, string $url, $data = null, array $headers = [], float $timeout = 10.0): HttpResponse
{
    $client = new AsyncHttpClient(['timeout' => $timeout]);
    return $client->request($method, $url, $data, $headers);
}
