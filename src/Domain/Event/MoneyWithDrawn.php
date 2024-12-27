<?php

namespace App\Domain\Event;

class MoneyWithDrawn
{
    private float $amount;
    private float $newBalance;
    function __construct(float $amount, float $newBalance)
    {
        $this->amount = $amount;
        $this->newBalance = $newBalance;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getNewBalance()
    {
        return $this->newBalance;
    }
}
