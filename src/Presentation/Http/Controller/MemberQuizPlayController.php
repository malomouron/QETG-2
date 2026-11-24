<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\UseCase\SubmitMemberQuiz;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\MemberRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use Throwable;

final class MemberQuizPlayController
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuestionRepositoryInterface $questions,
        private AnswerRepositoryInterface $answers,
        private ImageRepositoryInterface $images,
        private MemberRepositoryInterface $members,
        private SubmitMemberQuiz $submitQuiz,
        private string $basePath
    ) {
    }

    public function show(): void
    {
        $quiz = $this->requestedQuiz();
        if (!$this->isPlayable($quiz)) {
            $this->notFound();
        }
        $questions = $this->questions->findByQuizId((int) $quiz->id);
        if ($questions === array()) {
            $this->notFound();
        }

        $this->quizzes->recordView((int) $quiz->id);
        $owner = $this->members->findById($quiz->creatorMemberId);
        $image = $quiz->imageId === null ? null : $this->images->findById($quiz->imageId);
        $answersByQuestion = array();
        $imagesByAnswer = array();
        foreach ($questions as $question) {
            $answersByQuestion[(int) $question->id] = $this->answers->findByQuestionId((int) $question->id);
            foreach ($answersByQuestion[(int) $question->id] as $answer) {
                $imagesByAnswer[(int) $answer->id] = $answer->imageId === null ? null : $this->images->findById($answer->imageId);
            }
        }
        $result = null;
        $error = $this->takeFlash();
        $basePath = $this->basePath;
        $csrfToken = $this->csrfToken();
        require dirname(__DIR__, 2) . '/View/member-quiz-play.php';
    }

    public function submit(): void
    {
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du quiz a expiré. Recommencez.');
            $this->redirectToQuiz($_POST['quiz_id'] ?? null);
        }

        $quiz = $this->requestedQuiz($_POST['quiz_id'] ?? null);
        if (!$this->isPlayable($quiz)) {
            $this->notFound();
        }

        $rawAnswers = $_POST['answers'] ?? null;
        if (!is_array($rawAnswers)) {
            $this->flash('error', 'Répondez à toutes les questions avant de valider.');
            $this->redirectToQuiz($quiz->id);
        }
        $selectedAnswerIds = array();
        foreach ($rawAnswers as $questionId => $answerId) {
            $validatedQuestionId = filter_var((string) $questionId, FILTER_VALIDATE_INT);
            $validatedAnswerId = filter_var($answerId, FILTER_VALIDATE_INT);
            if ($validatedQuestionId === false || $validatedQuestionId < 1 || $validatedAnswerId === false || $validatedAnswerId < 1) {
                $this->flash('error', 'Une réponse sélectionnée est invalide.');
                $this->redirectToQuiz($quiz->id);
            }
            $selectedAnswerIds[(int) $validatedQuestionId] = (int) $validatedAnswerId;
        }

        try {
            $result = $this->submitQuiz->execute($quiz, $selectedAnswerIds, isset($_SESSION['id']) ? (int) $_SESSION['id'] : null);
        } catch (InvalidArgumentException $exception) {
            $this->flash('error', $exception->getMessage());
            $this->redirectToQuiz($quiz->id);
        } catch (Throwable $exception) {
            error_log('QETG member quiz submission failed: ' . $exception->getMessage());
            $this->flash('error', 'Votre résultat n’a pas pu être enregistré. Réessayez.');
            $this->redirectToQuiz($quiz->id);
        }

        $questions = $this->questions->findByQuizId((int) $quiz->id);
        $owner = $this->members->findById($quiz->creatorMemberId);
        $image = $quiz->imageId === null ? null : $this->images->findById($quiz->imageId);
        $answersByQuestion = array();
        $imagesByAnswer = array();
        foreach ($result['answers'] as $item) {
            $question = $item['question'];
            $answersByQuestion[(int) $question->id] = $this->answers->findByQuestionId((int) $question->id);
            foreach ($answersByQuestion[(int) $question->id] as $answer) {
                $imagesByAnswer[(int) $answer->id] = $answer->imageId === null ? null : $this->images->findById($answer->imageId);
            }
        }
        $error = null;
        $basePath = $this->basePath;
        $csrfToken = $this->csrfToken();
        require dirname(__DIR__, 2) . '/View/member-quiz-play.php';
    }

    private function requestedQuiz(mixed $rawId = null): ?Quiz
    {
        $rawId = $rawId ?? $_GET['id'] ?? $_GET['id_quiz'] ?? null;
        $quizId = filter_var($rawId, FILTER_VALIDATE_INT);
        return $quizId === false || $quizId === null || $quizId < 1 ? null : $this->quizzes->findById($quizId);
    }

    private function isPlayable(?Quiz $quiz): bool
    {
        return $quiz !== null && $quiz->isMemberCreated && $quiz->isComplete;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['member_quiz_csrf']) || !is_string($_SESSION['member_quiz_csrf'])) {
            $_SESSION['member_quiz_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['member_quiz_csrf'];
    }

    private function isValidCsrfToken(): bool
    {
        $token = (string) ($_POST['_csrf'] ?? '');
        return $token !== '' && isset($_SESSION['member_quiz_csrf']) && hash_equals((string) $_SESSION['member_quiz_csrf'], $token);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['member_quiz_flash'] = array('type' => $type, 'message' => $message);
    }

    private function takeFlash(): ?array
    {
        $flash = $_SESSION['member_quiz_flash'] ?? null;
        unset($_SESSION['member_quiz_flash']);
        return is_array($flash) ? $flash : null;
    }

    private function redirectToQuiz(mixed $rawId): never
    {
        $quizId = filter_var($rawId, FILTER_VALIDATE_INT);
        if ($quizId === false || $quizId === null || $quizId < 1) {
            $this->redirect('/quiz-membres');
        }
        $this->redirect('/quiz-membres/jouer?id=' . (int) $quizId);
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }

    private function notFound(): never
    {
        http_response_code(404);
        echo 'Quiz membre introuvable ou indisponible.';
        exit;
    }
}
