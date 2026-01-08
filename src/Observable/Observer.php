<?php

namespace PfinalClub\Asyncio\Observable;

use PfinalClub\Asyncio\Observable\Events\TaskEvent;
use PfinalClub\Asyncio\Observable\Events\ScopeEvent;
use PfinalClub\Asyncio\Observable\Events\ResourceEvent;
use PfinalClub\Asyncio\Observable\Events\RuntimeStateEvent;

/**
 * 观察者接口 - 定义事件处理方法
 * 
 * @api-stable
 */
interface Observer
{
    /**
     * 处理任务事件
     * 
     * @param TaskEvent $event 任务事件
     * @return void
     */
    public function onTaskEvent(TaskEvent $event): void;
    
    /**
     * 处理作用域事件
     * 
     * @param ScopeEvent $event 作用域事件
     * @return void
     */
    public function onScopeEvent(ScopeEvent $event): void;
    
    /**
     * 处理资源事件
     * 
     * @param ResourceEvent $event 资源事件
     * @return void
     */
    public function onResourceEvent(ResourceEvent $event): void;
    
    /**
     * 处理运行时状态事件
     * 
     * @param RuntimeStateEvent $event 运行时状态事件
     * @return void
     */
    public function onRuntimeStateEvent(RuntimeStateEvent $event): void;
}