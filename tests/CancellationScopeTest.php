<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\TaskCancelledException;
use PfinalClub\Asyncio\Concurrency\CancellationScope;
use function PfinalClub\Asyncio\{run, create_task, sleep};

class CancellationScopeTest extends TestCase
{
    public function testScopeRun()
    {
        $result = CancellationScope::run(function() {
            return 'scope result';
        });
        
        $this->assertEquals('scope result', $result);
    }
    
    public function testCurrentScope()
    {
        CancellationScope::run(function() {
            $scope = CancellationScope::current();
            $this->assertNotNull($scope);
            $this->assertFalse($scope->isCancelled());
        });
    }
    
    public function testScopeWithoutRun()
    {
        $this->assertNull(CancellationScope::current());
    }
    
    public function testScopeCancel()
    {
        CancellationScope::run(function() {
            $scope = CancellationScope::current();
            $scope->cancel();
            $this->assertTrue($scope->isCancelled());
        });
    }
    
    public function testTaskInScope()
    {
        run(function() {
            CancellationScope::run(function() {
                $task = create_task(function() {
                    return 'task result';
                });
                
                $this->assertNotNull($task);
                $result = \PfinalClub\Asyncio\await($task);
                $this->assertEquals('task result', $result);
            });
        });
    }
    
    public function testScopeCancelCancelsTasks()
    {
        run(function() {
            $scope = null;
            $cancelled = false;
            
            $scope = CancellationScope::run(function() use (&$cancelled) {
                $task = create_task(function() {
                    try {
                        sleep(5);
                        return 'should not complete';
                    } catch (TaskCancelledException $e) {
                        return 'cancelled';
                    }
                });
                
                return $task;
            });
            
            $this->assertNotNull($scope);
            $scope->cancel();
            
            sleep(0.1);
            $this->assertTrue($scope->isCancelled());
        });
    }
    
    public function testNestedScopes()
    {
        run(function() {
            $outerScope = null;
            $innerScope = null;
            
            CancellationScope::run(function() use (&$outerScope, &$innerScope) {
                $outerScope = CancellationScope::current();
                
                CancellationScope::run(function() use (&$innerScope) {
                    $innerScope = CancellationScope::current();
                    $this->assertNotNull($innerScope);
                });
                
                $this->assertNotNull($outerScope);
            });
            
            $this->assertNotNull($outerScope);
            $this->assertNotNull($innerScope);
            $this->assertNotSame($outerScope, $innerScope);
        });
    }
}
