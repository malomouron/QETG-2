<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use QETG\Domain\Entity\MemberAchievement;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Entity\QuizProgress;
use QETG\Domain\Entity\QuizStatistic;
use QETG\Domain\Repository\AchievementRepositoryInterface;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\DifficultyRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberAchievementRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use QETG\Domain\Repository\QuizProgressRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Domain\Repository\QuizStatisticRepositoryInterface;

final class OfficialQuizController
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuestionRepositoryInterface $questions,
        private AnswerRepositoryInterface $answers,
        private ImageRepositoryInterface $images,
        private DifficultyRepositoryInterface $difficulties,
        private QuizProgressRepositoryInterface $progress,
        private QuizStatisticRepositoryInterface $statistics,
        private AchievementRepositoryInterface $achievements,
        private MemberAchievementRepositoryInterface $memberAchievements,
        private string $basePath
    ) {
    }

    public function presentation(): void
    {
        $quizId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $quiz = $quizId === false || $quizId === null ? null : $this->quizzes->findById((int) $quizId);
        if (!$this->isPlayableOfficialQuiz($quiz)) {
            $this->notFound();
        }

        $questions = $this->questions->findByQuizId((int) $quiz->id);
        if ($questions === array()) {
            $this->notFound();
        }
        $image = $quiz->imageId === null ? null : $this->images->findById($quiz->imageId);
        $resumeNumber = 1;
        if (isset($_SESSION['id'])) {
            $savedProgress = $this->progress->findByMemberAndQuiz((int) $_SESSION['id'], (int) $quiz->id);
            if ($savedProgress !== null) {
                $savedQuestion = $this->questions->findById($savedProgress->questionId);
                if ($savedQuestion !== null && $savedQuestion->number > 1 && $savedQuestion->number < \count($questions)) {
                    $resumeNumber = $savedQuestion->number;
                }
            }
        }

        $basePath = $this->basePath;
        $csrfToken = $this->csrfToken();
        $totalQuestions = \count($questions);
        $page = 'presentation';
        $result = null;
        $question = null;
        $difficulty = null;
        $answers = array();
        require dirname(__DIR__, 2) . '/View/official-quiz.php';
    }

    public function question(): void
    {
        $quizId = filter_var($_GET['id_quiz'] ?? null, FILTER_VALIDATE_INT);
        $number = filter_var($_GET['numero_question'] ?? null, FILTER_VALIDATE_INT);
        $quiz = $quizId === false || $quizId === null ? null : $this->quizzes->findById((int) $quizId);
        if (!$this->isPlayableOfficialQuiz($quiz)) {
            $this->notFound();
        }

        $questionList = $this->questions->findByQuizId((int) $quiz->id);
        $totalQuestions = \count($questionList);
        if ($totalQuestions === 0) {
            $this->notFound();
        }

        $memberId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
        $savedProgress = $memberId === null ? null : $this->progress->findByMemberAndQuiz($memberId, (int) $quiz->id);
        $guestProgress = $_SESSION['official_quiz_progress'][(int) $quiz->id] ?? null;
        $expectedNumber = 1;
        if ($savedProgress !== null) {
            $savedQuestion = $this->questions->findById($savedProgress->questionId);
            if ($savedQuestion !== null && $savedQuestion->quizId === $quiz->id) {
                $expectedNumber = $savedQuestion->number;
            }
        } elseif ($memberId === null && \is_int($guestProgress) && $guestProgress > 0) {
            $expectedNumber = $guestProgress;
        }

        if ($number === false || $number === null || $number < 1 || $number > $totalQuestions || $number !== $expectedNumber) {
            $this->redirectToQuestion((int) $quiz->id, $expectedNumber);
        }

        $question = $this->questions->findByQuizAndNumber((int) $quiz->id, $number);
        if ($question === null) {
            $this->notFound();
        }
        $answers = $this->answers->findByQuestionId((int) $question->id);
        if ($answers === array()) {
            $this->notFound();
        }

        if ($number === 1 && $savedProgress === null && $guestProgress === null) {
            $this->quizzes->recordView((int) $quiz->id);
        }
        if ($memberId !== null) {
            $this->progress->save(new QuizProgress($savedProgress?->id, $memberId, (int) $question->id));
        } else {
            $_SESSION['official_quiz_progress'][(int) $quiz->id] = $number;
        }

        $image = $question->imageId === null ? null : $this->images->findById($question->imageId);
        $difficulty = $this->difficulties->findById($question->difficultyId);
        $basePath = $this->basePath;
        $csrfToken = $this->csrfToken();
        $page = 'question';
        $result = null;
        require dirname(__DIR__, 2) . '/View/official-quiz.php';
    }

    public function answer(): void
    {
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du quiz a expiré. Recommencez la question.');
            $quizId = filter_var($_POST['id_quiz'] ?? null, FILTER_VALIDATE_INT);
            if ($quizId !== false && $quizId !== null) {
                $this->redirectToQuestion((int) $quizId, 1);
            }
            $this->redirect('/accueil');
        }

        $quizId = filter_var($_POST['id_quiz'] ?? null, FILTER_VALIDATE_INT);
        $questionId = filter_var($_POST['id_question'] ?? null, FILTER_VALIDATE_INT);
        $answerId = filter_var($_POST['id_reponse'] ?? null, FILTER_VALIDATE_INT);
        $quiz = $quizId === false || $quizId === null ? null : $this->quizzes->findById((int) $quizId);
        if (!$this->isPlayableOfficialQuiz($quiz) || $questionId === false || $questionId === null || $answerId === false || $answerId === null) {
            $this->notFound();
        }

        $question = $this->questions->findById((int) $questionId);
        $answer = $this->answers->findById((int) $answerId);
        if ($question === null || $question->quizId !== $quiz->id || $answer === null || $answer->questionId !== $question->id) {
            $this->notFound();
        }

        $memberId = isset($_SESSION['id']) ? (int) $_SESSION['id'] : null;
        $savedProgress = $memberId === null ? null : $this->progress->findByMemberAndQuiz($memberId, (int) $quiz->id);
        $expectedQuestion = $savedProgress === null ? null : $this->questions->findById($savedProgress->questionId);
        $expectedNumber = $expectedQuestion?->number ?? (int) ($_SESSION['official_quiz_progress'][(int) $quiz->id] ?? 1);
        if ($question->number !== $expectedNumber) {
            $this->redirectToQuestion((int) $quiz->id, $expectedNumber);
        }

        $questionList = $this->questions->findByQuizId((int) $quiz->id);
        $totalQuestions = \count($questionList);
        $correctAnswer = null;
        foreach ($this->answers->findByQuestionId((int) $question->id) as $candidate) {
            if ($candidate->isCorrect) {
                $correctAnswer = $candidate;
                break;
            }
        }

        if (!$answer->isCorrect) {
            if ($memberId !== null) {
                $this->progress->deleteByMemberAndQuiz($memberId, (int) $quiz->id);
            }
            unset($_SESSION['official_quiz_progress'][(int) $quiz->id]);
            $this->renderResult($quiz, $question, $answer, $correctAnswer, false, array('restart' => true));
            return;
        }

        if ($question->number >= $totalQuestions) {
            $this->completeQuiz($memberId, $quiz, $totalQuestions);
            if ($memberId !== null) {
                $this->progress->deleteByMemberAndQuiz($memberId, (int) $quiz->id);
            }
            unset($_SESSION['official_quiz_progress'][(int) $quiz->id]);
            $this->renderResult($quiz, $question, $answer, $correctAnswer, true, array('completed' => true, 'totalQuestions' => $totalQuestions));
            return;
        }

        $nextQuestion = $this->questions->findByQuizAndNumber((int) $quiz->id, $question->number + 1);
        if ($nextQuestion === null) {
            $this->notFound();
        }
        if ($memberId !== null) {
            $this->progress->save(new QuizProgress($savedProgress?->id, $memberId, (int) $nextQuestion->id));
        } else {
            $_SESSION['official_quiz_progress'][(int) $quiz->id] = $nextQuestion->number;
        }
        $this->renderResult($quiz, $question, $answer, $correctAnswer, true, array('nextNumber' => $nextQuestion->number));
    }

    private function renderResult(Quiz $quiz, $question, $selectedAnswer, $correctAnswer, bool $correct, array $result): void
    {
        $result['correct'] = $correct;
        $result['selectedAnswer'] = $selectedAnswer;
        $result['correctAnswer'] = $correctAnswer;
        $image = $selectedAnswer->imageId === null ? null : $this->images->findById($selectedAnswer->imageId);
        $difficulty = $this->difficulties->findById($question->difficultyId);
        $basePath = $this->basePath;
        $csrfToken = $this->csrfToken();
        $page = 'result';
        require dirname(__DIR__, 2) . '/View/official-quiz.php';
    }

    private function completeQuiz(?int $memberId, Quiz $quiz, int $totalQuestions): void
    {
        if ($memberId === null) {
            return;
        }

        $existing = $this->statistics->findByMemberAndQuiz($memberId, (int) $quiz->id);
        $this->statistics->save(new QuizStatistic(
            $memberId,
            (int) $quiz->id,
            date('Y-m-d H:i:s'),
            $totalQuestions,
            $existing?->isFavorite ?? false,
            false
        ));

        $completed = $this->statistics->countOfficialByMemberId($memberId);
        $totalOfficial = $this->quizzes->countOfficial();
        $milestone = null;
        if ($completed === 1) {
            $milestone = 3;
        } elseif ($completed === intdiv($totalOfficial, 2)) {
            $milestone = 2;
        } elseif ($completed === $totalOfficial) {
            $milestone = 1;
        } elseif ($completed === 5) {
            $milestone = 6;
        }

        if ($milestone !== null
            && !$this->memberAchievements->hasAchievement($memberId, $milestone)
            && $this->achievements->findById($milestone) !== null
        ) {
            $this->memberAchievements->save(new MemberAchievement(null, $milestone, $memberId, date('d/m/Y')));
        }
    }

    private function isPlayableOfficialQuiz(?Quiz $quiz): bool
    {
        return $quiz !== null && !$quiz->isMemberCreated && $quiz->isComplete;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['official_quiz_csrf']) || !\is_string($_SESSION['official_quiz_csrf'])) {
            $_SESSION['official_quiz_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['official_quiz_csrf'];
    }

    private function isValidCsrfToken(): bool
    {
        $token = (string) ($_POST['_csrf'] ?? '');
        return $token !== '' && isset($_SESSION['official_quiz_csrf']) && hash_equals((string) $_SESSION['official_quiz_csrf'], $token);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['official_quiz_flash'] = array('type' => $type, 'message' => $message);
    }

    private function redirectToQuestion(int $quizId, int $number): never
    {
        header('Location: ' . $this->basePath . '/quiz/question?id_quiz=' . $quizId . '&numero_question=' . $number, true, 303);
        exit;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        $page = 'not-found';
        $basePath = $this->basePath;
        $quiz = null;
        $image = null;
        $question = null;
        $difficulty = null;
        $answers = array();
        $result = null;
        $csrfToken = $this->csrfToken();
        require dirname(__DIR__, 2) . '/View/official-quiz.php';
        exit;
    }
}
