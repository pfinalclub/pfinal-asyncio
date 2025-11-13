<?php

/**
 * 数据库连接池使用示例
 * 
 * 演示:
 * 1. 初始化数据库连接池
 * 2. 执行查询操作
 * 3. 事务处理
 * 4. 并发查询
 */

require_once __DIR__ . '/../vendor/autoload.php';

use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;
use function PfinalClub\Asyncio\Database\db_init;
use function PfinalClub\Asyncio\Database\db_query;
use function PfinalClub\Asyncio\Database\db_query_one;
use function PfinalClub\Asyncio\Database\db_execute;
use function PfinalClub\Asyncio\Database\db_insert;
use function PfinalClub\Asyncio\Database\db_transaction;
use PfinalClub\Asyncio\Database\DatabasePool;

echo "=== 数据库连接池示例 ===\n\n";

// 主函数
function main() {
    echo "1. 初始化数据库连接池...\n";
    
    // 初始化连接池
    db_init([
        'dsn' => 'mysql:host=127.0.0.1;dbname=test;charset=utf8mb4',
        'username' => 'root',
        'password' => '',  // 请修改为实际密码
        'max_connections' => 10,
    ]);
    
    echo "   ✓ 连接池初始化完成\n\n";
    
    // 示例 1: 创建测试表
    echo "2. 创建测试表...\n";
    try {
        db_execute('DROP TABLE IF EXISTS users');
        db_execute('
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ');
        echo "   ✓ 表创建成功\n\n";
    } catch (\PDOException $e) {
        echo "   ✗ 表创建失败: {$e->getMessage()}\n\n";
        echo "   提示: 请确保 MySQL 服务正在运行,并且数据库配置正确\n";
        echo "   提示: 可以创建一个名为 'test' 的数据库用于测试\n\n";
        return;
    }
    
    // 示例 2: 插入数据
    echo "3. 插入测试数据...\n";
    try {
        $id1 = db_insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Alice', 'alice@example.com']);
        $id2 = db_insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Bob', 'bob@example.com']);
        $id3 = db_insert('INSERT INTO users (name, email) VALUES (?, ?)', ['Charlie', 'charlie@example.com']);
        echo "   ✓ 插入 3 条记录: ID {$id1}, {$id2}, {$id3}\n\n";
    } catch (\PDOException $e) {
        echo "   ✗ 插入失败: {$e->getMessage()}\n\n";
        return;
    }
    
    // 示例 3: 查询数据
    echo "4. 查询所有用户...\n";
    $users = db_query('SELECT * FROM users ORDER BY id');
    foreach ($users as $user) {
        echo "   - ID: {$user['id']}, Name: {$user['name']}, Email: {$user['email']}\n";
    }
    echo "\n";
    
    // 示例 4: 查询单条记录
    echo "5. 查询单个用户...\n";
    $user = db_query_one('SELECT * FROM users WHERE name = ?', ['Alice']);
    if ($user) {
        echo "   找到用户: {$user['name']} ({$user['email']})\n\n";
    }
    
    // 示例 5: 事务处理
    echo "6. 测试事务 (回滚)...\n";
    try {
        db_transaction(function($pdo) {
            // 插入一条记录
            db_execute('INSERT INTO users (name, email) VALUES (?, ?)', ['David', 'david@example.com']);
            echo "   - 插入 David\n";
            
            // 模拟错误,触发回滚
            throw new \Exception('模拟错误');
        });
    } catch (\Exception $e) {
        echo "   ✓ 事务回滚成功: {$e->getMessage()}\n";
    }
    
    // 验证回滚
    $count = db_query('SELECT COUNT(*) as count FROM users')[0]['count'];
    echo "   - 当前用户数: {$count} (应该是 3)\n\n";
    
    // 示例 6: 并发查询
    echo "7. 并发查询测试...\n";
    $startTime = microtime(true);
    
    $tasks = [];
    for ($i = 0; $i < 5; $i++) {
        $tasks[] = create_task(function() use ($i) {
            return db_query('SELECT * FROM users WHERE id = ?', [$i % 3 + 1]);
        });
    }
    
    $results = gather(...$tasks);
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);
    
    echo "   ✓ 5 个并发查询完成\n";
    echo "   - 总耗时: {$elapsed}ms\n\n";
    
    // 示例 7: 统计信息
    echo "8. 连接池统计信息:\n";
    $stats = DatabasePool::getStats();
    echo "   - 已初始化: " . ($stats['initialized'] ? '是' : '否') . "\n";
    echo "   - 有连接: " . ($stats['has_connection'] ? '是' : '否') . "\n";
    echo "   - 连接存活: " . ($stats['connection_alive'] ? '是' : '否') . "\n";
    echo "   - DSN: {$stats['config']['dsn']}\n\n";
    
    // 清理
    echo "9. 清理测试数据...\n";
    db_execute('DROP TABLE IF EXISTS users');
    echo "   ✓ 清理完成\n\n";
    
    echo "=== 示例完成 ===\n";
}

try {
    run(function() {
        main();
    });
} catch (\Throwable $e) {
    echo "错误: {$e->getMessage()}\n";
    echo "提示: 请确保 MySQL 服务正在运行,并且配置正确\n";
}

