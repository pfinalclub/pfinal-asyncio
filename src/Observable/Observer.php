<?php

namespace PfinalClub\Asyncio\Observable;

use PfinalClub\Asyncio\Observable\Events\TaskEvent;

/**
 * 简化的观察者接口 - 仅处理任务事件
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
}