<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Production\HealthCheck;
use PfinalClub\Asyncio\Production\ResourceLimits;
use PfinalClub\Asyncio\Production\GracefulShutdown;

/**
 * 生产工具测试
 */
class ProductionToolsTest extends TestCase
{
    /**
     * 测试 HealthCheck 实例化
     * 
     * @test
     */
    public function testHealthCheckInstance(): void
    {
        $health = HealthCheck::getInstance();
        $this->assertInstanceOf(HealthCheck::class, $health);
        
        // 单例测试
        $health2 = HealthCheck::getInstance();
        $this->assertSame($health, $health2);
    }
    
    /**
     * 测试健康检查执行
     * 
     * @test
     */
    public function testHealthCheckExecution(): void
    {
        $health = HealthCheck::getInstance();
        $result = $health->check();
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('timestamp', $result);
        
        // 验证默认检查项
        $this->assertArrayHasKey('php_version', $result['checks']);
        $this->assertArrayHasKey('memory', $result['checks']);
        $this->assertArrayHasKey('event_loop', $result['checks']);
    }
    
    /**
     * 测试自定义健康检查
     * 
     * @test
     */
    public function testCustomHealthCheck(): void
    {
        $health = HealthCheck::getInstance();
        
        $health->registerCheck('custom_test', function() {
            return ['status' => 'ok', 'message' => 'test'];
        });
        
        $result = $health->check();
        $this->assertArrayHasKey('custom_test', $result['checks']);
        $this->assertEquals('ok', $result['checks']['custom_test']['status']);
    }
    
    /**
     * 测试 isHealthy() 方法
     * 
     * @test
     */
    public function testIsHealthy(): void
    {
        $health = HealthCheck::getInstance();
        $this->assertIsBool($health->isHealthy());
    }
    
    /**
     * 测试 ResourceLimits 实例化
     * 
     * @test
     */
    public function testResourceLimitsInstance(): void
    {
        $limits = ResourceLimits::getInstance();
        $this->assertInstanceOf(ResourceLimits::class, $limits);
    }
    
    /**
     * 测试内存限制设置
     * 
     * @test
     */
    public function testSetMemoryLimit(): void
    {
        $limits = ResourceLimits::getInstance();
        
        // 不应该抛出异常
        $limits->setMemoryLimit(100, false);
        $this->assertTrue(true);
    }
    
    /**
     * 测试任务限制设置
     * 
     * @test
     */
    public function testSetTaskLimit(): void
    {
        $limits = ResourceLimits::getInstance();
        
        // 不应该抛出异常
        $limits->setTaskLimit(1000, false);
        $this->assertTrue(true);
    }
    
    /**
     * 测试零内存限制抛出异常
     * 
     * @test
     */
    public function testZeroMemoryLimitThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $limits = ResourceLimits::getInstance();
        $limits->setMemoryLimit(0);
    }
    
    /**
     * 测试 GracefulShutdown 实例化
     * 
     * @test
     */
    public function testGracefulShutdownInstance(): void
    {
        $shutdown = GracefulShutdown::getInstance();
        $this->assertInstanceOf(GracefulShutdown::class, $shutdown);
    }
}

