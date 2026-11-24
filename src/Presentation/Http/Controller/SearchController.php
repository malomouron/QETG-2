<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

use InvalidArgumentException;
use QETG\Application\UseCase\SearchQuizzes;
use QETG\Domain\Repository\ImageRepositoryInterface;

final class SearchController
{
    public function __construct(
        private SearchQuizzes $searchQuizzes,
        private ImageRepositoryInterface $images,
        private string $basePath
    ) {
    }

    public function index(): void
    {
        $rawSearch = $_GET['search'] ?? '';
        $searchTerm = is_string($rawSearch) ? trim($rawSearch) : '';
        $error = null;
        $quizCards = array();

        try {
            foreach ($this->searchQuizzes->execute($searchTerm) as $quiz) {
                $quizCards[] = array(
                    'quiz' => $quiz,
                    'image' => $quiz->imageId === null ? null : $this->images->findById($quiz->imageId),
                );
            }
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }

        $basePath = $this->basePath;
        require dirname(__DIR__, 2) . '/View/search.php';
    }
}