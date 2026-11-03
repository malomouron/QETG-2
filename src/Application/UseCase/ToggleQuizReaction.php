<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\QuizReaction;
use QETG\Domain\Repository\QuizReactionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;

final class ToggleQuizReaction
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuizReactionRepositoryInterface $reactions
    ) {
    }

    /** Returns the new reaction value, or null when the current reaction was removed. */
    public function execute(int $quizId, string $ipAddress, int $value): ?int
    {
        if ($quizId < 1 || !\in_array($value, array(0, 1), true)) {
            throw new InvalidArgumentException('Réaction invalide.');
        }
        if ($ipAddress === '' || \strlen($ipAddress) > 45) {
            throw new InvalidArgumentException('Adresse réseau invalide.');
        }

        $quiz = $this->quizzes->findById($quizId);
        if ($quiz === null || !$quiz->isComplete || !$quiz->isPublic) {
            throw new InvalidArgumentException('Ce quiz n’accepte pas encore de réaction.');
        }

        $existing = $this->reactions->findByQuizAndIp($quizId, $ipAddress);
        if ($existing === null) {
            $this->reactions->save(new QuizReaction(null, $quizId, $ipAddress, $value, $quiz->isMemberCreated));
            return $value;
        }

        if ($existing->value === $value) {
            if ($existing->id !== null) {
                $this->reactions->delete($existing->id);
            }
            return null;
        }

        $this->reactions->save(new QuizReaction(
            $existing->id,
            $quizId,
            $ipAddress,
            $value,
            $quiz->isMemberCreated
        ));
        return $value;
    }
}
