<?php

namespace PfinalClub\Asyncio\Http;

/**
 * HTTP 辅助函数
 */

/**
 * 异步获取 URL 内容
 * 
 * @param string $url 要获取的 URL
 * @param array $headers 自定义请求头
 * @param float $timeout 超时时间（秒）
 * @return \Generator 返回响应数组
 */
function fetch_url(string $url, array $headers = [], float $timeout = 10.0): \Generator
{
    return yield from AsyncHttpClient::get($url, $headers, $timeout);
}

/**
 * 异步 GET 请求
 * 
 * @param string $url URL
 * @param array $headers 请求头
 * @param float $timeout 超时时间
 * @return \Generator
 */
function http_get(string $url, array $headers = [], float $timeout = 10.0): \Generator
{
    return yield from AsyncHttpClient::get($url, $headers, $timeout);
}

/**
 * 异步 POST 请求
 * 
 * @param string $url URL
 * @param mixed $data 请求数据
 * @param array $headers 请求头
 * @param float $timeout 超时时间
 * @return \Generator
 */
function http_post(string $url, $data = null, array $headers = [], float $timeout = 10.0): \Generator
{
    return yield from AsyncHttpClient::post($url, $data, $headers, $timeout);
}

/**
 * 异步 HTTP 请求
 * 
 * @param string $method HTTP 方法
 * @param string $url URL
 * @param mixed $data 请求数据
 * @param array $headers 请求头
 * @param float $timeout 超时时间
 * @return \Generator
 */
function http_request(string $method, string $url, $data = null, array $headers = [], float $timeout = 10.0): \Generator
{
    return yield from AsyncHttpClient::request($method, $url, $data, $headers, $timeout);
}

