<?php

namespace Pfinal\Async\Tests;

use PHPUnit\Framework\TestCase;
use Pfinal\Async\EventLoop;
use function Pfinal\Async\{create_task, sleep};

class EventLoopTest extends TestCase
{
    public function testGetInstance()
    {
        $loop1 = EventLoop::getInstance();
        $loop2 = EventLoop::getInstance();
        
        $this->assertSame($loop1, $loop2, '应该返回同一个实例');
    }
    
    public function testCreateTask()
    {
        $loop = EventLoop::getInstance();
        
        $task = $loop->createTask((function() {
            yield sleep(0.1);
            return 'test';
        })());
        
        $this->assertNotNull($task);
        $this->assertInstanceOf(\Pfinal\Async\Task::class, $task);
    }
}

