<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/legacy.php';

use QETG\Application\UseCase\SearchQuizzes;
use QETG\Domain\Entity\Image;
use QETG\Domain\Entity\Quiz;
use QETG\Domain\Repository\ImageRepositoryInterface;
use QETG\Domain\Repository\QuizRepositoryInterface;
use QETG\Presentation\Http\Controller\SearchController;

function assertSearchPage(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$quiz = new Quiz(7, 'Quiz <HTML>', 'Bases du web', 5, 0, false, 3, null, true, 4, true, 0);
$quizRepository = new class($quiz) implements QuizRepositoryInterface {
    public ?string $searchTerm = null;
    public int $limit = 0;

    public function __construct(private Quiz $quiz) {}
    public function findById(int $id): ?Quiz { unset($id); return null; }
    public function findPublic(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countPublic(): int { return 0; }
    public function findOfficial(int $limit, int $offset): array { unset($limit, $offset); return array(); }
    public function countOfficial(): int { return 0; }
    public function searchVisibleByName(string $searchTerm, int $limit): array
    {
        $this->searchTerm = $searchTerm;
        $this->limit = $limit;
        return array($this->quiz);
    }
    public function findMemberQuizzes(?string $searchTerm, ?int $quizId, int $limit): array { unset($searchTerm, $quizId, $limit); return array(); }
    public function recordView(int $id): void { unset($id); }
    public function findByCreator(int $memberId): array { unset($memberId); return array(); }
    public function save(Quiz $quiz): Quiz { return $quiz; }
    public function delete(int $id): void { unset($id); }
};
$imageRepository = new class implements ImageRepositoryInterface {
    public function findById(int $id): ?Image
    {
        return $id === 3 ? new Image(3, 'assets/images/community/html.png', 'HTML illustré') : null;
    }
    public function findAll(): array { return array(); }
    public function save(Image $image): Image { return $image; }
    public function delete(int $id): void { unset($id); }
};

$_SESSION = array();
$_GET = array('search' => ' HTML ');
$controller = new SearchController(new SearchQuizzes($quizRepository), $imageRepository, '/QETG-2');
ob_start();
$controller->index();
$html = (string) ob_get_clean();

assertSearchPage($quizRepository->searchTerm === 'HTML', 'Search query should be trimmed before reaching persistence.');
assertSearchPage($quizRepository->limit === 50, 'Search should use the bounded default result limit.');
assertSearchPage(str_contains($html, 'Résultats pour « HTML »'), 'Search page should show the search term.');
assertSearchPage(str_contains($html, 'Quiz &lt;HTML&gt;'), 'Quiz title must be escaped.');
assertSearchPage(str_contains($html, '/QETG-2/quiz-officiel?id=7'), 'Official quiz result should use the routed quiz URL.');
assertSearchPage(str_contains($html, '/QETG-2/assets/images/community/html.png'), 'Search result image should use its canonical path.');

$_GET = array('search' => '   ');
ob_start();
$controller->index();
$emptyHtml = (string) ob_get_clean();
assertSearchPage(str_contains($emptyHtml, 'Que souhaitez-vous découvrir ?'), 'An empty query should render the search prompt without querying.');

$_GET = array('search' => str_repeat('x', 201));
ob_start();
$controller->index();
$longHtml = (string) ob_get_clean();
assertSearchPage(str_contains($longHtml, 'Recherche trop longue'), 'Overlong queries should display a validation message.');

fwrite(STDOUT, "Search page checks passed.\n");
