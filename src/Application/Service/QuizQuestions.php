<?php

declare(strict_types=1);

namespace QETG\Application\Service;

use InvalidArgumentException;
use QETG\Domain\Entity\Answer;
use QETG\Domain\Entity\Question;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\DifficultyRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use RuntimeException;

final class QuizQuestions
{
    public function __construct(
        private QuestionRepositoryInterface $questions,
        private AnswerRepositoryInterface $answers,
        private DifficultyRepositoryInterface $difficulties
    ) {
    }

    /** @return list<array{text: string, difficulty: int, answers: list<string>, correct: int, explanation: string}> */
    public function validate(array $questions): array
    {
        if (count($questions) < 1 || count($questions) > 100) {
            throw new InvalidArgumentException('Un quiz doit contenir entre 1 et 100 questions.');
        }

        $validated = [];
        foreach ($questions as $question) {
            if (!is_array($question)) {
                throw new InvalidArgumentException('Le format de la question est invalide.');
            }
            $text = $question['text'] ?? '';
            $text = is_string($text) ? trim($text) : '';
            $answers = $question['answers'] ?? [];
            $correct = $question['correct'] ?? -1;
            $difficulty = $question['difficulty'] ?? null;
            $explanation = $question['explanation'] ?? '';
            if ($text === '' || strlen($text) > 1000 || !is_array($answers) || count($answers) < 2 || count($answers) > 5) {
                throw new InvalidArgumentException('Chaque question doit avoir un texte et entre 2 et 5 propositions.');
            }
            if (!is_int($difficulty) || $this->difficulties->findById($difficulty) === null) {
                throw new InvalidArgumentException('Choisissez une difficulté valide pour chaque question.');
            }
            if (!is_int($correct) || $correct < 0 || $correct >= count($answers)) {
                throw new InvalidArgumentException('Choisissez une bonne réponse pour chaque question.');
            }
            foreach ($answers as $answer) {
                if (!is_string($answer) || trim($answer) === '' || strlen($answer) > 200) {
                    throw new InvalidArgumentException('Chaque proposition doit contenir entre 1 et 200 caractères.');
                }
            }
            if (!is_string($explanation) || strlen($explanation) > 2000) {
                throw new InvalidArgumentException('Une explication doit être un texte de 2 000 caractères maximum.');
            }
            $validated[] = [
                'text' => $text,
                'difficulty' => $difficulty,
                'answers' => array_values(array_map('trim', $answers)),
                'correct' => $correct,
                'explanation' => trim($explanation),
            ];
        }

        return $validated;
    }

    /**
     * Insert validated questions into a quiz with no questions, in the caller's transaction.
     * @param list<array{text: string, difficulty: int, answers: list<string>, correct: int, explanation: string}> $questions
     */
    public function insert(int $quizId, array $questions): void
    {
        foreach ($questions as $position => $data) {
            $question = $this->questions->save(new Question(
                null,
                $quizId,
                null,
                null,
                $data['difficulty'],
                $data['text'],
                $position + 1,
                count($data['answers'])
            ));
            if ($question->id === null) {
                throw new RuntimeException('Une question n’a pas pu être enregistrée.');
            }
            foreach ($data['answers'] as $answerPosition => $choice) {
                $isCorrect = $answerPosition === $data['correct'];
                $this->answers->save(new Answer(
                    null,
                    $question->id,
                    null,
                    $choice,
                    $isCorrect,
                    $isCorrect ? $data['explanation'] : null,
                    $answerPosition + 1
                ));
            }
        }
    }
}