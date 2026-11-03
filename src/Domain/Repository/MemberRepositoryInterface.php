<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Member;

interface MemberRepositoryInterface
{
    public function findById(int $id): ?Member;

    public function findByLogin(string $login): ?Member;

    public function findByEmail(string $email): ?Member;

    public function save(Member $member): Member;

    public function delete(int $id): void;
}