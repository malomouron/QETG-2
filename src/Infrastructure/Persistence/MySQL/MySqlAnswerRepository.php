<?php

declare(strict_types=1);

namespace QETG\Infrastructure\Persistence\MySQL;

use QETG\Domain\Entity\Answer;
use QETG\Domain\Repository\AnswerRepositoryInterface;

final class MySqlAnswerRepository extends MySqlRepository implements AnswerRepositoryInterface
{
    public function findById(int $id): ?Answer
    {
        $row = $this->one('SELECT * FROM quiz_reponse WHERE id_reponse = ?', $id);
        return $row === null ? null : $this->hydrate($row);
    }

    public function findByQuestionId(int $questionId): array
    {
        $rows = $this->many('SELECT * FROM quiz_reponse WHERE id_question = ? ORDER BY num_reponse', $questionId);
        return array_map(fn (array $row): Answer => $this->hydrate($row), $rows);
    }

    public function save(Answer $answer): Answer
    {
        $values = array(
            $answer->questionId,
            $answer->imageId,
            $answer->choice,
            $answer->isCorrect ? '1' : '0',
            $answer->explanation,
            $answer->number,
        );
        if ($answer->id === null) {
            $this->execute(
                'INSERT INTO quiz_reponse (id_question, id_image_reponse, choix_possible_quiz, reponseOK, texte_reponse_explicatif, num_reponse) VALUES (?, ?, ?, ?, ?, ?)',
                ...$values
            );
            return new Answer($this->lastInsertId(), $answer->questionId, $answer->imageId, $answer->choice, $answer->isCorrect, $answer->explanation, $answer->number);
        }
        $this->execute(
            'UPDATE quiz_reponse SET id_question = ?, id_image_reponse = ?, choix_possible_quiz = ?, reponseOK = ?, texte_reponse_explicatif = ?, num_reponse = ? WHERE id_reponse = ?',
            ...array_merge($values, array($answer->id))
        );
        return $answer;
    }

    public function delete(int $id): void
    {
        $this->execute('DELETE FROM quiz_reponse WHERE id_reponse = ?', $id);
    }

    public function deleteByQuestionId(int $questionId): void
    {
        $this->execute('DELETE FROM quiz_reponse WHERE id_question = ?', $questionId);
    }

    private function hydrate(array $row): Answer
    {
        return new Answer(
            (int) $row['id_reponse'],
            (int) $row['id_question'],
            $row['id_image_reponse'] === null ? null : (int) $row['id_image_reponse'],
            (string) $row['choix_possible_quiz'],
            (string) $row['reponseOK'] === '1',
            $row['texte_reponse_explicatif'] === null ? null : (string) $row['texte_reponse_explicatif'],
            $row['num_reponse'] === null ? null : (int) $row['num_reponse']
        );
    }
}