<?php

declare(strict_types=1);

namespace QETG\Presentation\Http;

final class QuizQuestionInput
{
    public static function normalize(mixed $rawQuestions): ?array
    {
        if (!is_array($rawQuestions) || count($rawQuestions) < 1 || count($rawQuestions) > 100) {
            return null;
        }
        $questions = array();
        foreach ($rawQuestions as $question) {
            if (!is_array($question)) {
                return null;
            }
            $text = $question['text'] ?? '';
            $explanation = $question['explanation'] ?? '';
            $answers = $question['answers'] ?? null;
            $correct = filter_var($question['correct'] ?? null, FILTER_VALIDATE_INT);
            $difficulty = filter_var($question['difficulty'] ?? null, FILTER_VALIDATE_INT);
            if (!is_string($text) || strlen($text) > 1000 || !is_string($explanation) || strlen($explanation) > 2000
                || !is_array($answers) || count($answers) > 5 || $correct === false || $difficulty === false) {
                return null;
            }
            foreach ($answers as $answer) {
                if (!is_string($answer) || strlen($answer) > 200) {
                    return null;
                }
            }
            $questions[] = array(
                'text' => $text, 'difficulty' => $difficulty, 'answers' => array_values($answers),
                'correct' => $correct, 'explanation' => $explanation,
            );
        }
        return $questions;
    }
}