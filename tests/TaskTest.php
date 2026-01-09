<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Core\Task;
use PfinalClub\Asyncio\TaskCancelledException;
use function PfinalClub\Asyncio\{run, create_task, sleep};

class TaskTest extends TestCase
{
    public function testTaskCreation()
    {
        run(function() {
            $task = create_task(function() {
                return 'test';
            }, 'test-task');
            
            $this->assertInstanceOf(Task::class, $task);
            $this->assertEquals('test-task', $task->getName());
        });
    }
    
    public function testTaskCompletion()
    {
        run(function() {
            $task = create_task(function() {
                sleep(0.01);
                return 'completed';
            }, 'complete-task');
            
            $result = \PfinalClub\Asyncio\await($task);
            
            $this->assertTrue($task->isDone());
            $this->assertEquals('completed', $result);
            $this->assertEquals('completed', $task->getResult());
        });
    }
    
    public function testTaskException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task error');
        
        run(function() {
            $task = create_task(function() {
                throw new \RuntimeException('Task error');
            }, 'error-task');
            
            \PfinalClub\Asyncio\await($task);
        });
    }
    
    public function testTaskCancel()
    {
        run(function() {
            $task = create_task(function() {
                sleep(5);
                return 'should not complete';
            }, 'cancel-task');
            
            sleep(0.01);
            $cancelled = $task->cancel();
            
            $this->assertTrue($cancelled);
            $this->assertTrue($task->isDone());
            $this->assertTrue($task->hasException());
            $this->assertInstanceOf(TaskCancelledException::class, $task->getException());
        });
    }
    
    public function testTaskCallback()
    {
        run(function() {
            $callbackExecuted = false;
            
            $task = create_task(function() {
                sleep(0.01);
                return 'done';
            }, 'callback-task');
            
            $task->addDoneCallback(function($t) use (&$callbackExecuted) {
                $callbackExecuted = true;
            });
            
            \PfinalClub\Asyncio\await($task);
            
            // 给回调一点时间执行
            sleep(0.01);
            
            $this->assertTrue($callbackExecuted, '回调应该被执行');
        });
    }
    
    public function testTaskToString()
    {
        run(function() {
            $task = create_task(function() {
                return 'test';
            }, 'test-task');
            
            $str = (string)$task;
            $this->assertStringContainsString('test-task', $str);
        });
    }
}
