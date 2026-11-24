<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use Throwable;

final class MySqlTransactionManager implements \QETG\Application\Port\TransactionManagerInterface
{
    public function __construct(private MySqlConnection $database)
    {
    }

    public function run(callable $operation): mixed
    {
        $this->database->beginTransaction();
        try {
            $result = $operation();
            $this->database->commit();
            return $result;
        } catch (Throwable $exception) {
            try {
                $this->database->rollback();
            } catch (Throwable) {
                // Preserve the exception that caused the transaction to fail.
            }
            throw $exception;
        }
    }
}
