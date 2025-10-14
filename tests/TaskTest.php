<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Task;
use PfinalClub\Asyncio\TaskCancelledException;

class TaskTest extends TestCase
{
    public function testTaskCreation()
    {
        $coroutine = (function() {
            yield 1;
            return 'result';
        })();
        
        $task = new Task($coroutine, 1, 'test-task');
        
        $this->assertEquals(1, $task->getId());
        $this->assertEquals('test-task', $task->getName());
        $this->assertFalse($task->isDone());
    }
    
    public function testSetResult()
    {
        $coroutine = (function() { yield; })();
        $task = new Task($coroutine, 1, 'test');
        
        $task->setResult('success');
        
        $this->assertTrue($task->isDone());
        $this->assertEquals('success', $task->getResult());
    }
    
    public function testSetException()
    {
        $coroutine = (function() { yield; })();
        $task = new Task($coroutine, 1, 'test');
        
        $exception = new \Exception('error');
        $task->setException($exception);
        
        $this->assertTrue($task->isDone());
        $this->assertTrue($task->hasException());
        $this->assertSame($exception, $task->getException());
    }
    
    public function testCancel()
    {
        $coroutine = (function() { yield; })();
        $task = new Task($coroutine, 1, 'test');
        
        $result = $task->cancel();
        
        $this->assertTrue($result);
        $this->assertTrue($task->isDone());
        $this->assertInstanceOf(TaskCancelledException::class, $task->getException());
    }
    
    public function testDoneCallback()
    {
        $coroutine = (function() { yield; })();
        $task = new Task($coroutine, 1, 'test');
        
        $called = false;
        $task->addDoneCallback(function() use (&$called) {
            $called = true;
        });
        
        $task->setResult('done');
        
        $this->assertTrue($called);
    }
}

