<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\Port\TransactionManagerInterface;
use QETG\Application\UseCase\SaveMemberQuiz;
use QETG\Domain\Entity\Answer;
use QETG\Domain\Entity\Difficulty;
use QETG\Domain\Entity\Question;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\DifficultyRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;

function assertSaveQuiz(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$quizRepository = new class implements QuizRepositoryInterface {
    public ?Quiz $saved = null;
    public function findById(int $id): ?Quiz { return $this->saved !== null && $this->saved->id === $id ? $this->saved : null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 0; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 0; }
    public function searchVisibleByName(string $searchTerm, int $limit): array { unset($searchTerm, $limit); return array(); }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array { unset($searchTerm, $quizId, $limit); return array(); }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array(); }
    public function save(Quiz $quiz): Quiz
    {
        $this->saved = $quiz->id === null
            ? new Quiz(15, $quiz->name, $quiz->welcomeText, $quiz->experiencePoints, $quiz->creatorMemberId, $quiz->isMemberCreated, $quiz->imageId, $quiz->source, $quiz->isComplete, $quiz->questionCount, $quiz->isPublic, $quiz->viewCount)
            : $quiz;
        return $this->saved;
    }
    public function delete(int $id): void { unset($id); }
};
$questionRepository = new class implements QuestionRepositoryInterface {
    public array $saved = array();
    public function findById(int $id): ?Question { unset($id); return null; }
    public function findByQuizAndNumber(int $quizId, int $number): ?Question { unset($quizId, $number); return null; }
    public function findByQuizId(int $quizId): array { unset($quizId); return array(); }
    public function save(Question $question): Question { $saved = new Question(count($this->saved) + 1, $question->quizId, $question->imageId, $question->welcomeText, $question->difficultyId, $question->text, $question->number, $question->answerCount); $this->saved[] = $saved; return $saved; }
    public function delete(int $id): void { unset($id); }
    public function deleteByQuizId(int $quizId): void { unset($quizId); }
};
$answerRepository = new class implements AnswerRepositoryInterface {
    public array $saved = array();
    public function findById(int $id): ?Answer { unset($id); return null; }
    public function findByQuestionId(int $questionId): array { unset($questionId); return array(); }
    public function save(Answer $answer): Answer { $this->saved[] = $answer; return $answer; }
    public function delete(int $id): void { unset($id); }
    public function deleteByQuestionId(int $questionId): void { unset($questionId); }
};
$difficultyRepository = new class implements DifficultyRepositoryInterface {
    public function findById(int $id): ?Difficulty { return in_array($id, array(1, 2), true) ? new Difficulty($id, $id === 1 ? 'Simple' : 'Intermédiaire') : null; }
    public function findAll(): array { return array(new Difficulty(1, 'Simple'), new Difficulty(2, 'Intermédiaire')); }
    public function save(Difficulty $difficulty): Difficulty { return $difficulty; }
    public function delete(int $id): void { unset($id); }
};
$transactions = new class implements TransactionManagerInterface {
    public int $calls = 0;
    public function run(callable $operation): mixed { $this->calls++; return $operation(); }
};

$service = new SaveMemberQuiz($quizRepository, $questionRepository, $answerRepository, $difficultyRepository, $transactions);
$savedQuiz = $service->execute(9, null, 'Quiz test', 'Description', false, array(
    array('text' => 'Question ?', 'difficulty' => 2, 'answers' => array('Oui', 'Non'), 'correct' => 1, 'explanation' => 'Non est la bonne réponse.'),
), 'uploads/quizzes/test.png');
assertSaveQuiz($savedQuiz->id === 15 && $savedQuiz->isComplete && !$savedQuiz->isPublic, 'A complete private quiz should be created.');
assertSaveQuiz(count($questionRepository->saved) === 1 && count($answerRepository->saved) === 2, 'The question and every answer should be saved.');
assertSaveQuiz($questionRepository->saved[0]->difficultyId === 2, 'The selected database difficulty ID should be persisted on the question.');
assertSaveQuiz($answerRepository->saved[1]->isCorrect && $answerRepository->saved[1]->explanation === 'Non est la bonne réponse.', 'The selected correct answer and explanation should be persisted.');
assertSaveQuiz($transactions->calls === 1, 'Quiz creation should be transactional.');

try {
    $service->execute(9, null, 'Quiz invalide', '', true, array(
        array('text' => 'Sans bonne réponse', 'difficulty' => 1, 'answers' => array('A', 'B'), 'correct' => 5, 'explanation' => ''),
    ), 'uploads/quizzes/test.png');
    throw new RuntimeException('An invalid correct-answer index must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSaveQuiz(str_contains($exception->getMessage(), 'bonne réponse'), 'Invalid quiz data should report a useful error.');
}

try {
    $service->execute(9, null, 'Difficulté invalide', '', true, array(
        array('text' => 'Question ?', 'difficulty' => 3, 'answers' => array('A', 'B'), 'correct' => 0, 'explanation' => ''),
    ), 'uploads/quizzes/test.png');
    throw new RuntimeException('A difficulty ID absent from the database must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertSaveQuiz(str_contains($exception->getMessage(), 'difficulté'), 'Invalid difficulty IDs should report a useful error.');
}

fwrite(STDOUT, "Save member quiz use-case checks passed.\n");
