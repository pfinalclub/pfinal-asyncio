<?php

namespace PfinalClub\Asyncio\Tests;

use PHPUnit\Framework\TestCase;
use PfinalClub\Asyncio\Resource\Context;
use function PfinalClub\Asyncio\{run, create_task, sleep};

class ContextTest extends TestCase
{
    public function testSetGetContext()
    {
        run(function() {
            // 设置上下文
            Context::set('test_key', 'test_value');
            
            // 获取上下文
            $value = Context::get('test_key');
            $this->assertEquals('test_value', $value);
        });
    }
    
    public function testSetGetMultipleContexts()
    {
        run(function() {
            // 设置多个上下文
            Context::set('key1', 'value1');
            Context::set('key2', 'value2');
            Context::set('key3', 'value3');
            
            // 获取上下文
            $this->assertEquals('value1', Context::get('key1'));
            $this->assertEquals('value2', Context::get('key2'));
            $this->assertEquals('value3', Context::get('key3'));
        });
    }
    
    public function testGetDefaultValue()
    {
        run(function() {
            // 获取不存在的键，使用默认值
            $value = Context::get('non_existent_key', 'default_value');
            $this->assertEquals('default_value', $value);
            
            // 获取不存在的键，不使用默认值
            $value = Context::get('non_existent_key');
            $this->assertNull($value);
        });
    }
    
    public function testHasContext()
    {
        run(function() {
            // 设置上下文
            Context::set('existing_key', 'value');
            
            // 检查上下文是否存在
            $this->assertTrue(Context::has('existing_key'));
            $this->assertFalse(Context::has('non_existent_key'));
        });
    }
    
    public function testDeleteContext()
    {
        run(function() {
            // 设置上下文
            Context::set('key_to_delete', 'value');
            $this->assertTrue(Context::has('key_to_delete'));
            
            // 删除上下文
            Context::delete('key_to_delete');
            $this->assertFalse(Context::has('key_to_delete'));
        });
    }
    
    public function testClearContext()
    {
        run(function() {
            // 设置多个上下文
            Context::set('key1', 'value1');
            Context::set('key2', 'value2');
            Context::set('key3', 'value3');
            
            // 检查所有键都存在
            $this->assertTrue(Context::has('key1'));
            $this->assertTrue(Context::has('key2'));
            $this->assertTrue(Context::has('key3'));
            
            // 清除上下文
            Context::clear();
            
            // 检查所有键都不存在
            $this->assertFalse(Context::has('key1'));
            $this->assertFalse(Context::has('key2'));
            $this->assertFalse(Context::has('key3'));
        });
    }
    
    public function testContextInheritance()
    {
        run(function() {
            // 在父任务中设置上下文
            Context::set('parent_key', 'parent_value');
            Context::set('shared_key', 'parent_modified');
            
            $childResult = null;
            
            // 创建子任务
            $childTask = create_task(function() use (&$childResult) {
                // 获取继承的上下文
                $parentKey = Context::get('parent_key');
                $sharedKey = Context::get('shared_key');
                
                // 在子任务中设置自己的上下文
                Context::set('child_key', 'child_value');
                Context::set('shared_key', 'child_modified');
                
                return [
                    'parent_key' => $parentKey,
                    'child_key' => Context::get('child_key'),
                    'shared_key' => $sharedKey,
                    'child_shared_key' => Context::get('shared_key')
                ];
            });
            
            $childResult = \PfinalClub\Asyncio\await($childTask);
            
            // 验证父任务上下文未被修改
            $this->assertEquals('parent_modified', Context::get('shared_key'));
            $this->assertFalse(Context::has('child_key'));
            
            // 验证子任务继承和修改的上下文
            $this->assertEquals('parent_value', $childResult['parent_key']);
            $this->assertEquals('child_value', $childResult['child_key']);
            $this->assertEquals('parent_modified', $childResult['shared_key']);
            $this->assertEquals('child_modified', $childResult['child_shared_key']);
        });
    }
    
    public function testGetAllContext()
    {
        run(function() {
            // 设置上下文
            Context::set('key1', 'value1');
            Context::set('key2', 'value2');
            
            // 获取所有上下文
            $allContext = Context::getAll();
            $this->assertIsArray($allContext);
            $this->assertArrayHasKey('key1', $allContext);
            $this->assertArrayHasKey('key2', $allContext);
            $this->assertEquals('value1', $allContext['key1']);
            $this->assertEquals('value2', $allContext['key2']);
        });
    }
}
