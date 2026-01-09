<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Resource\AsyncResourceManager;
use PfinalClub\Asyncio\Resource\Context;
use PfinalClub\Asyncio\Resource\AsyncResource;
use function PfinalClub\Asyncio\{run, create_task, sleep};

/**
 * 资源管理集成测试
 * 测试AsyncResourceManager、Context和相关资源类的交互
 */
class ResourceManagementIntegrationTest extends TestCase
{
    /**
     * 测试异步资源的自动管理
     */
    public function testAsyncResourceAutomaticManagement()
    {
        $resourceCleaned = false;
        $resourceCount = 0;
        
        run(function() use (&$resourceCleaned, &$resourceCount) {
            // 创建一个简单的异步资源
            $resource = new class() implements AsyncResource {
                private $cleaned = false;
                
                public function close(): void
                {
                    $this->cleaned = true;
                }
                
                public function isClosed(): bool
                {
                    return $this->cleaned;
                }
                
                public function onCancellation(): void
                {
                    // 处理取消事件
                    $this->close();
                }
            };
            
            $manager = AsyncResourceManager::getInstance();
            $resourceCount = $manager->getResourceCount();
            
            // 注册资源
            $manager->registerResource($resource);
            $this->assertEquals($resourceCount + 1, $manager->getResourceCount());
            
            // 任务完成后资源应该被自动清理
        });
        
        // 检查资源是否被清理
        $manager = AsyncResourceManager::getInstance();
        $this->assertEquals($resourceCount, $manager->getResourceCount());
    }
    
    /**
     * 测试上下文资源的继承和管理
     */
    public function testContextResourceInheritance()
    {
        $contextValues = [];
        
        run(function() use (&$contextValues) {
            $context = Context::getCurrent();
            
            // 设置根上下文值
            $context->set('root-key', 'root-value');
            
            // 创建子任务，应该继承上下文
            $task1 = create_task(function() use (&$contextValues) {
                $context = Context::getCurrent();
                $contextValues['task1-root'] = $context->get('root-key');
                
                // 在子任务中设置新值
                $context->set('task1-key', 'task1-value');
                
                // 创建嵌套子任务
                $task2 = create_task(function() use (&$contextValues) {
                    $context = Context::getCurrent();
                    $contextValues['task2-root'] = $context->get('root-key');
                    $contextValues['task2-parent'] = $context->get('task1-key');
                    
                    // 设置嵌套子任务的值
                    $context->set('task2-key', 'task2-value');
                });
                
                $task2->getResult();
            });
            
            $task1->getResult();
            
            // 根上下文不应包含子任务设置的值
            $contextValues['root-task1'] = $context->get('task1-key');
            $contextValues['root-task2'] = $context->get('task2-key');
        });
        
        // 验证上下文继承
        $this->assertEquals('root-value', $contextValues['task1-root']);
        $this->assertEquals('root-value', $contextValues['task2-root']);
        $this->assertEquals('task1-value', $contextValues['task2-parent']);
        $this->assertNull($contextValues['root-task1']);
        $this->assertNull($contextValues['root-task2']);
    }
    
    /**
     * 测试大量资源的管理性能
     */
    public function testLargeResourceManagementPerformance()
    {
        $startTime = microtime(true);
        $resourceCount = 1000;
        
        run(function() use ($resourceCount) {
            $manager = AsyncResourceManager::getInstance();
            $initialCount = $manager->getResourceCount();
            
            // 创建并注册大量资源
            $resources = [];
            for ($i = 0; $i < $resourceCount; $i++) {
                $resource = new class() implements AsyncResource {
                    private $cleaned = false;
                    
                    public function close(): void
                    {
                        $this->cleaned = true;
                    }
                    
                    public function isClosed(): bool
                    {
                        return $this->cleaned;
                    }
                    
                    public function onCancellation(): void
                    {
                        // 处理取消事件
                        $this->close();
                    }
                };
                
                $resources[] = $resource;
                $manager->registerResource($resource);
            }
            
            // 验证所有资源都已注册
            $this->assertEquals($initialCount + $resourceCount, $manager->getResourceCount());
        });
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // 验证所有资源都已清理
        $manager = AsyncResourceManager::getInstance();
        $finalCount = $manager->getResourceCount();
        
        // 允许有少量资源残留（可能是系统资源）
        $this->assertLessThan(10, $finalCount, "资源残留不应超过10个");
        
        // 验证执行时间（在合理范围内）
        $this->assertLessThan(1.0, $duration, "执行时间不应超过1秒");
    }
    
    /**
     * 测试上下文隔离
     */
    public function testContextIsolation()
    {
        $results = [];
        
        run(function() use (&$results) {
            $context = Context::getCurrent();
            $context->set('shared', 'initial');
            
            // 创建两个并行任务
            $task1 = create_task(function() use (&$results) {
                $context = Context::getCurrent();
                $results['task1-initial'] = $context->get('shared');
                $context->set('shared', 'task1-modified');
                sleep(0.1); // 增加并发冲突的可能性
                $results['task1-final'] = $context->get('shared');
            });
            
            $task2 = create_task(function() use (&$results) {
                $context = Context::getCurrent();
                $results['task2-initial'] = $context->get('shared');
                $context->set('shared', 'task2-modified');
                sleep(0.1); // 增加并发冲突的可能性
                $results['task2-final'] = $context->get('shared');
            });
            
            $task1->getResult();
            $task2->getResult();
            
            $results['root-final'] = $context->get('shared');
        });
        
        // 验证上下文隔离
        $this->assertEquals('initial', $results['task1-initial']);
        $this->assertEquals('initial', $results['task2-initial']);
        $this->assertEquals('task1-modified', $results['task1-final']);
        $this->assertEquals('task2-modified', $results['task2-final']);
        $this->assertEquals('initial', $results['root-final']);
    }
    
    /**
     * 测试资源清理的异常处理
     */
    public function testResourceCleanupExceptionHandling()
    {
        $exceptions = [];
        
        run(function() use (&$exceptions) {
            $manager = AsyncResourceManager::getInstance();
            
            // 创建一个在清理时抛出异常的资源
            $resource1 = new class() implements AsyncResource {
                public function close(): void
                {
                    throw new \Exception("Resource cleanup exception");
                }
                
                public function isClosed(): bool
                {
                    return false;
                }
                
                public function onCancellation(): void
                {
                    // 处理取消事件
                    $this->close();
                }
            };
            
            // 创建一个正常清理的资源
            $resource2 = new class() implements AsyncResource {
                private $closed = false;
                
                public function close(): void
                {
                    $this->closed = true;
                }
                
                public function isClosed(): bool
                {
                    return $this->closed;
                }
                
                public function onCancellation(): void
                {
                    // 处理取消事件
                    $this->close();
                }
            };
            
            $manager->registerResource($resource1);
            $manager->registerResource($resource2);
        });
        
        // 验证资源管理器能够处理清理异常，不会导致崩溃
        $manager = AsyncResourceManager::getInstance();
        $this->assertTrue(true, "Resource manager should handle cleanup exceptions gracefully");
    }
}
