<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/bootstrap/legacy.php';

use QETG\Application\Service\RandomPasswordGenerator;
use QETG\Domain\Service\PasswordHasher;
use QETG\Presentation\View\Shared\ApplicationLayout;
use QETG\Presentation\View\Shared\ViewHelpers;

function assertSharedService(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$hasher = new PasswordHasher();
$hash = $hasher->hash('Mot de passe complexe 123!');
assertSharedService($hasher->verify('Mot de passe complexe 123!', $hash), 'PasswordHasher should verify a modern password hash.');
assertSharedService(!$hasher->verify('incorrect', $hash), 'PasswordHasher should reject an incorrect password.');
assertSharedService(!$hasher->verify('legacy', md5('legacy')), 'PasswordHasher must reject legacy MD5 hashes.');
assertSharedService(!$hasher->verify('Mot de passe complexe 123!', md5('Mot de passe complexe 123!')), 'PasswordHasher must not accept an MD5 password after the migration.');

$password = (new RandomPasswordGenerator())->generate(24);
assertSharedService(strlen($password) === 24, 'Random password generator should honor the requested length.');

ob_start();
ApplicationLayout::renderHead('Titre & test', array('/assets/css/home.css'));
$head = (string) ob_get_clean();
assertSharedService(str_contains($head, 'Titre &amp; test'), 'ApplicationLayout should escape the document title.');
assertSharedService(str_contains($head, '/assets/css/home.css'), 'ApplicationLayout should render the requested stylesheets.');
assertSharedService(ViewHelpers::escape('<quiz>') === '&lt;quiz&gt;', 'ViewHelpers should escape untrusted HTML.');
assertSharedService(ViewHelpers::assetUrl('uploads/avatar.png', '/QETG-2') === '/QETG-2/uploads/avatar.png', 'ViewHelpers should prefix local asset paths.');
assertSharedService(ViewHelpers::assetUrl('https://cdn.example.test/avatar.png', '/QETG-2') === 'https://cdn.example.test/avatar.png', 'ViewHelpers should preserve absolute asset URLs.');
assertSharedService(ViewHelpers::assetUrl(null, '/QETG-2') === '', 'ViewHelpers should return an empty URL for a missing asset.');

ob_start();
ApplicationLayout::renderDocumentStart('Page test', array('/assets/css/home.css'), 'home-page test-page', 'Description & test');
$documentStart = (string) ob_get_clean();
assertSharedService(str_contains($documentStart, '<!doctype html>'), 'ApplicationLayout should render the document doctype.');
assertSharedService(str_contains($documentStart, 'name="viewport"'), 'ApplicationLayout should render the responsive viewport.');
assertSharedService(str_contains($documentStart, 'content="Description &amp; test"'), 'ApplicationLayout should escape the meta description.');
assertSharedService(str_contains($documentStart, '<body class="home-page test-page">'), 'ApplicationLayout should render the requested body classes.');
assertSharedService(str_contains($documentStart, '/assets/js/site.js'), 'ApplicationLayout should load the shared external site script.');
assertSharedService(str_contains($head, '/assets/js/cookie-consent.js'), 'ApplicationLayout should load the external cookie configuration.');
assertSharedService(!str_contains($head, 'tarteaucitron.init('), 'The shared document head should not contain inline JavaScript.');

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$expectedBasePath = '/' . basename(dirname(__DIR__));
$basePath = ApplicationLayout::basePath();
$currentPage = '';
ob_start();
require dirname(__DIR__) . '/src/Presentation/View/Shared/site-header.php';
$siteHeader = (string) ob_get_clean();
assertSharedService(str_contains($siteHeader, $expectedBasePath . '/assets/images/community/icon.png'), 'Shared site header should use canonical image assets.');
assertSharedService(str_contains($siteHeader, $expectedBasePath . '/quiz-membres'), 'Shared site header should link to the routed community page.');
assertSharedService(str_contains($siteHeader, 'Créer un quiz'), 'The shared site header should expose the same navigation on every page.');

ob_start();
require dirname(__DIR__) . '/src/Presentation/View/Shared/site-footer.php';
$sharedFooter = (string) ob_get_clean();
assertSharedService(str_contains($sharedFooter, $expectedBasePath . '/a-propos#5'), 'The shared site footer should include the privacy link.');

assertSharedService(!str_contains($siteHeader, '/administration'), 'The administration link should be hidden from guests.');
$_SESSION = array('id' => 1, 'login' => 'admin', 'admin' => 1);
$currentPage = 'administration';
ob_start();
require dirname(__DIR__) . '/src/Presentation/View/Shared/site-header.php';
$adminHeader = (string) ob_get_clean();
assertSharedService(str_contains($adminHeader, $expectedBasePath . '/administration" aria-current="page"'), 'The shared site header should expose the routed dashboard.');

fwrite(STDOUT, "Shared service checks passed.\n");
