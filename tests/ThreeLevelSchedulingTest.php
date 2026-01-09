<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Core\EventLoop;
use PfinalClub\Asyncio\Core\PriorityScheduler;
use function PfinalClub\Asyncio\{run, create_task, sleep};

/**
 * 三级调度模型集成测试
 * 测试SYSTEM、CONTROL和WORK级任务之间的交互
 */
class ThreeLevelSchedulingTest extends TestCase
{
    /**
     * 测试不同优先级任务的执行顺序
     */
    public function testPriorityExecutionOrder()
    {
        $executionOrder = [];
        
        run(function() use (&$executionOrder) {
            $eventLoop = EventLoop::getInstance();
            $scheduler = $eventLoop->getScheduler();
            
            // 创建WORK级任务
            $workTask1 = $scheduler->schedule(function() use (&$executionOrder) {
                sleep(0.05);
                $executionOrder[] = 'work1';
                return 'work1-result';
            }, PriorityScheduler::PRIORITY_WORK, 'work1');
            
            $workTask2 = $scheduler->schedule(function() use (&$executionOrder) {
                sleep(0.05);
                $executionOrder[] = 'work2';
                return 'work2-result';
            }, PriorityScheduler::PRIORITY_WORK, 'work2');
            
            // 创建CONTROL级任务
            $controlTask = $scheduler->schedule(function() use (&$executionOrder) {
                sleep(0.01);
                $executionOrder[] = 'control';
                return 'control-result';
            }, PriorityScheduler::PRIORITY_CONTROL, 'control');
            
            // 创建SYSTEM级任务
            $systemTask = $scheduler->schedule(function() use (&$executionOrder) {
                $executionOrder[] = 'system';
                return 'system-result';
            }, PriorityScheduler::PRIORITY_SYSTEM, 'system');
            
            // 等待所有任务完成
            $systemTask->getResult();
            $controlTask->getResult();
            $workTask1->getResult();
            $workTask2->getResult();
        });
        
        // 验证执行顺序：SYSTEM > CONTROL > WORK
        $this->assertEquals('system', $executionOrder[0]);
        $this->assertEquals('control', $executionOrder[1]);
        $this->assertContains('work1', $executionOrder);
        $this->assertContains('work2', $executionOrder);
    }
    
    /**
     * 测试大量任务下的调度性能
     */
    public function testSchedulingPerformanceWithManyTasks()
    {
        $startTime = microtime(true);
        $taskCount = 100;
        $completedTasks = 0;
        
        run(function() use ($taskCount, &$completedTasks) {
            $eventLoop = EventLoop::getInstance();
            $scheduler = $eventLoop->getScheduler();
            
            $tasks = [];
            
            // 创建大量WORK级任务
            for ($i = 0; $i < $taskCount; $i++) {
                $tasks[] = $scheduler->schedule(function() use (&$completedTasks) {
                    sleep(0.001); // 模拟短暂的工作
                    $completedTasks++;
                    return "result-{$i}";
                }, PriorityScheduler::PRIORITY_WORK, "work-task-{$i}");
            }
            
            // 创建几个CONTROL级任务
            for ($i = 0; $i < 5; $i++) {
                $tasks[] = $scheduler->schedule(function() use (&$completedTasks, $i) {
                    sleep(0.005);
                    $completedTasks++;
                    return "control-result-{$i}";
                }, PriorityScheduler::PRIORITY_CONTROL, "control-task-{$i}");
            }
            
            // 创建1个SYSTEM级任务
            $tasks[] = $scheduler->schedule(function() use (&$completedTasks) {
                $completedTasks++;
                return "system-result";
            }, PriorityScheduler::PRIORITY_SYSTEM, "system-task");
            
            // 等待所有任务完成
            foreach ($tasks as $task) {
                $task->getResult();
            }
        });
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 验证所有任务都已完成
        $this->assertEquals($taskCount + 5 + 1, $completedTasks);
        
        // 验证执行时间（在合理范围内）
        $this->assertLessThan(2.0, $duration, "执行时间不应超过2秒");
    }
    
    /**
     * 测试SYSTEM级任务的立即执行特性
     */
    public function testSystemTaskImmediateExecution()
    {
        $executionOrder = [];
        
        run(function() use (&$executionOrder) {
            $eventLoop = EventLoop::getInstance();
            $scheduler = $eventLoop->getScheduler();
            
            // 创建一个长时间运行的WORK级任务
            $longRunningTask = $scheduler->schedule(function() use (&$executionOrder) {
                $executionOrder[] = 'work-start';
                sleep(0.2);
                $executionOrder[] = 'work-end';
                return 'work-result';
            }, PriorityScheduler::PRIORITY_WORK, 'long-running-work');
            
            // 立即创建一个SYSTEM级任务
            $systemTask = $scheduler->schedule(function() use (&$executionOrder) {
                $executionOrder[] = 'system-executed';
                return 'system-result';
            }, PriorityScheduler::PRIORITY_SYSTEM, 'immediate-system');
            
            // 等待所有任务完成
            $systemTask->getResult();
            $longRunningTask->getResult();
        });
        
        // 验证SYSTEM任务在WORK任务中间执行
        $workStartIndex = array_search('work-start', $executionOrder);
        $systemIndex = array_search('system-executed', $executionOrder);
        $workEndIndex = array_search('work-end', $executionOrder);
        
        $this->assertLessThan($systemIndex, $workEndIndex);
        $this->assertLessThan($workStartIndex, $systemIndex);
    }
}
