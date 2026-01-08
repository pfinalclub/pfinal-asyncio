<?php

namespace PfinalClub\Asyncio\Production;

use PfinalClub\Asyncio\Core\EventLoop;
use Workerman\{Timer, Worker};

/**
 * 优雅关闭
 * 
 * 处理 SIGTERM/SIGINT 信号，确保任务完成后再退出
 */
class GracefulShutdown
{
    private static ?GracefulShutdown $instance = null;
    private bool $shutdownRequested = false;
    private int $gracePeriod = 30;  // 优雅关闭等待时间（秒）
    private array $shutdownCallbacks = [];
    private ?int $shutdownTimerId = null;
    
    private function __construct()
    {
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 注册信号处理器
     */
    public function register(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            echo "Warning: Graceful shutdown signals not supported on Windows\n";
            return;
        }
        
        // 注册 SIGTERM 处理器
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        
        // 注册 SIGINT 处理器 (Ctrl+C)
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        
        // 启动信号分发
        pcntl_async_signals(true);
        
        echo "Graceful shutdown handlers registered (SIGTERM, SIGINT)\n";
    }
    
    /**
     * 处理关闭信号
     */
    public function handleShutdown(int $signal): void
    {
        if ($this->shutdownRequested) {
            echo "\nForce shutdown requested, exiting immediately...\n";
            exit(1);
        }
        
        $signalName = $signal === SIGTERM ? 'SIGTERM' : 'SIGINT';
        echo "\n\nReceived {$signalName}, initiating graceful shutdown...\n";
        echo "Press Ctrl+C again to force shutdown\n\n";
        
        $this->shutdownRequested = true;
        
        // 执行关闭回调
        $this->executeShutdownCallbacks();
        
        // 设置超时强制退出
        $this->shutdownTimerId = Timer::add($this->gracePeriod, function() {
            echo "\nGraceful shutdown timeout ({$this->gracePeriod}s), forcing exit...\n";
            $this->forceShutdown();
        }, [], false);
        
        // 停止接受新任务，等待现有任务完成
        $this->initiateShutdown();
    }
    
    /**
     * 开始关闭流程
     */
    private function initiateShutdown(): void
    {
        $loop = EventLoop::getInstance();
        $fibers = $loop->getActiveFibers();
        
        echo "Waiting for " . count($fibers) . " active task(s) to complete...\n";
        
        // 定期检查任务是否全部完成
        $checkTimer = Timer::add(0.5, function() use (&$checkTimer) {
            $loop = EventLoop::getInstance();
            $fibers = $loop->getActiveFibers();
            $activeCount = count($fibers);
            
            if ($activeCount === 0) {
                echo "All tasks completed, shutting down gracefully\n";
                
                // 取消定时器
                if ($checkTimer) {
                    Timer::del($checkTimer);
                }
                if ($this->shutdownTimerId) {
                    Timer::del($this->shutdownTimerId);
                }
                
                // 停止事件循环
                Worker::stopAll();
                exit(0);
            } else {
                echo "  Still waiting for {$activeCount} task(s)...\n";
            }
        }, [], true);
    }
    
    /**
     * 强制关闭
     */
    private function forceShutdown(): void
    {
        echo "Force shutting down...\n";
        
        // 执行清理
        Timer::delAll();
        Worker::stopAll();
        
        exit(1);
    }
    
    /**
     * 注册关闭回调
     */
    public function onShutdown(callable $callback): void
    {
        $this->shutdownCallbacks[] = $callback;
    }
    
    /**
     * 执行所有关闭回调
     */
    private function executeShutdownCallbacks(): void
    {
        foreach ($this->shutdownCallbacks as $callback) {
            try {
                echo "  Executing shutdown callback...\n";
                $callback();
            } catch (\Throwable $e) {
                echo "  Error in shutdown callback: " . $e->getMessage() . "\n";
            }
        }
    }
    
    /**
     * 检查是否已请求关闭
     */
    public function isShutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }
    
    /**
     * 设置优雅关闭等待时间
     */
    public function setGracePeriod(int $seconds): void
    {
        $this->gracePeriod = $seconds;
    }
    
    /**
     * 获取优雅关闭等待时间
     */
    public function getGracePeriod(): int
    {
        return $this->gracePeriod;
    }
}

