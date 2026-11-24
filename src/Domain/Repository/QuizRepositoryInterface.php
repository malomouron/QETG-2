<?php

declare(strict_types=1);

namespace QETG\Domain\Repository;

use QETG\Domain\Entity\Quiz;

interface QuizRepositoryInterface
{
    public function findById(int $id): ?Quiz;

    /** @return list<Quiz> */
    public function findPublic(int $limit, int $offset): array;

    public function countPublic(): int;

    /** @return list<Quiz> */
    public function findOfficial(int $limit, int $offset): array;

    public function countOfficial(): int;

    /** @return list<Quiz> */
    public function searchVisibleByName(string $searchTerm, int $limit): array;

    /** @return list<Quiz> Completed member quizzes; a private quiz is included only when addressed by its ID. */
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array;

    public function recordView(int $id): void;

    /** @return list<Quiz> */
    public function findByCreator(int $memberId): array;

    public function save(Quiz $quiz): Quiz;

    public function delete(int $id): void;
}