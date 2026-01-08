<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Concurrency\TaskGroup;
use function PfinalClub\Asyncio\{run, create_task, sleep};

class TaskGroupTest extends TestCase
{
    public function testTaskGroupCreate()
    {
        run(function() {
            $group = new TaskGroup();
            $this->assertNotNull($group);
            $this->assertEquals(0, $group->getRunningTaskCount());
        });
    }
    
    public function testTaskGroupAddTask()
    {
        run(function() {
            $group = new TaskGroup();
            
            $task1 = create_task(function() {
                return 'task1';
            });
            
            $group->addTask($task1);
            $this->assertEquals(1, $group->getRunningTaskCount());
            
            $task2 = create_task(function() {
                return 'task2';
            });
            
            $group->addTask($task2);
            $this->assertEquals(2, $group->getRunningTaskCount());
        });
    }
    
    public function testTaskGroupWait()
    {
        run(function() {
            $group = new TaskGroup();
            
            $results = [];
            
            // 添加3个任务
            for ($i = 0; $i < 3; $i++) {
                $taskId = $i;
                $task = create_task(function() use ($taskId) {
                    sleep(0.01);
                    return "result{$taskId}";
                });
                $group->addTask($task);
            }
            
            $this->assertEquals(3, $group->getRunningTaskCount());
            
            // 等待所有任务完成
            $group->wait();
            
            $this->assertEquals(0, $group->getRunningTaskCount());
        });
    }
    
    public function testTaskGroupCancel()
    {
        run(function() {
            $group = new TaskGroup();
            
            // 添加2个长时间运行的任务
            for ($i = 0; $i < 2; $i++) {
                $task = create_task(function() {
                    sleep(5);
                    return 'should not complete';
                });
                $group->addTask($task);
            }
            
            $this->assertEquals(2, $group->getRunningTaskCount());
            
            // 取消组
            $group->cancel();
            
            // 等待短时间确保取消生效
            sleep(0.01);
            
            // 任务应该已经完成（被取消）
            $group->wait();
            $this->assertEquals(0, $group->getRunningTaskCount());
        });
    }
    
    public function testTaskGroupWithException()
    {
        run(function() {
            $group = new TaskGroup();
            
            // 添加一个正常完成的任务
            $task1 = create_task(function() {
                sleep(0.01);
                return 'success';
            });
            $group->addTask($task1);
            
            // 添加一个抛出异常的任务
            $task2 = create_task(function() {
                sleep(0.005);
                throw new \RuntimeException('task error');
            });
            $group->addTask($task2);
            
            $this->assertEquals(2, $group->getRunningTaskCount());
            
            // 等待所有任务完成，应该不会抛出异常（TaskGroup会处理）
            $group->wait();
            
            $this->assertEquals(0, $group->getRunningTaskCount());
        });
    }
}
