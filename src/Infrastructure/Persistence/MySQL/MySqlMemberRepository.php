<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Member;
use QETG\Domain\Repository\MemberRepositoryInterface;

final class MySqlMemberRepository extends MySqlRepository implements MemberRepositoryInterface
{
    public function findById(int $id): ?Member
    {
        $row = $this->one('SELECT * FROM quiz_membre WHERE id = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByLogin(string $login): ?Member
    {
        $row = $this->one('SELECT * FROM quiz_membre WHERE login = ?', $login);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByEmail(string $email): ?Member
    {
        $row = $this->one('SELECT * FROM quiz_membre WHERE email = ?', $email);
        return $row === null ? null : $this->hydrate($row);
    }

    public function searchOthers(int $excludedMemberId, string $loginTerm, int $limit): array
    {
        $rows = $this->many(
            'SELECT * FROM quiz_membre WHERE id <> ? AND login LIKE ? ORDER BY login LIMIT ?',
            $excludedMemberId,
            '%' . trim($loginTerm) . '%',
            max(1, min(100, $limit))
        );

        return array_map(fn (array $row): Member => $this->hydrate($row), $rows);
    }

    public function save(Member $member): Member
    {
        $values = array(
            $member->login,
            $member->passwordHash,
            $member->email,
            $member->lastName,
            $member->firstName,
            (int) $member->isAdministrator,
            $member->profileImage,
            (int) $member->newsletterEnabled,
            $member->registrationIp,
            $member->registeredAt,
        );
        try {
            if ($member->id === null) {
                $this->execute(
                    'INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, newletter, ip, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    ...$values
                );
            } else {
                $this->execute(
                    'UPDATE quiz_membre SET login = ?, password_hash = ?, email = ?, nom = ?, prenom = ?, admin = ?, image_profil = ?, newletter = ?, ip = ?, date = ? WHERE id = ?',
                    ...array_merge($values, array($member->id))
                );
                return $member;
            }
        } catch (\RuntimeException $exception) {
            $cause = $exception->getPrevious();
            if ($cause instanceof \mysqli_sql_exception && $cause->getCode() === 1062) {
                throw new \DomainException('Un compte utilise déjà ces informations.', 0, $exception);
            }
            throw $exception;
        }

        return new Member(
            $this->lastInsertId(),
            $member->login,
            $member->passwordHash,
            $member->email,
            $member->lastName,
            $member->firstName,
            $member->isAdministrator,
            $member->profileImage,
            $member->newsletterEnabled,
            $member->registrationIp,
            $member->registeredAt
        );
    }

    public function delete(int $id): void
    {
        (new MySqlTransactionManager($this->database))->run(fn () => $this->deleteInTransaction($id));
    }

    /** The caller must already be in a transaction on this connection. */
    public function deleteInTransaction(int $id): void
    {
        // The parent row lock also blocks new foreign-key references to this account.
        if ($this->one('SELECT id FROM quiz_membre WHERE id = ? FOR UPDATE', $id) === null) {
            return;
        }
        $officialQuiz = $this->one('SELECT id_quiz FROM quiz WHERE id_user = ? AND id_quiz_user = 0 LIMIT 1 FOR UPDATE', $id);
        if ($officialQuiz !== null) {
            throw new \DomainException('Ce compte est propriétaire de quiz officiels et ne peut pas être supprimé.');
        }

        $this->execute('DELETE FROM quiz_statistique WHERE id_user = ?', $id);
        $this->execute('DELETE FROM quiz_subscription_membre WHERE idUser = ? OR idUserAbonnement = ?', $id, $id);
        // Quiz results and other dependents of deleted quizzes cascade in the database.
        $this->execute('DELETE FROM quiz WHERE id_user = ? AND id_quiz_user = 1', $id);
        $this->execute('DELETE FROM quiz_membre WHERE id = ?', $id);
    }

    private function hydrate(array $row): Member
    {
        return new Member(
            (int) $row['id'],
            (string) $row['login'],
            (string) $row['password_hash'],
            (string) $row['email'],
            (string) $row['nom'],
            (string) $row['prenom'],
            (int) $row['admin'] === 1,
            (string) $row['image_profil'],
            (bool) $row['newletter'],
            (string) $row['ip'],
            (string) $row['date']
        );
    }
}