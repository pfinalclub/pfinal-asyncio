<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Semaphore;
use PfinalClub\Asyncio\Production\MultiProcessMode;
use PfinalClub\Asyncio\Production\HealthCheck;
use PfinalClub\Asyncio\Production\GracefulShutdown;
use PfinalClub\Asyncio\Production\ResourceLimits;

use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;
use function PfinalClub\Asyncio\sleep;

/**
 * P0 修复测试类
 * 
 * 测试 v2.0.4 中修复的 3 个 P0 问题：
 * 1. Semaphore 计数 bug
 * 2. Production PSR-4 映射
 * 3. EventLoop 嵌套调用检测
 */
class P0FixesTest extends TestCase
{
    /**
     * 测试 Semaphore 计数不会变为负数
     * 
     * @test
     */
    public function testSemaphoreCountNeverNegative(): void
    {
        $minCount = PHP_INT_MAX;
        $allCountsValid = true;
        
        run(function() use (&$minCount, &$allCountsValid) {
            $sem = new Semaphore(3);
            $tasks = [];
            
            // 创建 20 个并发任务，只有 3 个并发许可
            for ($i = 0; $i < 20; $i++) {
                $tasks[] = create_task(function() use ($sem, &$minCount, &$allCountsValid) {
                    $sem->acquire();
                    
                    $available = $sem->getAvailable();
                    $minCount = min($minCount, $available);
                    
                    // 验证计数不为负数
                    if ($available < 0) {
                        $allCountsValid = false;
                    }
                    
                    sleep(0.01);
                    $sem->release();
                });
            }
            
            gather(...$tasks);
        });
        
        $this->assertGreaterThanOrEqual(0, $minCount, 'Semaphore count should never be negative');
        $this->assertTrue($allCountsValid, 'All semaphore counts should be valid (>= 0)');
    }
    
    /**
     * 测试 Semaphore 统计信息的准确性
     * 
     * @test
     */
    public function testSemaphoreStatsAccuracy(): void
    {
        run(function() {
            $sem = new Semaphore(5);
            
            // 初始状态
            $stats = $sem->getStats();
            $this->assertEquals(5, $stats['max']);
            $this->assertEquals(5, $stats['available']);
            $this->assertEquals(0, $stats['in_use']);
            $this->assertEquals(0, $stats['waiting']);
            
            // 获取 3 个许可
            $sem->acquire();
            $sem->acquire();
            $sem->acquire();
            
            $stats = $sem->getStats();
            $this->assertEquals(5, $stats['max']);
            $this->assertEquals(2, $stats['available']);
            $this->assertEquals(3, $stats['in_use']);
            
            // 释放 2 个许可
            $sem->release();
            $sem->release();
            
            $stats = $sem->getStats();
            $this->assertEquals(4, $stats['available']);
            $this->assertEquals(1, $stats['in_use']);
        });
    }
    
    /**
     * 测试 Semaphore 的 with() 方法自动管理许可
     * 
     * @test
     */
    public function testSemaphoreWithMethod(): void
    {
        run(function() {
            $sem = new Semaphore(3);
            
            $result = $sem->with(function() use ($sem) {
                // 在 with 块内，应该已经获取了许可
                $stats = $sem->getStats();
                $this->assertEquals(2, $stats['available']); // 3 - 1 = 2
                
                return 'success';
            });
            
            $this->assertEquals('success', $result);
            
            // with 块后，许可应该已释放
            $stats = $sem->getStats();
            $this->assertEquals(3, $stats['available']);
        });
    }
    
    /**
     * 测试 Production 命名空间的类可以正确加载
     * 
     * @test
     */
    public function testProductionClassesAutoload(): void
    {
        // 测试类存在
        $this->assertTrue(
            class_exists('PfinalClub\Asyncio\Production\MultiProcessMode'),
            'MultiProcessMode class should be autoloaded'
        );
        
        $this->assertTrue(
            class_exists('PfinalClub\Asyncio\Production\HealthCheck'),
            'HealthCheck class should be autoloaded'
        );
        
        $this->assertTrue(
            class_exists('PfinalClub\Asyncio\Production\GracefulShutdown'),
            'GracefulShutdown class should be autoloaded'
        );
        
        $this->assertTrue(
            class_exists('PfinalClub\Asyncio\Production\ResourceLimits'),
            'ResourceLimits class should be autoloaded'
        );
    }
    
    /**
     * 测试 Production 类可以正确实例化
     * 
     * @test
     */
    public function testProductionClassesInstantiation(): void
    {
        // 测试实例化
        $health = new HealthCheck();
        $this->assertInstanceOf(HealthCheck::class, $health);
        
        $limits = new ResourceLimits();
        $this->assertInstanceOf(ResourceLimits::class, $limits);
        
        $shutdown = new GracefulShutdown();
        $this->assertInstanceOf(GracefulShutdown::class, $shutdown);
    }
    
    /**
     * 测试在 Fiber 内部调用 run() 会抛出异常
     * 
     * @test
     */
    public function testNestedRunThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot call run() from within a Fiber context');
        
        run(function() {
            // 尝试在 Fiber 内部调用 run()
            run(function() {
                // 这不应该被执行
            });
        });
    }
    
    /**
     * 测试使用 create_task 的正确嵌套异步操作
     * 
     * @test
     */
    public function testCorrectNestedAsyncOperations(): void
    {
        $result = run(function() {
            $task1 = create_task(function() {
                sleep(0.01);
                return 'result1';
            });
            
            $task2 = create_task(function() {
                sleep(0.01);
                return 'result2';
            });
            
            return gather($task1, $task2);
        });
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('result1', $result[0]);
        $this->assertEquals('result2', $result[1]);
    }
    
    /**
     * 测试 Semaphore 在高并发下的正确性
     * 
     * @test
     */
    public function testSemaphoreHighConcurrency(): void
    {
        $maxConcurrent = 0;
        $currentConcurrent = 0;
        
        run(function() use (&$maxConcurrent, &$currentConcurrent) {
            $sem = new Semaphore(5);
            $tasks = [];
            
            // 创建 100 个任务
            for ($i = 0; $i < 100; $i++) {
                $tasks[] = create_task(function() use ($sem, &$maxConcurrent, &$currentConcurrent) {
                    $sem->acquire();
                    
                    $currentConcurrent++;
                    $maxConcurrent = max($maxConcurrent, $currentConcurrent);
                    
                    sleep(0.001); // 1ms
                    
                    $currentConcurrent--;
                    $sem->release();
                });
            }
            
            gather(...$tasks);
        });
        
        // 验证最大并发数不超过信号量限制
        $this->assertLessThanOrEqual(5, $maxConcurrent, 'Max concurrent tasks should not exceed semaphore limit');
        $this->assertGreaterThan(0, $maxConcurrent, 'Should have some concurrent tasks');
    }
    
    /**
     * 测试 Semaphore 异常处理（finally 块中释放许可）
     * 
     * @test
     */
    public function testSemaphoreExceptionHandling(): void
    {
        run(function() {
            $sem = new Semaphore(3);
            
            try {
                $sem->acquire();
                $this->assertEquals(2, $sem->getAvailable());
                
                throw new \Exception('Test exception');
            } catch (\Exception $e) {
                $this->assertEquals('Test exception', $e->getMessage());
            } finally {
                $sem->release();
            }
            
            // 许可应该已经释放
            $this->assertEquals(3, $sem->getAvailable());
        });
    }
    
    /**
     * 测试 Semaphore 零并发限制抛出异常
     * 
     * @test
     */
    public function testSemaphoreZeroMaxThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Semaphore max value must be positive');
        
        new Semaphore(0);
    }
    
    /**
     * 测试 Semaphore 负数并发限制抛出异常
     * 
     * @test
     */
    public function testSemaphoreNegativeMaxThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Semaphore max value must be positive');
        
        new Semaphore(-5);
    }
}

