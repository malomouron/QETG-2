<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\PendingRegistration;
use QETG\Domain\Repository\PendingRegistrationRepositoryInterface;

final class MySqlPendingRegistrationRepository extends MySqlRepository implements PendingRegistrationRepositoryInterface
{
    public function findById(int $id): ?PendingRegistration
    {
        $row = $this->one('SELECT * FROM quiz_cle WHERE id_cle = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByConfirmationToken(string $token): ?PendingRegistration
    {
        $row = $this->one('SELECT * FROM quiz_cle WHERE cle = ? LIMIT 1', $token);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByLogin(string $login): ?PendingRegistration
    {
        $row = $this->one('SELECT * FROM quiz_cle WHERE cle_login = ? LIMIT 1', $login);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByEmail(string $email): ?PendingRegistration
    {
        $row = $this->one('SELECT * FROM quiz_cle WHERE cle_email = ? LIMIT 1', $email);
        return $row === null ? null : $this->hydrate($row);
    }

    public function save(PendingRegistration $registration): PendingRegistration
    {
        $values = array(
            $registration->confirmationToken,
            $registration->login,
            $registration->passwordHash,
            $registration->email,
            $registration->lastName,
            $registration->firstName,
            (int) $registration->newsletterEnabled,
            $registration->createdOn,
        );
        if ($registration->id === null) {
            $this->execute(
                'INSERT INTO quiz_cle (cle, cle_login, cle_pass_md5, cle_email, cle_nom, cle_prenom, cle_newletter, cle_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                ...$values
            );
            return new PendingRegistration(
                $this->lastInsertId(),
                $registration->confirmationToken,
                $registration->login,
                $registration->passwordHash,
                $registration->email,
                $registration->lastName,
                $registration->firstName,
                $registration->newsletterEnabled,
                $registration->createdOn
            );
        }
        $this->execute(
            'UPDATE quiz_cle SET cle = ?, cle_login = ?, cle_pass_md5 = ?, cle_email = ?, cle_nom = ?, cle_prenom = ?, cle_newletter = ?, cle_date = ? WHERE id_cle = ?',
            ...array_merge($values, array($registration->id))
        );
        return $registration;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_cle WHERE id_cle = ?', $id);
    }

    private function hydrate(array $row): PendingRegistration
    {
        return new PendingRegistration(
            (int) $row['id_cle'],
            (string) $row['cle'],
            (string) $row['cle_login'],
            (string) $row['cle_pass_md5'],
            (string) $row['cle_email'],
            (string) $row['cle_nom'],
            (string) $row['cle_prenom'],
            (bool) $row['cle_newletter'],
            (string) $row['cle_date']
        );
    }
}