<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Future;
use function PfinalClub\Asyncio\{run, create_task, create_future, await_future};

class FutureTest extends TestCase
{
    public function testFutureCreation()
    {
        $future = create_future();
        $this->assertInstanceOf(Future::class, $future);
        $this->assertFalse($future->isDone());
    }
    
    public function testFutureSetResult()
    {
        $future = create_future();
        $future->setResult('test value');
        
        $this->assertTrue($future->isDone());
        $this->assertEquals('test value', $future->getResult());
        $this->assertFalse($future->hasException());
    }
    
    public function testFutureSetException()
    {
        $future = create_future();
        $exception = new \RuntimeException('Future error');
        $future->setException($exception);
        
        $this->assertTrue($future->isDone());
        $this->assertTrue($future->hasException());
        $this->assertSame($exception, $future->getException());
    }
    
    public function testFutureGetResultBeforeDone()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Future not done yet');
        
        $future = create_future();
        $future->getResult();
    }
    
    public function testFutureSetResultTwice()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Future already done');
        
        $future = create_future();
        $future->setResult('first');
        $future->setResult('second');
    }
    
    public function testFutureAwait()
    {
        $result = run(function() {
            $future = create_future();
            
            // 异步设置结果
            create_task(function() use ($future) {
                \PfinalClub\Asyncio\sleep(0.05);
                $future->setResult('async result');
            }, 'setter');
            
            return await_future($future);
        });
        
        $this->assertEquals('async result', $result);
    }
    
    public function testFutureCallback()
    {
        $future = create_future();
        $callbackExecuted = false;
        
        $future->addDoneCallback(function($f) use (&$callbackExecuted) {
            $callbackExecuted = true;
        });
        
        $future->setResult('done');
        
        $this->assertTrue($callbackExecuted);
    }
    
    public function testFutureCallbackWhenAlreadyDone()
    {
        $future = create_future();
        $future->setResult('done');
        
        $callbackExecuted = false;
        $future->addDoneCallback(function($f) use (&$callbackExecuted) {
            $callbackExecuted = true;
        });
        
        $this->assertTrue($callbackExecuted, '回调应该立即执行');
    }
}
