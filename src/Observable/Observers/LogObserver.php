<?php

namespace PfinalClub\Asyncio\Observable\Observers;

use PfinalClub\Asyncio\Observable\Observer;
use PfinalClub\Asyncio\Observable\Events\TaskEvent;
use PfinalClub\Asyncio\Observable\Events\ScopeEvent;
use PfinalClub\Asyncio\Observable\Events\ResourceEvent;
use PfinalClub\Asyncio\Observable\Events\RuntimeStateEvent;

/**
 * 日志观察者 - 记录事件日志
 * 
 * @api-stable
 */
class LogObserver implements Observer
{
    private int $logLevel;
    private bool $detailed;
    
    public const LOG_LEVEL_DEBUG = 0;
    public const LOG_LEVEL_INFO = 1;
    public const LOG_LEVEL_WARNING = 2;
    public const LOG_LEVEL_ERROR = 3;
    
    /**
     * 构造函数
     * 
     * @param int $logLevel 日志级别，默认为 INFO
     * @param bool $detailed 是否输出详细信息，默认为 false
     */
    public function __construct(int $logLevel = self::LOG_LEVEL_INFO, bool $detailed = false)
    {
        $this->logLevel = $logLevel;
        $this->detailed = $detailed;
    }
    
    /**
     * {@inheritDoc}
     */
    public function onTaskEvent(TaskEvent $event): void
    {
        $task = $event->getTask();
        $taskId = $task->getId();
        $taskName = $task->getName();
        $state = $task->getState()->value;
        
        $message = "Task #{$taskId} '{$taskName}' {$event->getType()} (state: {$state})";
        
        if ($event->isFailed() && $event->getException()) {
            $exception = $event->getException();
            $message .= ": {$exception->getMessage()} in {$exception->getFile()}:{$exception->getLine()}";
        }
        
        $this->log(self::LOG_LEVEL_INFO, $message);
    }
    
    /**
     * {@inheritDoc}
     */
    public function onScopeEvent(ScopeEvent $event): void
    {
        $scope = $event->getScope();
        $scopeId = spl_object_id($scope);
        $taskCount = count($scope->getTasks());
        
        $message = "Scope #{$scopeId} {$event->getType()} (tasks: {$taskCount}, cancelled: " . ($scope->isCancelled() ? 'yes' : 'no') . ")";
        
        $this->log(self::LOG_LEVEL_DEBUG, $message);
    }
    
    /**
     * {@inheritDoc}
     */
    public function onResourceEvent(ResourceEvent $event): void
    {
        $resource = $event->getResource();
        $resourceType = $event->getResourceType();
        $resourceId = spl_object_id($resource);
        
        $message = "Resource #{$resourceId} {$resourceType} {$event->getType()} (closed: " . ($resource->isClosed() ? 'yes' : 'no') . ")";
        
        $this->log(self::LOG_LEVEL_DEBUG, $message);
    }
    
    /**
     * {@inheritDoc}
     */
    public function onRuntimeStateEvent(RuntimeStateEvent $event): void
    {
        $data = $event->getData();
        $dataStr = $this->detailed ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data);
        
        $message = "Runtime {$event->getType()}: {$dataStr}";
        
        $logLevel = match ($event->getType()) {
            RuntimeStateEvent::MEMORY_WARNING => self::LOG_LEVEL_WARNING,
            RuntimeStateEvent::HIGH_LOAD => self::LOG_LEVEL_WARNING,
            RuntimeStateEvent::SHUTDOWN_INITIATED => self::LOG_LEVEL_INFO,
            RuntimeStateEvent::SHUTDOWN_COMPLETED => self::LOG_LEVEL_INFO,
            RuntimeStateEvent::EVENT_LOOP_STARTED => self::LOG_LEVEL_INFO,
            RuntimeStateEvent::EVENT_LOOP_STOPPED => self::LOG_LEVEL_INFO,
            default => self::LOG_LEVEL_DEBUG,
        };
        
        $this->log($logLevel, $message);
    }
    
    /**
     * 记录日志
     * 
     * @param int $level 日志级别
     * @param string $message 日志消息
     * @return void
     */
    private function log(int $level, string $message): void
    {
        if ($level < $this->logLevel) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s.u');
        $levelStr = match ($level) {
            self::LOG_LEVEL_DEBUG => 'DEBUG',
            self::LOG_LEVEL_INFO => 'INFO',
            self::LOG_LEVEL_WARNING => 'WARNING',
            self::LOG_LEVEL_ERROR => 'ERROR',
            default => 'UNKNOWN',
        };
        
        $logMessage = "[{$timestamp}] [{$levelStr}] [AsyncIO] {$message}";
        
        // 输出到标准输出
        echo $logMessage . PHP_EOL;
    }
    
    /**
     * 获取当前日志级别
     * 
     * @return int 日志级别
     */
    public function getLogLevel(): int
    {
        return $this->logLevel;
    }
    
    /**
     * 设置日志级别
     * 
     * @param int $logLevel 日志级别
     * @return void
     */
    public function setLogLevel(int $logLevel): void
    {
        $this->logLevel = $logLevel;
    }
}