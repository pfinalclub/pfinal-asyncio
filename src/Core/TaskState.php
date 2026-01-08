<?php

namespace PfinalClub\Asyncio\Core;

/**
 * 任务状态枚举
 * 
 * 定义任务在生命周期中的所有可能状态
 * 
 * 状态转换图：
 * ```
 * PENDING → RUNNING → COMPLETED
 *                   → FAILED
 *                   → CANCELLED
 * ```
 */
enum TaskState: string
{
    /**
     * 待处理 - 任务已创建但尚未开始执行
     */
    case PENDING = 'pending';
    
    /**
     * 运行中 - 任务正在执行
     */
    case RUNNING = 'running';
    
    /**
     * 已完成 - 任务成功完成并返回结果
     */
    case COMPLETED = 'completed';
    
    /**
     * 失败 - 任务执行过程中抛出异常
     */
    case FAILED = 'failed';
    
    /**
     * 已取消 - 任务被显式取消
     */
    case CANCELLED = 'cancelled';
    
    /**
     * 判断任务是否处于终态
     * 
     * @return bool
     */
    public function isTerminal(): bool
    {
        return match($this) {
            self::COMPLETED, self::FAILED, self::CANCELLED => true,
            default => false,
        };
    }
    
    /**
     * 判断任务是否成功完成
     * 
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this === self::COMPLETED;
    }
    
    /**
     * 判断任务是否失败
     * 
     * @return bool
     */
    public function isFailure(): bool
    {
        return $this === self::FAILED;
    }
    
    /**
     * 判断任务是否被取消
     * 
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
    
    /**
     * 获取状态的中文描述
     * 
     * @return string
     */
    public function getDescription(): string
    {
        return match($this) {
            self::PENDING => '待处理',
            self::RUNNING => '运行中',
            self::COMPLETED => '已完成',
            self::FAILED => '失败',
            self::CANCELLED => '已取消',
        };
    }
    
    /**
     * 获取状态的 emoji 图标
     * 
     * @return string
     */
    public function getEmoji(): string
    {
        return match($this) {
            self::PENDING => '⏳',
            self::RUNNING => '▶️',
            self::COMPLETED => '✅',
            self::FAILED => '❌',
            self::CANCELLED => '🚫',
        };
    }
    
    /**
     * 格式化输出状态
     * 
     * @return string
     */
    public function format(): string
    {
        return $this->getEmoji() . ' ' . $this->getDescription();
    }
    
    /**
     * 验证状态转换是否合法
     * 
     * @param TaskState $target 目标状态
     * @return bool 是否可以转换到目标状态
     */
    public function canTransitionTo(TaskState $target): bool
    {
        // 使用 match 表达式更直观地定义状态转换规则
        return match ($this) {
            self::PENDING => $target === self::RUNNING || $target === self::CANCELLED || $target === self::FAILED,
            self::RUNNING => $target === self::COMPLETED || $target === self::FAILED || $target === self::CANCELLED,
            self::COMPLETED => false,
            self::FAILED => false,
            self::CANCELLED => false,
        };
    }
}

