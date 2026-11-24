<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\SetQuizFavorite;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

function assertFavoriteSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$repository = new class implements QuizStatisticRepositoryInterface {
    public ?QuizStatistic $statistic;

    public function __construct()
    {
        $this->statistic = new QuizStatistic(42, 17, '2026-10-25', 8, false, true);
    }

    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizStatistic
    {
        return $this->statistic !== null && $this->statistic->memberId === $memberId && $this->statistic->quizId === $quizId
            ? $this->statistic
            : null;
    }
    public function findByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function findFavoritesByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function countOfficialByMemberId(int $memberId): int { unset($memberId); return 0; }
    public function save(QuizStatistic $statistic): QuizStatistic { $this->statistic = $statistic; return $statistic; }
    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void { unset($memberId, $quizId); }
};

$service = new SetQuizFavorite($repository);
$favorite = $service->execute(42, 17, true);
assertFavoriteSame(true, $favorite->isFavorite, 'The service should mark a completed quiz as favorite.');
assertFavoriteSame('2026-10-25', $favorite->playedAt, 'Changing favorite status should preserve the completion date.');
assertFavoriteSame(8, $favorite->score, 'Changing favorite status should preserve the score.');
assertFavoriteSame(true, $favorite->isMemberQuiz, 'Changing favorite status should preserve the quiz type.');

$notFavorite = $service->execute(42, 17, false);
assertFavoriteSame(false, $notFavorite->isFavorite, 'The service should remove a quiz from favorites.');

try {
    $service->execute(42, 999, true);
    throw new RuntimeException('An unplayed quiz must not be added as a favorite.');
} catch (InvalidArgumentException $exception) {
    assertFavoriteSame('Seuls les quiz déjà effectués peuvent être ajoutés aux favoris.', $exception->getMessage(), 'Unplayed favorite should return a clear validation message.');
}

fwrite(STDOUT, "Favorite use-case checks passed.\n");
