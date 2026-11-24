<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Application\Port\TransactionManagerInterface;
use QETG\Application\Service\QuizQuestions;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\DifficultyRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;

final class SaveMemberQuiz
{
    private QuizQuestions $quizQuestions;

    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuestionRepositoryInterface $questions,
        private AnswerRepositoryInterface $answers,
        DifficultyRepositoryInterface $difficulties,
        private TransactionManagerInterface $transactions
    ) {
        $this->quizQuestions = new QuizQuestions($questions, $answers, $difficulties);
    }

    /** @param list<array{text: string, difficulty: int, answers: list<string>, correct: int, explanation: string}> $questionData */
    public function execute(int $memberId, ?int $quizId, string $name, string $description, bool $isPublic, array $questionData, ?string $newImageSource): Quiz
    {
        $name = trim($name);
        $description = trim($description);
        if ($memberId < 1 || $name === '' || strlen($name) > 50 || strlen($description) > 5000) {
            throw new InvalidArgumentException('Le nom du quiz est obligatoire et ne peut pas dépasser 50 caractères.');
        }
        $questionData = $this->quizQuestions->validate($questionData);

        $existingQuiz = $quizId === null ? null : $this->quizzes->findById($quizId);
        if ($quizId !== null && ($existingQuiz === null || !$existingQuiz->isMemberCreated || $existingQuiz->creatorMemberId !== $memberId)) {
            throw new InvalidArgumentException('Vous ne pouvez pas modifier ce quiz.');
        }
        $source = $newImageSource ?? $existingQuiz?->source;
        if ($source === null || $source === '') {
            throw new InvalidArgumentException('Ajoutez une image de présentation au quiz.');
        }

        return $this->transactions->run(function () use ($memberId, $quizId, $existingQuiz, $name, $description, $isPublic, $questionData, $source): Quiz {
            $quiz = $this->quizzes->save(new Quiz(
                $quizId,
                $name,
                $description,
                5,
                $memberId,
                true,
                $existingQuiz?->imageId,
                $source,
                true,
                count($questionData),
                $isPublic,
                $existingQuiz?->viewCount ?? 0
            ));
            if ($quiz->id === null) {
                throw new InvalidArgumentException('Le quiz n’a pas pu être créé.');
            }

            foreach ($this->questions->findByQuizId((int) $quiz->id) as $oldQuestion) {
                $this->answers->deleteByQuestionId((int) $oldQuestion->id);
                $this->questions->delete((int) $oldQuestion->id);
            }

            $this->quizQuestions->insert($quiz->id, $questionData);
            return $quiz;
        });
    }
}
