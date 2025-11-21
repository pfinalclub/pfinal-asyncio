<?php

namespace PfinalClub\Asyncio\Http;

/**
 * HTTP 重试策略
 * 
 * 实现指数退避算法和智能重试逻辑
 * 
 * @example
 * ```php
 * $retry = new RetryPolicy(
 *     maxRetries: 3,
 *     initialDelay: 0.1,
 *     maxDelay: 10.0,
 *     backoffMultiplier: 2.0
 * );
 * 
 * $client = new AsyncHttpClient([
 *     'retry_policy' => $retry
 * ]);
 * ```
 */
class RetryPolicy
{
    /**
     * @param int $maxRetries 最大重试次数
     * @param float $initialDelay 初始延迟（秒）
     * @param float $maxDelay 最大延迟（秒）
     * @param float $backoffMultiplier 退避乘数
     * @param array $retryableStatusCodes 可重试的 HTTP 状态码
     * @param array $retryableExceptions 可重试的异常类型
     * @param bool $respectRetryAfter 是否遵守 Retry-After 头
     */
    public function __construct(
        private int $maxRetries = 3,
        private float $initialDelay = 0.1,
        private float $maxDelay = 10.0,
        private float $backoffMultiplier = 2.0,
        private array $retryableStatusCodes = [408, 429, 500, 502, 503, 504],
        private array $retryableExceptions = [\RuntimeException::class, \Exception::class],
        private bool $respectRetryAfter = true
    ) {
        if ($maxRetries < 0) {
            throw new \InvalidArgumentException("maxRetries must be non-negative, got: {$maxRetries}");
        }
        if ($initialDelay <= 0) {
            throw new \InvalidArgumentException("initialDelay must be positive, got: {$initialDelay}");
        }
        if ($backoffMultiplier < 1.0) {
            throw new \InvalidArgumentException("backoffMultiplier must be >= 1.0, got: {$backoffMultiplier}");
        }
    }
    
    /**
     * 计算重试延迟（指数退避 + 抖动）
     * 
     * @param int $attempt 尝试次数（1-based）
     * @param ?HttpResponse $response 响应对象（用于检查 Retry-After 头）
     * @return float 延迟秒数
     */
    public function getRetryDelay(int $attempt, ?HttpResponse $response = null): float
    {
        // 检查 Retry-After 头
        if ($this->respectRetryAfter && $response) {
            $retryAfter = $response->getHeader('Retry-After');
            if ($retryAfter) {
                // Retry-After 可以是秒数或 HTTP 日期
                if (is_numeric($retryAfter)) {
                    return min((float)$retryAfter, $this->maxDelay);
                } else {
                    $timestamp = strtotime($retryAfter);
                    if ($timestamp !== false) {
                        $delay = max(0, $timestamp - time());
                        return min($delay, $this->maxDelay);
                    }
                }
            }
        }
        
        // 指数退避
        $delay = $this->initialDelay * pow($this->backoffMultiplier, $attempt - 1);
        
        // 添加抖动（jitter）避免惊群效应
        // 抖动范围：±20%
        $jitter = $delay * 0.2 * (mt_rand() / mt_getrandmax() - 0.5);
        $delay += $jitter;
        
        // 限制最大延迟
        return min($delay, $this->maxDelay);
    }
    
    /**
     * 判断是否应该重试
     * 
     * @param int $attempt 当前尝试次数
     * @param ?\Throwable $exception 异常（如果有）
     * @param ?int $statusCode HTTP 状态码（如果有）
     * @return bool
     */
    public function shouldRetry(int $attempt, ?\Throwable $exception = null, ?int $statusCode = null): bool
    {
        // 超过最大重试次数
        if ($attempt > $this->maxRetries) {
            return false;
        }
        
        // 检查 HTTP 状态码
        if ($statusCode !== null && in_array($statusCode, $this->retryableStatusCodes)) {
            return true;
        }
        
        // 检查异常类型
        if ($exception !== null) {
            foreach ($this->retryableExceptions as $exceptionClass) {
                if ($exception instanceof $exceptionClass) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 获取最大重试次数
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }
    
    /**
     * 检查状态码是否可重试
     */
    public function isRetryableStatusCode(int $statusCode): bool
    {
        return in_array($statusCode, $this->retryableStatusCodes);
    }
    
    /**
     * 检查异常是否可重试
     */
    public function isRetryableException(\Throwable $exception): bool
    {
        foreach ($this->retryableExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 创建默认的重试策略
     */
    public static function createDefault(): self
    {
        return new self();
    }
    
    /**
     * 创建激进的重试策略（更多重试，更快退避）
     */
    public static function createAggressive(): self
    {
        return new self(
            maxRetries: 5,
            initialDelay: 0.05,
            maxDelay: 5.0,
            backoffMultiplier: 1.5
        );
    }
    
    /**
     * 创建保守的重试策略（更少重试，更慢退避）
     */
    public static function createConservative(): self
    {
        return new self(
            maxRetries: 2,
            initialDelay: 0.5,
            maxDelay: 30.0,
            backoffMultiplier: 3.0
        );
    }
    
    /**
     * 禁用重试
     */
    public static function disabled(): self
    {
        return new self(maxRetries: 0);
    }
}

