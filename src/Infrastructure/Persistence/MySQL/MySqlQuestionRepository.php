<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Question;
use QETG\Domain\Repository\QuestionRepositoryInterface;

final class MySqlQuestionRepository extends MySqlRepository implements QuestionRepositoryInterface
{
    public function findById(int $id): ?Question
    {
        $row = $this->one('SELECT * FROM quiz_question WHERE id_question = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByQuizAndNumber(int $quizId, int $number): ?Question
    {
        $row = $this->one(
            'SELECT * FROM quiz_question WHERE id_quiz = ? AND numero_question = ? LIMIT 1',
            $quizId,
            $number
        );
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByQuizId(int $quizId): array
    {
        $rows = $this->many('SELECT * FROM quiz_question WHERE id_quiz = ? ORDER BY numero_question', $quizId);
        return array_map(fn (array $row): Question => $this->hydrate($row), $rows);
    }

    public function save(Question $question): Question
    {
        $values = array(
            $question->quizId,
            $question->imageId,
            $question->welcomeText,
            $question->difficultyId,
            $question->text,
            $question->number,
            $question->answerCount,
        );
        if ($question->id === null) {
            $this->execute(
                'INSERT INTO quiz_question (id_quiz, id_image_question, texte_bienvenue, id_difficulte, question_texte, numero_question, nbrReponse) VALUES (?, ?, ?, ?, ?, ?, ?)',
                ...$values
            );
            return new Question($this->lastInsertId(), ...$values);
        }
        $this->execute(
            'UPDATE quiz_question SET id_quiz = ?, id_image_question = ?, texte_bienvenue = ?, id_difficulte = ?, question_texte = ?, numero_question = ?, nbrReponse = ? WHERE id_question = ?',
            ...array_merge($values, array($question->id))
        );
        return $question;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_question WHERE id_question = ?', $id);
    }

    public function deleteByQuizId(int $quizId): void
    {
        $this->execute('DELETE FROM quiz_question WHERE id_quiz = ?', $quizId);
    }

    private function hydrate(array $row): Question
    {
        return new Question(
            (int) $row['id_question'],
            (int) $row['id_quiz'],
            $row['id_image_question'] === null ? null : (int) $row['id_image_question'],
            $row['texte_bienvenue'] === null ? null : (string) $row['texte_bienvenue'],
            (int) $row['id_difficulte'],
            (string) $row['question_texte'],
            (int) $row['numero_question'],
            (int) $row['nbrReponse']
        );
    }
}