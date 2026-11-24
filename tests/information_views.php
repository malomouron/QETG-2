<?php

declare(strict_types=1);

session_start();
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/legacy.php';

use QETG\Presentation\Http\Controller\InformationController;

function assertInformationContains(string $needle, string $html, string $message): void
{
    if (strpos($html, $needle) === false) {
        throw new RuntimeException($message);
    }
}

$controller = new InformationController('/QETG-2');
ob_start();
$controller->about();
$aboutHtml = (string) ob_get_clean();
assertInformationContains('À propos de Quiz en tout genre', $aboutHtml, 'About page must describe the project.');
assertInformationContains('id="2"', $aboutHtml, 'About page must preserve the contact anchor.');
assertInformationContains('id="3"', $aboutHtml, 'About page must preserve the rules anchor.');
assertInformationContains('id="4"', $aboutHtml, 'About page must preserve the cookies anchor.');
assertInformationContains('id="5"', $aboutHtml, 'About page must preserve the privacy anchor.');
assertInformationContains('/QETG-2/assets/images/community/Qr_code.png', $aboutHtml, 'About page must use the canonical installation-relative QR asset.');

ob_start();
$controller->help();
$helpHtml = (string) ob_get_clean();
assertInformationContains('Centre d’aide', $helpHtml, 'Help page must render its heading.');
assertInformationContains('help-search-input', $helpHtml, 'Help page must have a functional FAQ search field.');
assertInformationContains('Comment créer un compte ?', $helpHtml, 'Help page must include a registration FAQ.');
assertInformationContains('Comment supprimer mon compte ?', $helpHtml, 'Help page must explain account deletion.');
assertInformationContains('mailto:admin@malomouron.fr', $helpHtml, 'Help page must provide a contact route.');

fwrite(STDOUT, "Information page rendering checks passed.\n");
