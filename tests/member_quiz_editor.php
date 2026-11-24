<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/legacy.php';

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
use QETG\Presentation\Http\Controller\MemberQuizEditorController;

function assertMemberEditor(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$quiz = new Quiz(15, 'Quiz existant', 'Description', 5, 42, true, null, 'uploads/quizzes/existing.png', true, 1, true, 2);
$quizRepository = new class($quiz) implements QuizRepositoryInterface {
    public function __construct(private Quiz $quiz) {}
    public function findById(int $id): ?Quiz { return $id === $this->quiz->id ? $this->quiz : null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 0; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 0; }
    public function searchVisibleByName(string $searchTerm, int $limit): array { unset($searchTerm, $limit); return array(); }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array { unset($searchTerm, $quizId, $limit); return array(); }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array(); }
    public function save(Quiz $quiz): Quiz { return $quiz; }
    public function delete(int $id): void { unset($id); }
};
$questionRepository = new class implements QuestionRepositoryInterface {
    public function findById(int $id): ?Question { unset($id); return null; }
    public function findByQuizAndNumber(int $quizId, int $number): ?Question { unset($quizId, $number); return null; }
    public function findByQuizId(int $quizId): array { return $quizId === 15 ? array(new Question(70, 15, null, null, 2, 'Question existante ?', 1, 2)) : array(); }
    public function save(Question $question): Question { return $question; }
    public function delete(int $id): void { unset($id); }
    public function deleteByQuizId(int $quizId): void { unset($quizId); }
};
$answerRepository = new class implements AnswerRepositoryInterface {
    public function findById(int $id): ?Answer { unset($id); return null; }
    public function findByQuestionId(int $questionId): array { return $questionId === 70 ? array(new Answer(1, 70, null, 'Oui', true, 'Explication', 1), new Answer(2, 70, null, 'Non', false, null, 2)) : array(); }
    public function save(Answer $answer): Answer { return $answer; }
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
    public function run(callable $operation): mixed { return $operation(); }
};
$save = new SaveMemberQuiz($quizRepository, $questionRepository, $answerRepository, $difficultyRepository, $transactions);
$controller = new MemberQuizEditorController($quizRepository, $questionRepository, $answerRepository, $difficultyRepository, $save, '/QETG-2');
$_SESSION = array('id' => 42, 'login' => 'membre');
$_GET = array('id' => '15');
ob_start();
$controller->form();
$html = (string) ob_get_clean();
assertMemberEditor(str_contains($html, 'Modifier votre quiz'), 'The editor should render edit mode for the owner.');
assertMemberEditor(str_contains($html, 'Quiz existant'), 'The editor should preserve the current quiz title.');
assertMemberEditor(str_contains($html, 'Question existante ?'), 'Existing questions should be supplied to the dynamic editor.');
assertMemberEditor(str_contains($html, '"id":1,"label":"Simple"') && str_contains($html, '"id":2,"label":"Interm'), 'The editor should use the difficulty labels and IDs available in the database.');
assertMemberEditor(str_contains($html, 'name="_csrf"'), 'The editor form must contain a CSRF token.');
assertMemberEditor(str_contains($html, '/quiz-membres/enregistrer'), 'The editor should submit to the routed save endpoint.');
assertMemberEditor(str_contains($html, 'data-image-upload') && str_contains($html, 'type="file" name="cover"') && str_contains($html, '4 Mo maximum'), 'The editor should use the shared image picker and show its own limit.');
assertMemberEditor(!preg_match('/<input id="member-quiz-cover"[^>]*\brequired\b/', $html), 'The cover must remain optional when editing.');
$_GET = array();
ob_start();
$controller->form();
$createHtml = (string) ob_get_clean();
assertMemberEditor(preg_match('/<input id="member-quiz-cover"[^>]*\brequired\b/', $createHtml) === 1, 'A new quiz still requires a cover.');

fwrite(STDOUT, "Member quiz editor checks passed.\n");
