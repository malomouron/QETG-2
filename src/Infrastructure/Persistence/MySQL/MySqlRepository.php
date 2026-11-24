<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

/** Base adapter for MySQL-backed domain repositories. */
abstract class MySqlRepository
{
    public function __construct(protected MySqlConnection $database)
    {
    }

    /** @return array<string, mixed>|null */
    protected function one(string $sql, mixed ...$parameters): ?array
    {
        return $this->database->fetchOne($sql, ...$parameters);
    }

    /** @return list<array<string, mixed>> */
    protected function many(string $sql, mixed ...$parameters): array
    {
        return $this->database->fetchAll($sql, ...$parameters);
    }

    protected function execute(string $sql, mixed ...$parameters): void
    {
        $this->database->execute($sql, ...$parameters);
    }

    protected function lastInsertId(): int
    {
        return $this->database->lastInsertId();
    }
}