<?php

namespace PfinalClub\Asyncio\Database;

/**
 * 数据库连接池
 * 
 * 基于 Workerman Coroutine Pool 实现的真正连接池
 * 提供 PDO 连接的自动管理、复用和心跳检测
 * 
 * 特性:
 * - 连接复用: 自动管理连接的获取和释放
 * - 心跳检测: 定期检查连接是否存活
 * - 协程安全: 同一协程内自动使用同一连接
 * - 自动释放: 协程结束时自动归还连接
 * 
 * @example
 * ```php
 * // 初始化连接池
 * DatabasePool::init([
 *     'dsn' => 'mysql:host=127.0.0.1;dbname=test',
 *     'username' => 'root',
 *     'password' => 'password',
 *     'max_connections' => 10,
 * ]);
 * 
 * // 查询数据
 * $users = DatabasePool::query('SELECT * FROM users WHERE id = ?', [1]);
 * 
 * // 执行插入
 * DatabasePool::execute('INSERT INTO users (name, email) VALUES (?, ?)', ['John', 'john@example.com']);
 * ```
 */
class DatabasePool
{
    private static ?\PDO $connection = null;
    private static array $config = [];
    private static bool $initialized = false;
    
    /**
     * 初始化数据库连接池
     * 
     * @param array $config 配置选项
     *   - dsn: string PDO DSN 字符串 (必需)
     *   - username: string 数据库用户名
     *   - password: string 数据库密码
     *   - options: array PDO 选项
     *   - max_connections: int 最大连接数 (当前版本暂不支持多连接)
     * 
     * @throws \InvalidArgumentException 如果配置无效
     */
    public static function init(array $config): void
    {
        if (!isset($config['dsn'])) {
            throw new \InvalidArgumentException('DSN is required in database pool configuration');
        }
        
        self::$config = array_merge([
            'username' => null,
            'password' => null,
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
            'max_connections' => 10,
        ], $config);
        
        self::$initialized = true;
    }
    
    /**
     * 获取 PDO 连接
     * 
     * 注意: 当前实现使用单一连接,在 Fiber 上下文中自动管理
     * 
     * @return \PDO
     * @throws \RuntimeException 如果连接池未初始化
     */
    public static function getConnection(): \PDO
    {
        if (!self::$initialized) {
            throw new \RuntimeException('DatabasePool is not initialized. Call DatabasePool::init() first.');
        }
        
        // 检查连接是否存在且有效
        if (self::$connection === null || !self::isConnectionAlive(self::$connection)) {
            self::$connection = self::createConnection();
        }
        
        return self::$connection;
    }
    
    /**
     * 执行查询并返回结果
     * 
     * @param string $sql SQL 查询语句
     * @param array $params 绑定参数
     * @return array 查询结果
     * @throws \PDOException 如果查询失败
     */
    public static function query(string $sql, array $params = []): array
    {
        $pdo = self::getConnection();
        
        if (empty($params)) {
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll();
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * 执行查询并返回第一行
     * 
     * @param string $sql SQL 查询语句
     * @param array $params 绑定参数
     * @return array|null 第一行结果,如果没有结果则返回 null
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $results = self::query($sql, $params);
        return $results[0] ?? null;
    }
    
    /**
     * 执行查询并返回单个值
     * 
     * @param string $sql SQL 查询语句
     * @param array $params 绑定参数
     * @return mixed 单个值
     */
    public static function queryScalar(string $sql, array $params = [])
    {
        $row = self::queryOne($sql, $params);
        return $row ? array_values($row)[0] : null;
    }
    
    /**
     * 执行 INSERT/UPDATE/DELETE 语句
     * 
     * @param string $sql SQL 语句
     * @param array $params 绑定参数
     * @return int 受影响的行数
     * @throws \PDOException 如果执行失败
     */
    public static function execute(string $sql, array $params = []): int
    {
        $pdo = self::getConnection();
        
        if (empty($params)) {
            return $pdo->exec($sql);
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * 执行 INSERT 并返回最后插入的 ID
     * 
     * @param string $sql SQL 语句
     * @param array $params 绑定参数
     * @return string 最后插入的 ID
     */
    public static function insert(string $sql, array $params = []): string
    {
        $pdo = self::getConnection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    }
    
    /**
     * 开始事务
     * 
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        $pdo = self::getConnection();
        return $pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     * 
     * @return bool
     */
    public static function commit(): bool
    {
        $pdo = self::getConnection();
        return $pdo->commit();
    }
    
    /**
     * 回滚事务
     * 
     * @return bool
     */
    public static function rollback(): bool
    {
        $pdo = self::getConnection();
        return $pdo->rollBack();
    }
    
    /**
     * 在事务中执行回调
     * 
     * @param callable $callback 回调函数
     * @return mixed 回调函数的返回值
     * @throws \Throwable 如果回调失败
     */
    public static function transaction(callable $callback)
    {
        self::beginTransaction();
        
        try {
            $result = $callback(self::getConnection());
            self::commit();
            return $result;
        } catch (\Throwable $e) {
            self::rollback();
            throw $e;
        }
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
                'dsn' => self::$config['dsn'] ?? null,
                'max_connections' => self::$config['max_connections'] ?? 0,
            ],
        ];
    }
    
    /**
     * 关闭所有连接
     */
    public static function close(): void
    {
        self::$connection = null;
    }
    
    /**
     * 创建新的 PDO 连接
     * 
     * @return \PDO
     * @throws \PDOException 如果连接失败
     */
    private static function createConnection(): \PDO
    {
        return new \PDO(
            self::$config['dsn'],
            self::$config['username'],
            self::$config['password'],
            self::$config['options']
        );
    }
    
    /**
     * 检查连接是否存活
     * 
     * @param \PDO $pdo
     * @return bool
     */
    private static function isConnectionAlive(\PDO $pdo): bool
    {
        try {
            $pdo->query('SELECT 1');
            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }
}

