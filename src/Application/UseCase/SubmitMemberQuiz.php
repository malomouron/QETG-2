<?php

declare(strict_types=1);

namespace QETG\Application\UseCase;

use InvalidArgumentException;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

final class SubmitMemberQuiz
{
    public function __construct(
        private QuestionRepositoryInterface $questions,
        private AnswerRepositoryInterface $answers,
        private QuizStatisticRepositoryInterface $statistics
    ) {
    }

    /** @param array<int, int> $selectedAnswerIds
     *  @return array{correctCount: int, totalQuestions: int, answers: list<array{question: mixed, selected: mixed, correct: mixed, isCorrect: bool}>}
     */
    public function execute(Quiz $quiz, array $selectedAnswerIds, ?int $memberId): array
    {
        if (!$quiz->isMemberCreated || !$quiz->isComplete) {
            throw new InvalidArgumentException('Ce quiz membre n’est pas disponible.');
        }

        $questions = $this->questions->findByQuizId((int) $quiz->id);
        if ($questions === array() || count($selectedAnswerIds) !== count($questions)) {
            throw new InvalidArgumentException('Répondez à toutes les questions avant de valider.');
        }

        $results = array();
        $correctCount = 0;
        foreach ($questions as $question) {
            $selectedId = $selectedAnswerIds[(int) $question->id] ?? null;
            if (!is_int($selectedId) || $selectedId < 1) {
                throw new InvalidArgumentException('Une réponse sélectionnée est invalide.');
            }
            $selected = $this->answers->findById($selectedId);
            if ($selected === null || $selected->questionId !== $question->id) {
                throw new InvalidArgumentException('Une réponse ne correspond pas à sa question.');
            }

            $correctAnswer = null;
            foreach ($this->answers->findByQuestionId((int) $question->id) as $answer) {
                if ($answer->isCorrect) {
                    $correctAnswer = $answer;
                    break;
                }
            }
            if ($correctAnswer === null) {
                throw new InvalidArgumentException('Ce quiz contient une question sans bonne réponse.');
            }
            $isCorrect = $selected->id === $correctAnswer->id;
            if ($isCorrect) {
                $correctCount++;
            }
            $results[] = array('question' => $question, 'selected' => $selected, 'correct' => $correctAnswer, 'isCorrect' => $isCorrect);
        }

        if ($memberId !== null) {
            $existing = $this->statistics->findByMemberAndQuiz($memberId, (int) $quiz->id);
            if ($existing === null) {
                $this->statistics->save(new QuizStatistic(
                    $memberId,
                    (int) $quiz->id,
                    date('Y-m-d H:i:s'),
                    $correctCount,
                    false,
                    true
                ));
            }
        }

        return array('correctCount' => $correctCount, 'totalQuestions' => count($questions), 'answers' => $results);
    }
}
