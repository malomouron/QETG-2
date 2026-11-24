<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\UseCase\SaveMemberQuiz;
use QETG\Domain\Repository\DifficultyRepositoryInterface;
use QETG\Domain\Repository\AnswerRepositoryInterface;
use QETG\Domain\Repository\QuestionRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Presentation\Http\QuizQuestionInput;
use Throwable;

final class MemberQuizEditorController
{
    public function __construct(
        private QuizRepositoryInterface $quizzes,
        private QuestionRepositoryInterface $questions,
        private AnswerRepositoryInterface $answers,
        private DifficultyRepositoryInterface $difficulties,
        private SaveMemberQuiz $saveQuiz,
        private string $basePath
    ) {
    }

    public function form(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        $quizId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $quiz = null;
        $initialQuestions = array();
        if ($quizId !== false && $quizId !== null) {
            $quiz = $this->quizzes->findById((int) $quizId);
            if ($quiz === null || !$quiz->isMemberCreated || $quiz->creatorMemberId !== (int) $_SESSION['id']) {
                http_response_code(404);
                echo 'Quiz introuvable.';
                return;
            }
            foreach ($this->questions->findByQuizId((int) $quiz->id) as $question) {
                $answers = $this->answers->findByQuestionId((int) $question->id);
                $correctPosition = 0;
                $answerTexts = array();
                $explanation = '';
                foreach ($answers as $position => $answer) {
                    $answerTexts[] = $answer->choice;
                    if ($answer->isCorrect) {
                        $correctPosition = $position;
                        $explanation = (string) ($answer->explanation ?? '');
                    }
                }
                $initialQuestions[] = array(
                    'text' => $question->text,
                    'difficulty' => $question->difficultyId,
                    'answers' => $answerTexts,
                    'correct' => $correctPosition,
                    'explanation' => $explanation,
                );
            }
        }
        $basePath = $this->basePath;
        $difficulties = $this->difficulties->findAll();
        $csrfToken = $this->csrfToken();
        $flash = $this->takeFlash();
        require dirname(__DIR__, 2) . '/View/member-quiz-editor.php';
    }

    public function save(): void
    {
        if (!$this->requireMember()) {
            return;
        }
        if (!$this->isValidCsrfToken()) {
            $this->flash('error', 'La session du formulaire a expiré. Réessayez.');
            $this->redirect('/quiz-membres/editeur');
        }

        $quizId = null;
        if (isset($_POST['quiz_id']) && $_POST['quiz_id'] !== '') {
            $quizId = filter_var($_POST['quiz_id'], FILTER_VALIDATE_INT);
            if ($quizId === false || $quizId === null || $quizId < 1) {
                $this->flash('error', 'Identifiant de quiz invalide.');
                $this->redirect('/quiz-membres?tab=mine');
            }
        }
        $questions = QuizQuestionInput::normalize($_POST['questions'] ?? null);
        if ($questions === null) {
            $this->flash('error', 'Vérifiez les questions, les propositions et les bonnes réponses.');
            $this->redirect($quizId === null ? '/quiz-membres/editeur' : '/quiz-membres/editeur?id=' . $quizId);
        }

        $newImageSource = null;
        $storedImagePath = null;
        try {
            $newImageSource = $this->storeUploadedImage($storedImagePath);
            $visibility = $_POST['visibility'] ?? 'public';
            if (!in_array($visibility, array('public', 'private'), true)) {
                throw new InvalidArgumentException('Choisissez une visibilité valide.');
            }
            $quiz = $this->saveQuiz->execute(
                (int) $_SESSION['id'],
                $quizId,
                (string) ($_POST['title'] ?? ''),
                (string) ($_POST['description'] ?? ''),
                $visibility === 'public',
                $questions,
                $newImageSource
            );
        } catch (InvalidArgumentException $exception) {
            $this->removeStoredImage($storedImagePath);
            $this->flash('error', $exception->getMessage());
            $this->redirect($quizId === null ? '/quiz-membres/editeur' : '/quiz-membres/editeur?id=' . $quizId);
        } catch (Throwable $exception) {
            $this->removeStoredImage($storedImagePath);
            error_log('QETG member quiz save failed: ' . $exception->getMessage());
            $this->flash('error', 'Le quiz n’a pas pu être enregistré. Réessayez.');
            $this->redirect($quizId === null ? '/quiz-membres/editeur' : '/quiz-membres/editeur?id=' . $quizId);
        }

        $_SESSION['community_flash'] = array('type' => 'success', 'message' => 'Votre quiz a été enregistré et publié.');
        $this->redirect('/quiz-membres?tab=mine');
    }

    private function storeUploadedImage(?string &$storedPath): ?string
    {
        $file = $_FILES['cover'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name']) || (int) $file['size'] > 4 * 1024 * 1024) {
            throw new InvalidArgumentException('L’image doit être valide et ne pas dépasser 4 Mo.');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file((string) $file['tmp_name']);
        $extensions = array('image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp');
        if (!is_string($mime) || !isset($extensions[$mime]) || @getimagesize((string) $file['tmp_name']) === false) {
            throw new InvalidArgumentException('Formats autorisés : JPEG, PNG, GIF et WebP.');
        }
        $directory = dirname(__DIR__, 4) . '/uploads/quizzes';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new InvalidArgumentException('Le stockage des images de quiz est indisponible.');
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $absolutePath = $directory . '/' . $filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            throw new InvalidArgumentException('L’image n’a pas pu être enregistrée.');
        }
        $storedPath = $absolutePath;
        return 'uploads/quizzes/' . $filename;
    }

    private function removeStoredImage(?string $path): void
    {
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    private function requireMember(): bool
    {
        if (!isset($_SESSION['id']) || (int) $_SESSION['id'] < 1) {
            $this->flash('error', 'Connectez-vous pour créer ou modifier un quiz.');
            $this->redirect('/connexion');
        }
        return true;
    }

    private function csrfToken(): string
    {
        if (!isset($_SESSION['member_quiz_editor_csrf']) || !is_string($_SESSION['member_quiz_editor_csrf'])) {
            $_SESSION['member_quiz_editor_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['member_quiz_editor_csrf'];
    }

    private function isValidCsrfToken(): bool
    {
        $token = (string) ($_POST['_csrf'] ?? '');
        return $token !== '' && isset($_SESSION['member_quiz_editor_csrf']) && hash_equals((string) $_SESSION['member_quiz_editor_csrf'], $token);
    }

    private function flash(string $type, string $message): void
    {
        $_SESSION['member_quiz_editor_flash'] = array('type' => $type, 'message' => $message);
    }

    private function takeFlash(): ?array
    {
        $flash = $_SESSION['member_quiz_editor_flash'] ?? null;
        unset($_SESSION['member_quiz_editor_flash']);
        return is_array($flash) ? $flash : null;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $this->basePath . $path, true, 303);
        exit;
    }
}
