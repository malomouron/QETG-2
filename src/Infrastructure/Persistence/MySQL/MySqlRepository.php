<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

/** Base adapter for the legacy MySQLi wrapper. */
abstract class MySqlRepository
{
    public function __construct(protected \db $database)
    {
    }

    /** @return array<string, mixed>|null */
    protected function one(string $sql, mixed ...$parameters): ?array
    {
        $row = $this->database->query($sql, ...$parameters)->fetchArray();

        return $row === array() ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    protected function many(string $sql, mixed ...$parameters): array
    {
        return $this->database->query($sql, ...$parameters)->fetchAll();
    }

    protected function execute(string $sql, mixed ...$parameters): void
    {
        $this->database->query($sql, ...$parameters);
    }

    protected function lastInsertId(): int
    {
        return (int) $this->database->lastInsertID();
    }
}