<?php

namespace PfinalClub\Asyncio\Production;

use Workerman\Worker;
use PfinalClub\Asyncio\Core\EventLoop;

/**
 * 多进程模式
 * 充分利用多核 CPU，提升并发处理能力
 */
class MultiProcessMode
{
    private static $mainCallback = null;  // callable|null - PHP <8.2 不支持 callable 作为属性类型
    private static int $workerCount = 0;
    private static array $config = [];
    
    /**
     * 启用多进程模式
     * 
     * @param callable $callback 主回调函数，在每个 Worker 进程中执行
     * @param array $config 配置选项
     *   - worker_count: Worker 进程数（默认：CPU 核心数）
     *   - name: Worker 名称（默认：AsyncIO-Worker）
     *   - daemon: 是否以守护进程运行（默认：false）
     *   - log_file: 日志文件路径（默认：./asyncio.log）
     *   - pid_file: PID 文件路径（默认：./asyncio.pid）
     *   - stdout_file: 标准输出文件（默认：/dev/null）
     * 
     * @throws \RuntimeException 如果事件循环已经初始化（与 EventLoop::run() 冲突）
     */
    public static function enable(callable $callback, array $config = []): void
    {
        // 检测与 EventLoop::run() 的冲突
        if (\Workerman\Worker::$globalEvent !== null) {
            throw new \RuntimeException(
                "Cannot enable MultiProcessMode: EventLoop is already initialized. " .
                "MultiProcessMode must be enabled BEFORE calling run(). " .
                "Both MultiProcessMode and EventLoop::run() initialize Workerman's event loop, causing conflicts."
            );
        }
        
        // 保存回调和配置
        self::$mainCallback = $callback;
        self::$config = $config;
        
        // 获取 CPU 核心数
        $cpuCount = self::getCpuCount();
        self::$workerCount = $config['worker_count'] ?? $cpuCount;
        
        // 创建 Worker
        $worker = new Worker();
        $worker->name = $config['name'] ?? 'AsyncIO-Worker';
        $worker->count = self::$workerCount;
        
        // 配置守护进程
        if (isset($config['daemon']) && $config['daemon']) {
            Worker::$daemonize = true;
        }
        
        // 配置日志文件
        Worker::$logFile = $config['log_file'] ?? getcwd() . '/asyncio.log';
        Worker::$pidFile = $config['pid_file'] ?? getcwd() . '/asyncio.pid';
        Worker::$stdoutFile = $config['stdout_file'] ?? '/dev/null';
        
        // Worker 启动回调
        $worker->onWorkerStart = function($worker) {
            echo "Worker #{$worker->id} 启动 (PID: {$worker->pid})\n";
            
            // 在每个 Worker 进程中运行事件循环
            try {
                $result = EventLoop::getInstance()->run(self::$mainCallback);
                echo "Worker #{$worker->id} 完成\n";
            } catch (\Throwable $e) {
                echo "Worker #{$worker->id} 错误: {$e->getMessage()}\n";
                error_log("Worker error: " . $e->getMessage());
            }
        };
        
        // Worker 停止回调
        $worker->onWorkerStop = function($worker) {
            echo "Worker #{$worker->id} 停止\n";
        };
        
        // Worker 重载回调
        $worker->onWorkerReload = function($worker) {
            echo "Worker #{$worker->id} 重载\n";
        };
        
        // 打印配置信息
        echo "\n=== AsyncIO 多进程模式 ===\n";
        echo "Worker 进程数: " . self::$workerCount . " (CPU 核心数: {$cpuCount})\n";
        echo "Worker 名称: {$worker->name}\n";
        echo "守护进程: " . (Worker::$daemonize ? '是' : '否') . "\n";
        echo "日志文件: " . Worker::$logFile . "\n";
        echo "PID 文件: " . Worker::$pidFile . "\n";
        echo "=============================\n\n";
        
        // 启动所有 Worker
        Worker::runAll();
    }
    
    /**
     * 获取 CPU 核心数
     */
    private static function getCpuCount(): int
    {
        // Linux/Mac
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            if (PHP_OS_FAMILY === 'Darwin') {
                $count = (int)shell_exec('sysctl -n hw.ncpu');
            } else {
                $count = (int)shell_exec('nproc');
            }
            return $count > 0 ? $count : 4;
        }
        
        // Windows
        if (PHP_OS_FAMILY === 'Windows') {
            $count = (int)getenv('NUMBER_OF_PROCESSORS');
            return $count > 0 ? $count : 4;
        }
        
        // 默认
        return 4;
    }
    
    /**
     * 获取多进程统计信息
     */
    public static function getStats(): array
    {
        return [
            'worker_count' => self::$workerCount,
            'config' => self::$config,
        ];
    }
    
    /**
     * 停止所有 Worker
     */
    public static function stop(): void
    {
        Worker::stopAll();
    }
    
    /**
     * 重载所有 Worker（优雅重启）
     */
    public static function reload(): void
    {
        Worker::reloadAllWorkers();
    }
}

