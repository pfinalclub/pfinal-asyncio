<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Exception\GatherException;
use PfinalClub\Asyncio\Concurrency\GatherStrategy;
use function PfinalClub\Asyncio\{run, create_task, sleep, gather};

/**
 * Gather策略集成测试
 * 测试不同的gather策略（FAIL_FAST, WAIT_ALL, RETURN_PARTIAL）
 */
class GatherStrategyTest extends TestCase
{
    /**
     * 测试FAIL_FAST策略
     * 一旦有任务失败，立即取消所有其他任务
     */
    public function testFailFastStrategy()
    {
        $failedTaskCount = 0;
        $completedTaskCount = 0;
        
        try {
            run(function() use (&$failedTaskCount, &$completedTaskCount) {
                $task1 = create_task(function() use (&$completedTaskCount) {
                    sleep(0.05);
                    $completedTaskCount++;
                    return 'task1-result';
                }, 'task1');
                
                $task2 = create_task(function() use (&$failedTaskCount) {
                    sleep(0.01);
                    $failedTaskCount++;
                    throw new \RuntimeException('task2-failed');
                }, 'task2');
                
                $task3 = create_task(function() use (&$completedTaskCount) {
                    sleep(0.1);
                    $completedTaskCount++;
                    return 'task3-result';
                }, 'task3');
                
                // 使用FAIL_FAST策略
                $results = gather($task1, $task2, $task3, GatherStrategy::FAIL_FAST);
            });
            
            $this->fail('预期会抛出GatherException');
        } catch (GatherException $e) {
            // 验证只完成了1个任务，另一个任务失败，第三个任务被取消
            $this->assertEquals(1, $completedTaskCount);
            $this->assertEquals(1, $failedTaskCount);
            
            // 验证GatherException包含失败信息
            $this->assertEquals(1, $e->getFailedCount());
            $this->assertEquals(1, $e->getSuccessCount());
            $this->assertNotNull($e->getFirstException());
        }
    }
    
    /**
     * 测试WAIT_ALL策略
     * 等待所有任务完成，无论成功或失败
     */
    public function testWaitAllStrategy()
    {
        try {
            $results = run(function() {
                $task1 = create_task(function() {
                    sleep(0.05);
                    return 'task1-result';
                }, 'task1');
                
                $task2 = create_task(function() {
                    sleep(0.01);
                    throw new \RuntimeException('task2-failed');
                }, 'task2');
                
                $task3 = create_task(function() {
                    sleep(0.03);
                    throw new \RuntimeException('task3-failed');
                }, 'task3');
                
                // 使用WAIT_ALL策略
                return gather($task1, $task2, $task3, GatherStrategy::WAIT_ALL);
            });
            
            $this->fail('预期会抛出GatherException');
        } catch (GatherException $e) {
            // 验证所有任务都已完成（2个失败，1个成功）
            $this->assertEquals(2, $e->getFailedCount());
            $this->assertEquals(1, $e->getSuccessCount());
            
            // 验证GatherException包含所有失败信息
            $exceptions = $e->getExceptions();
            $this->assertCount(2, $exceptions);
            
            // 验证成功结果被保留
            $results = $e->getResults();
            $this->assertCount(1, $results);
        }
    }
    
    /**
     * 测试RETURN_PARTIAL策略
     * 等待所有任务完成，返回已成功的结果
     */
    public function testReturnPartialStrategy()
    {
        $results = run(function() {
            $task1 = create_task(function() {
                sleep(0.05);
                return 'task1-result';
            }, 'task1');
            
            $task2 = create_task(function() {
                sleep(0.01);
                throw new \RuntimeException('task2-failed');
            }, 'task2');
            
            $task3 = create_task(function() {
                sleep(0.03);
                return 'task3-result';
            }, 'task3');
            
            // 使用RETURN_PARTIAL策略
            return gather($task1, $task2, $task3, GatherStrategy::RETURN_PARTIAL);
        });
        
        // 验证返回了部分结果
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertContains('task1-result', $results);
        $this->assertContains('task3-result', $results);
    }
    
    /**
     * 测试空任务列表的情况
     */
    public function testEmptyTaskList()
    {
        $results = run(function() {
            // 使用各种策略测试空任务列表
            $result1 = gather();
            $result2 = gather(GatherStrategy::FAIL_FAST);
            $result3 = gather(GatherStrategy::WAIT_ALL);
            $result4 = gather(GatherStrategy::RETURN_PARTIAL);
            
            return [$result1, $result2, $result3, $result4];
        });
        
        // 验证所有策略都返回空数组
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertEmpty($result);
        }
    }
    
    /**
     * 测试单个任务的情况
     */
    public function testSingleTask()
    {
        $results = run(function() {
            $task = create_task(function() {
                return 'single-task-result';
            }, 'single-task');
            
            // 使用各种策略测试单个任务
            $result1 = gather($task);
            $result2 = gather($task, GatherStrategy::FAIL_FAST);
            $result3 = gather($task, GatherStrategy::WAIT_ALL);
            $result4 = gather($task, GatherStrategy::RETURN_PARTIAL);
            
            return [$result1, $result2, $result3, $result4];
        });
        
        // 验证所有策略都返回正确结果
        foreach ($results as $result) {
            $this->assertIsArray($result);
            $this->assertCount(1, $result);
            $this->assertEquals('single-task-result', $result[0]);
        }
    }
    
    /**
     * 测试所有任务都成功的情况
     */
    public function testAllTasksSuccess()
    {
        $results = run(function() {
            $tasks = [];
            
            // 创建5个成功的任务
            for ($i = 0; $i < 5; $i++) {
                $taskId = $i;
                $tasks[] = create_task(function() use ($taskId) {
                    sleep(0.01 * $taskId);
                    return "task{$taskId}-result";
                }, "task{$taskId}");
            }
            
            // 使用各种策略测试
            $result1 = gather(...$tasks, GatherStrategy::FAIL_FAST);
            $result2 = gather(...$tasks, GatherStrategy::WAIT_ALL);
            $result3 = gather(...$tasks, GatherStrategy::RETURN_PARTIAL);
            
            return [$result1, $result2, $result3];
        });
        
        // 验证所有策略都返回相同的结果
        for ($i = 1; $i < count($results); $i++) {
            $this->assertEquals($results[0], $results[$i]);
        }
        
        // 验证结果数量正确
        $this->assertCount(5, $results[0]);
        
        // 验证结果内容正确
        for ($i = 0; $i < 5; $i++) {
            $this->assertContains("task{$i}-result", $results[0]);
        }
    }
}
