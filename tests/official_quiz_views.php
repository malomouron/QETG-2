<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/legacy.php';

use QETG\Domain\Entity\Answer;
use QETG\Domain\Entity\Difficulty;
use QETG\Domain\Entity\Image;
use QETG\Domain\Entity\Question;
use QETG\Domain\Entity\Quiz;

function assertQuizViewContains(string $needle, string $html, string $message): void
{
    if (strpos($html, $needle) === false) {
        throw new RuntimeException($message);
    }
}

$basePath = '/QETG-2';
$csrfToken = 'test-csrf-token';
$quiz = new Quiz(1, 'DÉMO · Les bases du HTML', 'Découvrez les premières balises.', 5, 1, false, 1, 'assets/images/community/image-quiz-HTML.png', true, 2, true, 10);
$image = new Image(1, 'assets/images/community/image-quiz-HTML.png', 'Illustration HTML');
$question = new Question(3, 1, 1, 'Question de démonstration', 1, 'Quelle balise contient le contenu visible ?', 1, 2);
$difficulty = new Difficulty(1, 'Simple');
$answers = array(
    new Answer(5, 3, 1, '<body>', true, 'Le contenu visible est placé dans body.', 1),
    new Answer(6, 3, 1, '<head>', false, 'head accueille les métadonnées.', 2),
);
$totalQuestions = 2;
$resumeNumber = 1;
$result = null;
$page = 'presentation';
ob_start();
require dirname(__DIR__) . '/src/Presentation/View/official-quiz.php';
$html = (string) ob_get_clean();
assertQuizViewContains('DÉMO · Les bases du HTML', $html, 'Presentation must render the quiz title.');
assertQuizViewContains('Commencer le quiz', $html, 'Presentation must render a start action.');
assertQuizViewContains('/QETG-2/quiz/question?id_quiz=1', $html, 'Presentation must point to the routed question page.');

$page = 'question';
ob_start();
require dirname(__DIR__) . '/src/Presentation/View/official-quiz.php';
$html = (string) ob_get_clean();
assertQuizViewContains('Quelle balise contient le contenu visible ?', $html, 'Question view must render its text.');
assertQuizViewContains('name="id_reponse"', $html, 'Question view must offer answer selection.');
assertQuizViewContains('name="_csrf"', $html, 'Answer form must include a CSRF token.');
assertQuizViewContains('Question 1', $html, 'Question view must show current position.');

$page = 'result';
$result = array(
    'correct' => false,
    'selectedAnswer' => $answers[1],
    'correctAnswer' => $answers[0],
    'restart' => true,
);
ob_start();
require dirname(__DIR__) . '/src/Presentation/View/official-quiz.php';
$html = (string) ob_get_clean();
assertQuizViewContains('Ce n’est pas la bonne réponse.', $html, 'Wrong answer feedback must render.');
assertQuizViewContains('&lt;body&gt;', $html, 'Correct answer text must be escaped.');
assertQuizViewContains('Recommencer depuis le début', $html, 'Wrong answers must offer a restart.');

fwrite(STDOUT, "Official quiz view checks passed.\n");
