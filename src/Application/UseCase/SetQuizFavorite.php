<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

final class SetQuizFavorite
{
    public function __construct(private QuizStatisticRepositoryInterface $statistics)
    {
    }

    public function execute(int $memberId, int $quizId, bool $isFavorite): QuizStatistic
    {
        if ($memberId < 1 || $quizId < 1) {
            throw new InvalidArgumentException('Quiz ou compte invalide.');
        }

        $statistic = $this->statistics->findByMemberAndQuiz($memberId, $quizId);
        if ($statistic === null) {
            throw new InvalidArgumentException('Seuls les quiz déjà effectués peuvent être ajoutés aux favoris.');
        }

        return $this->statistics->save(new QuizStatistic(
            $statistic->memberId,
            $statistic->quizId,
            $statistic->playedAt,
            $statistic->score,
            $isFavorite,
            $statistic->isMemberQuiz
        ));
    }
}
