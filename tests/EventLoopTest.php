<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\EventLoop;
use function PfinalClub\Asyncio\{create_task, sleep, run};

class EventLoopTest extends TestCase
{
    public function testGetInstance()
    {
        $loop1 = EventLoop::getInstance();
        $loop2 = EventLoop::getInstance();
        
        $this->assertSame($loop1, $loop2, '应该返回同一个实例');
    }
    
    public function testCreateFiber()
    {
        $loop = EventLoop::getInstance();
        
        $task = $loop->createFiber(function() {
            return 'test result';
        }, 'test-fiber');
        
        $this->assertNotNull($task);
        $this->assertInstanceOf(\PfinalClub\Asyncio\Task::class, $task);
        $this->assertEquals('test-fiber', $task->getName());
    }
    
    public function testRun()
    {
        $result = run(function() {
            sleep(0.01);
            return 'completed';
        });
        
        $this->assertEquals('completed', $result);
    }
    
    public function testSleep()
    {
        $start = microtime(true);
        
        run(function() {
            sleep(0.1);
        });
        
        $elapsed = microtime(true) - $start;
        $this->assertGreaterThanOrEqual(0.1, $elapsed, '应该至少睡眠 0.1 秒');
        $this->assertLessThan(0.2, $elapsed, '不应该超过 0.2 秒');
    }
    
    public function testConcurrentTasks()
    {
        $start = microtime(true);
        
        $result = run(function() {
            $task1 = create_task(function() {
                sleep(0.1);
                return 'task1';
            }, 't1');
            
            $task2 = create_task(function() {
                sleep(0.1);
                return 'task2';
            }, 't2');
            
            return \PfinalClub\Asyncio\gather($task1, $task2);
        });
        
        $elapsed = microtime(true) - $start;
        
        $this->assertEquals(['task1', 'task2'], $result);
        $this->assertLessThan(0.3, $elapsed, '并发执行应该快于顺序执行');
    }
    
    public function testAwait()
    {
        $result = run(function() {
            $task = create_task(function() {
                sleep(0.05);
                return 'awaited result';
            }, 'await-test');
            
            return \PfinalClub\Asyncio\await($task);
        });
        
        $this->assertEquals('awaited result', $result);
    }
}
