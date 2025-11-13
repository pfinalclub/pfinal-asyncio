<?php

namespace PfinalClub\Asyncio\Database;

use PfinalClub\Asyncio\Database\DatabasePool;

/**
 * 初始化数据库连接池
 * 
 * @param array $config 配置选项
 */
function db_init(array $config): void
{
    DatabasePool::init($config);
}

/**
 * 执行数据库查询
 * 
 * @param string $sql SQL 查询语句
 * @param array $params 绑定参数
 * @return array 查询结果
 */
function db_query(string $sql, array $params = []): array
{
    return DatabasePool::query($sql, $params);
}

/**
 * 查询单行
 * 
 * @param string $sql SQL 查询语句
 * @param array $params 绑定参数
 * @return array|null
 */
function db_query_one(string $sql, array $params = []): ?array
{
    return DatabasePool::queryOne($sql, $params);
}

/**
 * 查询单个值
 * 
 * @param string $sql SQL 查询语句
 * @param array $params 绑定参数
 * @return mixed
 */
function db_query_scalar(string $sql, array $params = [])
{
    return DatabasePool::queryScalar($sql, $params);
}

/**
 * 执行 INSERT/UPDATE/DELETE
 * 
 * @param string $sql SQL 语句
 * @param array $params 绑定参数
 * @return int 受影响的行数
 */
function db_execute(string $sql, array $params = []): int
{
    return DatabasePool::execute($sql, $params);
}

/**
 * 执行 INSERT 并返回 ID
 * 
 * @param string $sql SQL 语句
 * @param array $params 绑定参数
 * @return string 最后插入的 ID
 */
function db_insert(string $sql, array $params = []): string
{
    return DatabasePool::insert($sql, $params);
}

/**
 * 在事务中执行
 * 
 * @param callable $callback 回调函数
 * @return mixed
 */
function db_transaction(callable $callback)
{
    return DatabasePool::transaction($callback);
}

