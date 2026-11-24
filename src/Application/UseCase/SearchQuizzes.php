<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Repository\QuizRepositoryInterface;

final class SearchQuizzes
{
    public function __construct(private QuizRepositoryInterface $quizzes)
    {
    }

    /** @return list<Quiz> */
    public function execute(string $searchTerm, int $limit = 50): array
    {
        $searchTerm = trim($searchTerm);
        if ($searchTerm === '') {
            return array();
        }
        if (strlen($searchTerm) > 200) {
            throw new InvalidArgumentException('La recherche ne peut pas dépasser 200 caractères.');
        }

        return $this->quizzes->searchVisibleByName($searchTerm, max(1, min(100, $limit)));
    }
}