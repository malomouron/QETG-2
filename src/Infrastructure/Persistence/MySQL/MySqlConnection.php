<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use InvalidArgumentException;
use mysqli;
use mysqli_sql_exception;
use RuntimeException;
use Throwable;

/** Infrastructure service owning the mysqli connection and prepared-query lifecycle. */
final class MySqlConnection
{
    private mysqli $connection;

    public function __construct(string $host, string $username, string $password, string $database, string $charset = 'utf8mb4')
    {
        if (trim($database) === '') {
            throw new InvalidArgumentException('Le nom de la base doit être configuré avant de connecter MySQL.');
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $this->connection = new mysqli($host, $username, $password, $database);
            $this->connection->set_charset($charset);
        } catch (mysqli_sql_exception $exception) {
            throw new RuntimeException('Connexion à la base de données impossible.', 0, $exception);
        }
    }

    /** @return array<string, mixed>|null */
    public function fetchOne(string $sql, mixed ...$parameters): ?array
    {
        $statement = $this->prepareAndExecute($sql, $parameters);
        try {
            $result = $statement->get_result();
            $row = $result->fetch_assoc();
            return $row === null ? null : $row;
        } finally {
            $statement->close();
        }
    }

    /** @return list<array<string, mixed>> */
    public function fetchAll(string $sql, mixed ...$parameters): array
    {
        $statement = $this->prepareAndExecute($sql, $parameters);
        try {
            $result = $statement->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } finally {
            $statement->close();
        }
    }

    public function execute(string $sql, mixed ...$parameters): int
    {
        $statement = $this->prepareAndExecute($sql, $parameters);
        try {
            return $statement->affected_rows;
        } finally {
            $statement->close();
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->connection->insert_id;
    }

    public function beginTransaction(): void
    {
        $this->connection->begin_transaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollback(): void
    {
        $this->connection->rollback();
    }

    public function close(): void
    {
        $this->connection->close();
    }

    /** @param list<mixed> $parameters */
    private function prepareAndExecute(string $sql, array $parameters): \mysqli_stmt
    {
        try {
            $statement = $this->connection->prepare($sql);
            if ($parameters !== array()) {
                $types = '';
                $values = array();
                foreach ($parameters as $parameter) {
                    $types .= match (true) {
                        is_int($parameter), is_bool($parameter) => 'i',
                        is_float($parameter) => 'd',
                        $parameter === null => 's',
                        default => 's',
                    };
                    $values[] = $parameter;
                }
                $bindArguments = array($types);
                foreach ($values as &$value) {
                    $bindArguments[] = &$value;
                }
                unset($value);
                call_user_func_array(array($statement, 'bind_param'), $bindArguments);
            }
            $statement->execute();
            return $statement;
        } catch (Throwable $exception) {
            if (isset($statement) && $statement instanceof \mysqli_stmt) {
                $statement->close();
            }
            throw new RuntimeException('La requête de base de données a échoué.', 0, $exception);
        }
    }
}
