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
        if ($member->id === null) {
            $this->execute(
                'INSERT INTO quiz_membre (login, pass_md5, email, nom, prenom, admin, image_profil, newletter, ip, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                ...$values
            );
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
        $this->execute(
            'UPDATE quiz_membre SET login = ?, pass_md5 = ?, email = ?, nom = ?, prenom = ?, admin = ?, image_profil = ?, newletter = ?, ip = ?, date = ? WHERE id = ?',
            ...array_merge($values, array($member->id))
        );
        return $member;
    }

    public function delete(int $id): void
    {
        $officialQuiz = $this->one('SELECT id_quiz FROM quiz WHERE id_user = ? AND id_quiz_user = 0 LIMIT 1', $id);
        if ($officialQuiz !== null) {
            throw new \RuntimeException('Ce compte est propriétaire de quiz officiels et ne peut pas être supprimé.');
        }

        $this->database->beginTransaction();
        try {
            $this->execute(
                'DELETE FROM quiz_statistique WHERE id_user = ? OR id_quiz IN (SELECT id_quiz FROM quiz WHERE id_user = ? AND id_quiz_user = 1)',
                $id,
                $id
            );
            $this->execute('DELETE FROM quiz_subscription_membre WHERE idUser = ? OR idUserAbonnement = ?', $id, $id);
            $this->execute('DELETE FROM quiz WHERE id_user = ? AND id_quiz_user = 1', $id);
            $this->execute('DELETE FROM quiz_membre WHERE id = ?', $id);
            if (!$this->database->commit()) {
                throw new \RuntimeException('Impossible de valider la suppression du compte.');
            }
        } catch (\Throwable $exception) {
            $this->database->rollback();
            throw $exception;
        }
    }

    private function hydrate(array $row): Member
    {
        return new Member(
            (int) $row['id'],
            (string) $row['login'],
            (string) $row['pass_md5'],
            (string) $row['email'],
            (string) $row['nom'],
            (string) $row['prenom'],
            (bool) $row['admin'],
            (string) $row['image_profil'],
            (bool) $row['newletter'],
            (string) $row['ip'],
            (string) $row['date']
        );
    }
}