<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Observable\Observable;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;
use PfinalClub\Asyncio\Observable\Events\ScopeEvent;
use function PfinalClub\Asyncio\{run, create_task, sleep};
use PfinalClub\Asyncio\Concurrency\CancellationScope;

/**
 * 可观测性集成测试
 * 测试事件发布订阅机制
 */
class ObservabilityIntegrationTest extends TestCase
{
    /**
     * 测试任务事件的发布和订阅
     */
    public function testTaskEventPublishing()
    {
        $events = [];
        
        run(function() use (&$events) {
            // 订阅任务事件
            $subscription = Observable::subscribe(TaskEvent::class, function(TaskEvent $event) use (&$events) {
                $events[] = [
                    'type' => $event->getType(),
                    'taskId' => $event->getTaskId(),
                    'timestamp' => $event->getTimestamp()
                ];
            });
            
            // 创建并执行任务
            $task = create_task(function() {
                sleep(0.01);
                return 'result';
            });
            
            $task->getResult();
            
            // 取消订阅
            Observable::unsubscribe($subscription);
        });
        
        // 验证事件
        $this->assertGreaterThanOrEqual(2, count($events), 'Should receive at least 2 events (STARTED and COMPLETED)');
        
        // 检查是否包含STARTED和COMPLETED事件
        $eventTypes = array_column($events, 'type');
        $this->assertContains(TaskEvent::STARTED, $eventTypes);
        $this->assertContains(TaskEvent::COMPLETED, $eventTypes);
        
        // 检查所有事件是否有相同的taskId
        $taskIds = array_column($events, 'taskId');
        $this->assertNotEmpty($taskIds[0], 'Task ID should not be empty');
        foreach ($taskIds as $taskId) {
            $this->assertEquals($taskIds[0], $taskId, 'All events should have the same task ID');
        }
    }
    
    /**
     * 测试作用域事件的发布和订阅
     */
    public function testScopeEventPublishing()
    {
        $events = [];
        
        run(function() use (&$events) {
            // 订阅作用域事件
            $subscription = Observable::subscribe(ScopeEvent::class, function(ScopeEvent $event) use (&$events) {
                $events[] = [
                    'type' => $event->getType(),
                    'scopeId' => $event->getScopeId(),
                    'timestamp' => $event->getTimestamp()
                ];
            });
            
            // 创建并使用取消作用域
            $scope = new CancellationScope();
            
            $task = create_task(function() use ($scope) {
                try {
                    sleep(0.01);
                    $scope->checkCancelled();
                    return 'result';
                } catch (\PfinalClub\Asyncio\Exception\TaskCancelledException $e) {
                    return 'cancelled';
                }
            });
            
            // 取消作用域
            $scope->cancel();
            
            $task->getResult();
            
            // 取消订阅
            Observable::unsubscribe($subscription);
        });
        
        // 验证事件
        $this->assertGreaterThanOrEqual(2, count($events), 'Should receive at least 2 events (CREATED and CANCELLED)');
        
        // 检查是否包含CREATED和CANCELLED事件
        $eventTypes = array_column($events, 'type');
        $this->assertContains(ScopeEvent::CREATED, $eventTypes);
        $this->assertContains(ScopeEvent::CANCELLED, $eventTypes);
    }
    
    /**
     * 测试多事件订阅
     */
    public function testMultipleEventSubscriptions()
    {
        $taskEvents = [];
        $scopeEvents = [];
        
        run(function() use (&$taskEvents, &$scopeEvents) {
            // 订阅多个事件类型
            $taskSubscription = Observable::subscribe(TaskEvent::class, function(TaskEvent $event) use (&$taskEvents) {
                $taskEvents[] = $event->getType();
            });
            
            $scopeSubscription = Observable::subscribe(ScopeEvent::class, function(ScopeEvent $event) use (&$scopeEvents) {
                $scopeEvents[] = $event->getType();
            });
            
            // 创建任务和作用域
            $scope = new CancellationScope();
            
            $task = create_task(function() use ($scope) {
                sleep(0.01);
                return 'result';
            });
            
            $task->getResult();
            
            // 取消订阅
            Observable::unsubscribe($taskSubscription);
            Observable::unsubscribe($scopeSubscription);
        });
        
        // 验证事件
        $this->assertGreaterThan(0, count($taskEvents), 'Should receive task events');
        $this->assertGreaterThan(0, count($scopeEvents), 'Should receive scope events');
    }
    
    /**
     * 测试事件过滤
     */
    public function testEventFiltering()
    {
        $completedEvents = [];
        
        run(function() use (&$completedEvents) {
            // 订阅任务事件，并过滤出COMPLETED事件
            $subscription = Observable::subscribe(TaskEvent::class, function(TaskEvent $event) use (&$completedEvents) {
                if ($event->getType() === TaskEvent::COMPLETED) {
                    $completedEvents[] = $event->getTaskId();
                }
            });
            
            // 创建多个任务
            $tasks = [];
            for ($i = 0; $i < 5; $i++) {
                $tasks[] = create_task(function() use ($i) {
                    sleep(0.005);
                    return "result-{$i}";
                });
            }
            
            // 等待所有任务完成
            foreach ($tasks as $task) {
                $task->getResult();
            }
            
            // 取消订阅
            Observable::unsubscribe($subscription);
        });
        
        // 验证只收到COMPLETED事件
        $this->assertEquals(5, count($completedEvents), 'Should receive exactly 5 COMPLETED events');
    }
}
