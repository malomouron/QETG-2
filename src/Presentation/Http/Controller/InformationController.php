<?php

declare(strict_types=1);

namespace QETG\Presentation\Http\Controller;

final class InformationController
{
    public function __construct(private string $basePath)
    {
    }

    public function about(): void
    {
        $page = 'about';
        $basePath = $this->basePath;
        require dirname(__DIR__, 2) . '/View/information.php';
    }

    public function help(): void
    {
        $page = 'help';
        $basePath = $this->basePath;
        require dirname(__DIR__, 2) . '/View/information.php';
    }
}
