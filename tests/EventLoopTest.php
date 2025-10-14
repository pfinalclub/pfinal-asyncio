<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\EventLoop;
use function PfinalClub\Asyncio\{create_task, sleep};

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
        $this->assertInstanceOf(\PfinalClub\Asyncio\Task::class, $task);
    }
}

