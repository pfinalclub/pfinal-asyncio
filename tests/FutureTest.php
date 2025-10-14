<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Future;

class FutureTest extends TestCase
{
    public function testFutureCreation()
    {
        $future = new Future();
        
        $this->assertFalse($future->isDone());
    }
    
    public function testSetResult()
    {
        $future = new Future();
        $future->setResult('test result');
        
        $this->assertTrue($future->isDone());
        $this->assertEquals('test result', $future->getResult());
    }
    
    public function testSetException()
    {
        $future = new Future();
        $exception = new \Exception('test error');
        $future->setException($exception);
        
        $this->assertTrue($future->isDone());
        $this->assertTrue($future->hasException());
        $this->assertSame($exception, $future->getException());
    }
    
    public function testDoneCallback()
    {
        $future = new Future();
        $called = false;
        
        $future->addDoneCallback(function() use (&$called) {
            $called = true;
        });
        
        $future->setResult('done');
        
        $this->assertTrue($called);
    }
    
    public function testCallbackCalledImmediatelyIfDone()
    {
        $future = new Future();
        $future->setResult('done');
        
        $called = false;
        $future->addDoneCallback(function() use (&$called) {
            $called = true;
        });
        
        $this->assertTrue($called);
    }
}

