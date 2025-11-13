<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Http\AsyncHttpClient;
use PfinalClub\Asyncio\Http\ConnectionManager;

/**
 * HTTP 客户端测试
 */
class HttpClientTest extends TestCase
{
    /**
     * 测试客户端实例化
     * 
     * @test
     */
    public function testClientInstantiation(): void
    {
        $client = new AsyncHttpClient();
        $this->assertInstanceOf(AsyncHttpClient::class, $client);
    }
    
    /**
     * 测试连接管理器获取
     * 
     * @test
     */
    public function testGetConnectionManager(): void
    {
        $client = new AsyncHttpClient(['use_connection_manager' => true]);
        
        $manager = AsyncHttpClient::getConnectionManager();
        $this->assertInstanceOf(ConnectionManager::class, $manager);
    }
    
    /**
     * 测试连接管理器统计
     * 
     * @test
     */
    public function testConnectionManagerStats(): void
    {
        $client = new AsyncHttpClient(['use_connection_manager' => true]);
        
        $stats = $client->getConnectionManagerStats();
        $this->assertIsArray($stats);
    }
    
    /**
     * 测试向后兼容的方法
     * 
     * @test
     */
    public function testBackwardCompatibility(): void
    {
        $client = new AsyncHttpClient();
        
        // getConnectionPoolStats() 应该仍然可用
        $stats = $client->getConnectionPoolStats();
        $this->assertIsArray($stats);
    }
}

