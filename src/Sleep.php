<?php

namespace PfinalClub\Asyncio;

/**
 * Sleep - 延迟执行辅助类
 */
class Sleep
{
    private float $delay;
    
    public function __construct(float $delay)
    {
        $this->delay = $delay;
    }
    
    public function getDelay(): float
    {
        return $this->delay;
    }
}

