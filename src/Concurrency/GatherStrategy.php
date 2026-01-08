<?php

namespace PfinalClub\Asyncio\Concurrency;

/**
 * Gather 策略枚举
 * 
 * 定义 gather() 函数在处理多个任务时的行为
 * 
 * @api-stable
 */
enum GatherStrategy
{
    /**
     * 快速失败 - 一旦有任务失败或取消，立即取消所有其他任务并抛出异常
     */
    case FAIL_FAST;
    
    /**
     * 等待所有 - 等待所有任务完成，无论成功或失败
     * 收集所有结果和异常，最后抛出包含所有信息的 GatherException
     */
    case WAIT_ALL;
    
    /**
     * 返回部分结果 - 等待所有任务完成，返回已成功的结果
     * 不抛出异常，但会记录失败信息
     */
    case RETURN_PARTIAL;
}