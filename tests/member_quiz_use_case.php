<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Application\UseCase\SubmitMemberQuiz;
use QETG\Domain\Entity\Answer;
use QETG\Domain\Entity\Question;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

function assertMemberQuizSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

$question = new Question(10, 5, null, null, 2, 'Deux fois trois ?', 1, 2);
$answers = array(
    new Answer(20, 10, null, '6', true, 'Deux fois trois font six.', 1),
    new Answer(21, 10, null, '8', false, null, 2),
);
$questionRepository = new class($question) implements QuestionRepositoryInterface {
    public function __construct(private Question $question) {}
    public function findById(int $id): ?Question { return $id === $this->question->id ? $this->question : null; }
    public function findByQuizAndNumber(int $quizId, int $number): ?Question { return $quizId === $this->question->quizId && $number === 1 ? $this->question : null; }
    public function findByQuizId(int $quizId): array { return $quizId === $this->question->quizId ? array($this->question) : array(); }
    public function save(Question $question): Question { return $question; }
    public function delete(int $id): void { unset($id); }
    public function deleteByQuizId(int $quizId): void { unset($quizId); }
};
$answerRepository = new class($answers) implements AnswerRepositoryInterface {
    public function __construct(private array $answers) {}
    public function findById(int $id): ?Answer { foreach ($this->answers as $answer) { if ($answer->id === $id) return $answer; } return null; }
    public function findByQuestionId(int $questionId): array { return array_values(array_filter($this->answers, static fn (Answer $answer): bool => $answer->questionId === $questionId)); }
    public function save(Answer $answer): Answer { return $answer; }
    public function delete(int $id): void { unset($id); }
    public function deleteByQuestionId(int $questionId): void { unset($questionId); }
};
$statistics = new class implements QuizStatisticRepositoryInterface {
    public array $saved = array();
    public function findByMemberAndQuiz(int $memberId, int $quizId): ?QuizStatistic { unset($memberId, $quizId); return null; }
    public function findByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function findFavoritesByMemberId(int $memberId): array { unset($memberId); return array(); }
    public function countOfficialByMemberId(int $memberId): int { unset($memberId); return 0; }
    public function save(QuizStatistic $statistic): QuizStatistic { $this->saved[] = $statistic; return $statistic; }
    public function deleteByMemberAndQuiz(int $memberId, int $quizId): void { unset($memberId, $quizId); }
};

$useCase = new SubmitMemberQuiz($questionRepository, $answerRepository, $statistics);
$quiz = new Quiz(5, 'Quiz test', '', 5, 9, true, null, null, true, 1, true, 0);
$result = $useCase->execute($quiz, array(10 => 20), 42);
assertMemberQuizSame(1, $result['correctCount'], 'A correct answer should increase the score.');
assertMemberQuizSame(1, $result['totalQuestions'], 'Score should use the actual question total.');
assertMemberQuizSame(true, $result['answers'][0]['isCorrect'], 'The result should report a correct choice.');
assertMemberQuizSame(1, count($statistics->saved), 'A member completion should be recorded once.');
assertMemberQuizSame(true, $statistics->saved[0]->isMemberQuiz, 'Saved statistics should mark a member-created quiz.');

try {
    $useCase->execute($quiz, array(10 => 999), null);
    throw new RuntimeException('An answer from another question or unknown answer must be rejected.');
} catch (InvalidArgumentException $exception) {
    assertMemberQuizSame('Une réponse ne correspond pas à sa question.', $exception->getMessage(), 'Invalid answers should give a validation error.');
}

fwrite(STDOUT, "Member quiz use-case checks passed.\n");
