<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Semaphore;

use function PfinalClub\Asyncio\run;
use function PfinalClub\Asyncio\create_task;
use function PfinalClub\Asyncio\gather;

/**
 * Semaphore 测试
 */
class SemaphoreTest extends TestCase
{
    /**
     * 测试基本的获取和释放
     * 
     * @test
     */
    public function testBasicAcquireRelease(): void
    {
        run(function() {
            $sem = new Semaphore(3);
            
            $this->assertEquals(3, $sem->getAvailable());
            
            $sem->acquire();
            $this->assertEquals(2, $sem->getAvailable());
            
            $sem->acquire();
            $this->assertEquals(1, $sem->getAvailable());
            
            $sem->release();
            $this->assertEquals(2, $sem->getAvailable());
            
            $sem->release();
            $this->assertEquals(3, $sem->getAvailable());
        });
    }
    
    /**
     * 测试计数永不为负 (P0 修复验证)
     * 
     * @test
     */
    public function testCountNeverNegative(): void
    {
        $minCount = PHP_INT_MAX;
        
        run(function() use (&$minCount) {
            $sem = new Semaphore(2);
            $tasks = [];
            
            for ($i = 0; $i < 10; $i++) {
                $tasks[] = create_task(function() use ($sem, &$minCount) {
                    $sem->acquire();
                    $minCount = min($minCount, $sem->getAvailable());
                    $sem->release();
                });
            }
            
            gather(...$tasks);
        });
        
        $this->assertGreaterThanOrEqual(0, $minCount, "Count should never be negative");
    }
    
    /**
     * 测试 with() 方法
     * 
     * @test
     */
    public function testWithMethod(): void
    {
        run(function() {
            $sem = new Semaphore(3);
            
            $result = $sem->with(function() use ($sem) {
                $this->assertEquals(2, $sem->getAvailable());
                return 'success';
            });
            
            $this->assertEquals('success', $result);
            $this->assertEquals(3, $sem->getAvailable());
        });
    }
    
    /**
     * 测试统计信息
     * 
     * @test
     */
    public function testStats(): void
    {
        run(function() {
            $sem = new Semaphore(5);
            
            $stats = $sem->getStats();
            $this->assertEquals(5, $stats['max']);
            $this->assertEquals(5, $stats['available']);
            $this->assertEquals(0, $stats['in_use']);
            $this->assertEquals(0, $stats['waiting']);
            
            $sem->acquire();
            $sem->acquire();
            
            $stats = $sem->getStats();
            $this->assertEquals(3, $stats['available']);
            $this->assertEquals(2, $stats['in_use']);
        });
    }
    
    /**
     * 测试零值抛出异常
     * 
     * @test
     */
    public function testZeroValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Semaphore(0);
    }
    
    /**
     * 测试负值抛出异常
     * 
     * @test
     */
    public function testNegativeValueThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Semaphore(-5);
    }
}

