<?php

namespace PfinalClub\Asyncio\Cache;

use PfinalClub\Asyncio\Cache\RedisPool;

/**
 * 初始化 Redis 连接池
 * 
 * @param array $config 配置选项
 */
function redis_init(array $config = []): void
{
    RedisPool::init($config);
}

/**
 * 设置缓存
 * 
 * @param string $key
 * @param mixed $value
 * @param int|null $ttl 过期时间(秒)
 * @return bool
 */
function cache_set(string $key, $value, ?int $ttl = null): bool
{
    return RedisPool::set($key, $value, $ttl);
}

/**
 * 获取缓存
 * 
 * @param string $key
 * @return mixed
 */
function cache_get(string $key)
{
    return RedisPool::get($key);
}

/**
 * 删除缓存
 * 
 * @param string|array $keys
 * @return int
 */
function cache_delete($keys): int
{
    return RedisPool::delete($keys);
}

/**
 * 检查缓存是否存在
 * 
 * @param string $key
 * @return bool
 */
function cache_exists(string $key): bool
{
    return RedisPool::exists($key);
}

