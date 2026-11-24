<?php

declare(strict_types=1);

namespace QETG\Application\Port;

interface TransactionManagerInterface
{
    public function run(callable $operation): mixed;
}
