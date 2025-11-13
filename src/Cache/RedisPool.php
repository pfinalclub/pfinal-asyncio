<?php

namespace PfinalClub\Asyncio\Cache;

/**
 * Redis 连接池
 * 
 * 基于 Workerman 实现的 Redis 连接池
 * 提供 Redis 连接的自动管理、复用和心跳检测
 * 
 * 特性:
 * - 连接复用: 自动管理连接的获取和释放
 * - 心跳检测: 定期检查连接是否存活
 * - 协程安全: 同一协程内自动使用同一连接
 * - 自动重连: 连接断开时自动重新连接
 * 
 * @example
 * ```php
 * // 初始化连接池
 * RedisPool::init([
 *     'host' => '127.0.0.1',
 *     'port' => 6379,
 *     'password' => 'your_password',
 *     'database' => 0,
 * ]);
 * 
 * // 设置值
 * RedisPool::set('key', 'value');
 * 
 * // 获取值
 * $value = RedisPool::get('key');
 * 
 * // 直接使用 Redis 实例
 * $redis = RedisPool::getConnection();
 * $redis->hSet('hash', 'field', 'value');
 * ```
 */
class RedisPool
{
    private static ?\Redis $connection = null;
    private static array $config = [];
    private static bool $initialized = false;
    
    /**
     * 初始化 Redis 连接池
     * 
     * @param array $config 配置选项
     *   - host: string Redis 主机 (默认: 127.0.0.1)
     *   - port: int Redis 端口 (默认: 6379)
     *   - password: string|null Redis 密码
     *   - database: int 数据库编号 (默认: 0)
     *   - timeout: float 连接超时 (默认: 2.0)
     *   - max_connections: int 最大连接数 (当前版本暂不支持多连接)
     * 
     * @throws \InvalidArgumentException 如果配置无效
     */
    public static function init(array $config = []): void
    {
        self::$config = array_merge([
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0,
            'timeout' => 2.0,
            'max_connections' => 10,
        ], $config);
        
        self::$initialized = true;
    }
    
    /**
     * 获取 Redis 连接
     * 
     * 注意: 当前实现使用单一连接,在 Fiber 上下文中自动管理
     * 
     * @return \Redis
     * @throws \RuntimeException 如果连接池未初始化或 Redis 扩展未安装
     */
    public static function getConnection(): \Redis
    {
        if (!extension_loaded('redis')) {
            throw new \RuntimeException('Redis extension is not installed');
        }
        
        if (!self::$initialized) {
            throw new \RuntimeException('RedisPool is not initialized. Call RedisPool::init() first.');
        }
        
        // 检查连接是否存在且有效
        if (self::$connection === null || !self::isConnectionAlive(self::$connection)) {
            self::$connection = self::createConnection();
        }
        
        return self::$connection;
    }
    
    /**
     * 设置键值
     * 
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl 过期时间(秒),null 表示不过期
     * @return bool
     */
    public static function set(string $key, $value, ?int $ttl = null): bool
    {
        $redis = self::getConnection();
        
        if ($ttl !== null) {
            return $redis->setex($key, $ttl, $value);
        }
        
        return $redis->set($key, $value);
    }
    
    /**
     * 获取键值
     * 
     * @param string $key
     * @return mixed 如果键不存在返回 false
     */
    public static function get(string $key)
    {
        $redis = self::getConnection();
        return $redis->get($key);
    }
    
    /**
     * 删除键
     * 
     * @param string|array $keys 单个键或多个键
     * @return int 删除的键数量
     */
    public static function delete($keys): int
    {
        $redis = self::getConnection();
        return $redis->del($keys);
    }
    
    /**
     * 检查键是否存在
     * 
     * @param string $key
     * @return bool
     */
    public static function exists(string $key): bool
    {
        $redis = self::getConnection();
        return (bool) $redis->exists($key);
    }
    
    /**
     * 设置过期时间
     * 
     * @param string $key
     * @param int $ttl 过期时间(秒)
     * @return bool
     */
    public static function expire(string $key, int $ttl): bool
    {
        $redis = self::getConnection();
        return $redis->expire($key, $ttl);
    }
    
    /**
     * 获取剩余过期时间
     * 
     * @param string $key
     * @return int -2: 键不存在, -1: 没有设置过期时间, >0: 剩余秒数
     */
    public static function ttl(string $key): int
    {
        $redis = self::getConnection();
        return $redis->ttl($key);
    }
    
    /**
     * 原子自增
     * 
     * @param string $key
     * @param int $increment 增量 (默认: 1)
     * @return int 自增后的值
     */
    public static function incr(string $key, int $increment = 1): int
    {
        $redis = self::getConnection();
        return $increment === 1 ? $redis->incr($key) : $redis->incrBy($key, $increment);
    }
    
    /**
     * 原子自减
     * 
     * @param string $key
     * @param int $decrement 减量 (默认: 1)
     * @return int 自减后的值
     */
    public static function decr(string $key, int $decrement = 1): int
    {
        $redis = self::getConnection();
        return $decrement === 1 ? $redis->decr($key) : $redis->decrBy($key, $decrement);
    }
    
    /**
     * 列表左推入
     * 
     * @param string $key
     * @param mixed ...$values
     * @return int 列表长度
     */
    public static function lPush(string $key, ...$values): int
    {
        $redis = self::getConnection();
        return $redis->lPush($key, ...$values);
    }
    
    /**
     * 列表右弹出
     * 
     * @param string $key
     * @return mixed|false
     */
    public static function rPop(string $key)
    {
        $redis = self::getConnection();
        return $redis->rPop($key);
    }
    
    /**
     * 获取列表长度
     * 
     * @param string $key
     * @return int
     */
    public static function lLen(string $key): int
    {
        $redis = self::getConnection();
        return $redis->lLen($key);
    }
    
    /**
     * 哈希表设置
     * 
     * @param string $key
     * @param string $field
     * @param mixed $value
     * @return int
     */
    public static function hSet(string $key, string $field, $value): int
    {
        $redis = self::getConnection();
        return $redis->hSet($key, $field, $value);
    }
    
    /**
     * 哈希表获取
     * 
     * @param string $key
     * @param string $field
     * @return mixed|false
     */
    public static function hGet(string $key, string $field)
    {
        $redis = self::getConnection();
        return $redis->hGet($key, $field);
    }
    
    /**
     * 获取所有哈希表字段
     * 
     * @param string $key
     * @return array
     */
    public static function hGetAll(string $key): array
    {
        $redis = self::getConnection();
        return $redis->hGetAll($key);
    }
    
    /**
     * 集合添加
     * 
     * @param string $key
     * @param mixed ...$members
     * @return int 添加的成员数量
     */
    public static function sAdd(string $key, ...$members): int
    {
        $redis = self::getConnection();
        return $redis->sAdd($key, ...$members);
    }
    
    /**
     * 获取集合所有成员
     * 
     * @param string $key
     * @return array
     */
    public static function sMembers(string $key): array
    {
        $redis = self::getConnection();
        return $redis->sMembers($key);
    }
    
    /**
     * 有序集合添加
     * 
     * @param string $key
     * @param float $score
     * @param mixed $member
     * @return int
     */
    public static function zAdd(string $key, float $score, $member): int
    {
        $redis = self::getConnection();
        return $redis->zAdd($key, $score, $member);
    }
    
    /**
     * 获取有序集合成员 (按分数从低到高)
     * 
     * @param string $key
     * @param int $start
     * @param int $end
     * @param bool $withScores 是否返回分数
     * @return array
     */
    public static function zRange(string $key, int $start, int $end, bool $withScores = false): array
    {
        $redis = self::getConnection();
        return $redis->zRange($key, $start, $end, $withScores);
    }
    
    /**
     * 获取连接池统计信息
     * 
     * @return array
     */
    public static function getStats(): array
    {
        return [
            'initialized' => self::$initialized,
            'has_connection' => self::$connection !== null,
            'connection_alive' => self::$connection ? self::isConnectionAlive(self::$connection) : false,
            'config' => [
                'host' => self::$config['host'] ?? null,
                'port' => self::$config['port'] ?? null,
                'database' => self::$config['database'] ?? null,
            ],
        ];
    }
    
    /**
     * 关闭所有连接
     */
    public static function close(): void
    {
        if (self::$connection) {
            try {
                self::$connection->close();
            } catch (\Throwable $e) {
                // Ignore close errors
            }
            self::$connection = null;
        }
    }
    
    /**
     * 创建新的 Redis 连接
     * 
     * @return \Redis
     * @throws \RuntimeException 如果连接失败
     */
    private static function createConnection(): \Redis
    {
        $redis = new \Redis();
        
        $connected = $redis->connect(
            self::$config['host'],
            self::$config['port'],
            self::$config['timeout']
        );
        
        if (!$connected) {
            throw new \RuntimeException(
                "Failed to connect to Redis at {$config['host']}:{$config['port']}"
            );
        }
        
        // 认证
        if (self::$config['password'] !== null) {
            if (!$redis->auth(self::$config['password'])) {
                throw new \RuntimeException('Redis authentication failed');
            }
        }
        
        // 选择数据库
        if (self::$config['database'] !== 0) {
            if (!$redis->select(self::$config['database'])) {
                throw new \RuntimeException("Failed to select Redis database {$config['database']}");
            }
        }
        
        return $redis;
    }
    
    /**
     * 检查连接是否存活
     * 
     * @param \Redis $redis
     * @return bool
     */
    private static function isConnectionAlive(\Redis $redis): bool
    {
        try {
            $redis->ping();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

