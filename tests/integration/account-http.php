<?php

declare(strict_types=1);

use QETG\Application\Service\NewsletterUnsubscribeToken;
use QETG\Domain\Entity\Member;
use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli' || getenv('QETG_ENV_CONFIG') !== '1' || getenv('DB_HOST') !== 'db'
    || getenv('DB_DATABASE') !== 'test' || getenv('DB_USERNAME') !== 'qetg_ci' || getenv('DB_PASSWORD') !== 'ci-app-only') {
    throw new RuntimeException('Run this fixture only through the isolated tests Compose environment.');
}

function accountHttpCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class AccountHttpBrowser
{
    private array $cookies = [];

    public function request(string $path, int $status, ?array $form = null, ?string $location = null): string
    {
        accountHttpCheck(str_starts_with($path, '/') && !str_starts_with($path, '//'), 'Expected an app-relative path.');
        $method = $form === null ? 'GET' : 'POST';
        $headers = ['Connection: close'];
        if ($this->cookies !== []) $headers[] = 'Cookie: ' . implode('; ', $this->cookies);
        $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 10, 'header' => $headers];
        if ($form !== null) {
            $options['header'][] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($form);
        }
        $stream = @fopen('http://127.0.0.1' . $path, 'r', false, stream_context_create(['http' => $options]));
        accountHttpCheck($stream !== false, "No HTTP response for $method $path.");
        $responseHeaders = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = stream_get_contents($stream);
        fclose($stream);
        accountHttpCheck($body !== false, "Could not read the HTTP response for $method $path.");
        $statusLine = $responseHeaders[0] ?? '(missing status)';
        accountHttpCheck(preg_match('~^HTTP/\S+ (\d{3})\b~', $statusLine, $match) === 1 && (int) $match[1] === $status,
            "$method $path: expected HTTP $status, got $statusLine.");
        $actualLocation = null;
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Location:') === 0) $actualLocation = trim(substr($header, strlen('Location:')));
            if (preg_match('/^Set-Cookie:\s*([^=;]+)=([^;]*)/i', $header, $match) === 1) $this->cookies[$match[1]] = $match[1] . '=' . $match[2];
        }
        if ($location !== null) accountHttpCheck($actualLocation === $location, "$method $path: expected Location $location, got " . ($actualLocation ?? '(missing)'));
        return $body;
    }
}

function accountHttpXPath(string $body): DOMXPath
{
    $document = new DOMDocument();
    accountHttpCheck($document->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING), 'Expected HTML response.');
    return new DOMXPath($document);
}

function accountHttpToken(DOMXPath $page, string $form): string
{
    $token = $page->evaluate("string($form/input[@name='_csrf']/@value)");
    accountHttpCheck(is_string($token) && $token !== '', "Missing CSRF token: $form");
    return $token;
}

function accountHttpLogin(AccountHttpBrowser $browser, string $identity, string $password): void
{
    $page = accountHttpXPath($browser->request('/connexion', 200));
    $form = "//form[@action='/connexion' and @method='post']";
    $browser->request('/connexion', 303, ['_csrf' => accountHttpToken($page, $form), 'identity' => $identity, 'password' => $password], '/accueil');
}

$db = new MySqlConnection('db', 'qetg_ci', 'ci-app-only', 'test');
// Password-reset storage is an explicit production migration and is created only in this throwaway schema if needed.
$db->execute('CREATE TABLE IF NOT EXISTS quiz_password_reset (id INT NOT NULL AUTO_INCREMENT, member_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), UNIQUE KEY uq_quiz_password_reset_token_hash (token_hash), KEY idx_quiz_password_reset_member (member_id), CONSTRAINT fk_quiz_password_reset_member FOREIGN KEY (member_id) REFERENCES quiz_membre (id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$suffix = bin2hex(random_bytes(6));
$login = 'http-account-' . $suffix;
$email = $login . '@example.invalid';
$initialPassword = 'Initial!-' . $suffix;
$initialHash = password_hash($initialPassword, PASSWORD_DEFAULT);
$resetPassword = 'Reset!-' . $suffix;
$memberId = null;
$communityQuizId = null;
$foreignQuizId = null;
$officialQuizId = null;
$resetToken = bin2hex(random_bytes(32));
try {
    $db->execute('INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, newletter, ip, date) VALUES (?, ?, ?, ?, ?, 0, ?, 1, ?, ?)',
        $login, $initialHash, $email, 'Avant', 'Compte', 'default', '192.0.2.90', date('d/m/Y'));
    $memberId = $db->lastInsertId();
    $foreignLogin = 'http-account-foreign-' . $suffix;
    $db->execute('INSERT INTO quiz_membre (login, password_hash, email, nom, prenom, admin, image_profil, ip, date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        $foreignLogin, $initialHash, $foreignLogin . '@example.invalid', 'Autre', 'Compte', 0, 'default', '', date('d/m/Y'));
    $foreignMemberId = $db->lastInsertId();

    $db->execute('INSERT INTO quiz (nom_quiz, bienvenue, xp, id_user, id_quiz_user, quizComplet, nbrQuestion, portee) VALUES (?, ?, 5, ?, 1, 1, 0, 1)', 'Account owned ' . $suffix, 'Owned fixture', $memberId);
    $communityQuizId = $db->lastInsertId();
    $db->execute('INSERT INTO quiz (nom_quiz, bienvenue, xp, id_user, id_quiz_user, quizComplet, nbrQuestion, portee) VALUES (?, ?, 5, ?, 1, 1, 0, 1)', 'Account foreign ' . $suffix, 'Foreign fixture', $foreignMemberId);
    $foreignQuizId = $db->lastInsertId();
    $db->execute('INSERT INTO quiz (nom_quiz, bienvenue, xp, id_user, id_quiz_user, quizComplet, nbrQuestion, portee) VALUES (?, ?, 5, ?, 0, 1, 0, 1)', 'Account official ' . $suffix, 'Official fixture', $memberId);
    $officialQuizId = $db->lastInsertId();

    $browser = new AccountHttpBrowser();
    accountHttpLogin($browser, $login, $initialPassword);
    $profile = accountHttpXPath($browser->request('/compte/profil', 200));
    $updateForm = "//form[@action='/compte/profil' and @method='post']";
    $csrf = accountHttpToken($profile, $updateForm);
    $browser->request('/compte/profil', 303, ['_csrf' => 'bad-token', 'first_name' => 'Blocked', 'last_name' => 'Update']);
    accountHttpCheck($db->fetchOne('SELECT prenom FROM quiz_membre WHERE id = ?', $memberId)['prenom'] === 'Compte', 'Invalid CSRF must not update profile fields.');
    $browser->request('/compte/profil', 303, ['_csrf' => $csrf, 'first_name' => 'Après', 'last_name' => 'Compte', 'newsletter' => '1'], '/compte/profil');
    accountHttpCheck($db->fetchOne('SELECT prenom, nom, newletter FROM quiz_membre WHERE id = ?', $memberId) === ['prenom' => 'Après', 'nom' => 'Compte', 'newletter' => 1], 'A valid profile update must persist name and newsletter fields.');

    $profile = accountHttpXPath($browser->request('/compte/profil', 200));
    $deleteQuizForm = "//form[@action='/compte/quiz/supprimer' and @method='post'][input[@name='quiz_id' and @value='$communityQuizId']]";
    $quizCsrf = accountHttpToken($profile, $deleteQuizForm);
    $browser->request('/compte/quiz/supprimer', 303, ['_csrf' => $quizCsrf, 'quiz_id' => (string) $foreignQuizId], '/compte/profil');
    accountHttpCheck($db->fetchOne('SELECT id_quiz FROM quiz WHERE id_quiz = ?', $foreignQuizId) !== null, 'A member cannot delete another creator’s quiz.');
    $browser->request('/compte/quiz/supprimer', 303, ['_csrf' => $quizCsrf, 'quiz_id' => (string) $communityQuizId], '/compte/profil');
    accountHttpCheck($db->fetchOne('SELECT id_quiz FROM quiz WHERE id_quiz = ?', $communityQuizId) === null, 'A creator can delete their own community quiz.');

    $browser->request('/compte/mot-de-passe', 303, ['_csrf' => 'invalid', 'current_password' => $initialPassword, 'new_password' => $resetPassword, 'password_confirmation' => $resetPassword]);
    accountHttpCheck(password_verify($initialPassword, (string) $db->fetchOne('SELECT password_hash FROM quiz_membre WHERE id = ?', $memberId)['password_hash']), 'Invalid CSRF must not change the stored password.');
    $passwordForm = accountHttpXPath($browser->request('/compte/mot-de-passe', 200));
    $passwordCsrf = accountHttpToken($passwordForm, "//form[@action='/compte/mot-de-passe' and @method='post']");
    $browser->request('/compte/mot-de-passe', 303, ['_csrf' => $passwordCsrf, 'current_password' => 'wrong-password', 'new_password' => $resetPassword, 'password_confirmation' => $resetPassword]);
    accountHttpCheck(password_verify($initialPassword, (string) $db->fetchOne('SELECT password_hash FROM quiz_membre WHERE id = ?', $memberId)['password_hash']), 'An incorrect current password must not change the stored hash.');
    $browser->request('/compte/mot-de-passe', 303, ['_csrf' => $passwordCsrf, 'current_password' => $initialPassword, 'new_password' => $resetPassword, 'password_confirmation' => $resetPassword], '/compte/mot-de-passe');
    accountHttpCheck(password_verify($resetPassword, (string) $db->fetchOne('SELECT password_hash FROM quiz_membre WHERE id = ?', $memberId)['password_hash']), 'A valid password change must store a new verifiable password hash.');

    // The unknown-email reset path has the same response and never calls SMTP or reveals account existence.
    $unknownBrowser = new AccountHttpBrowser();
    $forgotPage = accountHttpXPath($unknownBrowser->request('/mot-de-passe-oublie', 200));
    $forgotForm = "//form[@action='/mot-de-passe-oublie' and @method='post']";
    $unknownBrowser->request('/mot-de-passe-oublie', 303, ['_csrf' => accountHttpToken($forgotPage, $forgotForm), 'email' => 'missing-' . $suffix . '@example.invalid'], '/mot-de-passe-oublie');
    $forgotResult = accountHttpXPath($unknownBrowser->request('/mot-de-passe-oublie', 200));
    accountHttpCheck(str_contains($forgotResult->evaluate('string(//*[@role="status"])'), 'Si un compte correspond'), 'Password-reset response must not disclose whether the address exists.');
    $unknownBrowser->request('/reinitialiser-mot-de-passe?token=bad-token', 200);

    $expiredToken = bin2hex(random_bytes(32));
    $db->execute('INSERT INTO quiz_password_reset (member_id, token_hash, expires_at) VALUES (?, ?, DATE_SUB(NOW(), INTERVAL 1 MINUTE))', $memberId, hash('sha256', $expiredToken));
    $expiredBrowser = new AccountHttpBrowser();
    $expiredPage = accountHttpXPath($expiredBrowser->request('/reinitialiser-mot-de-passe?token=' . $expiredToken, 200));
    accountHttpCheck(str_contains($expiredPage->evaluate('string(//p[contains(@class,"account-intro")])'), 'invalide ou a expiré')
        && $expiredPage->query('//form[@action="/reinitialiser-mot-de-passe"]')->length === 0, 'Expired reset links must show an error and must not render an active reset form.');
    accountHttpCheck($db->fetchOne('SELECT id FROM quiz_password_reset WHERE token_hash = ?', hash('sha256', $expiredToken)) === null, 'Viewing an expired reset link must delete the expired token.');

    $validResetToken = bin2hex(random_bytes(32));
    $db->execute('INSERT INTO quiz_password_reset (member_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))', $memberId, hash('sha256', $validResetToken));
    $resetBrowser = new AccountHttpBrowser();
    $resetPath = '/reinitialiser-mot-de-passe?token=' . $validResetToken;
    $resetPage = accountHttpXPath($resetBrowser->request($resetPath, 200));
    $resetForm = "//form[@action='/reinitialiser-mot-de-passe' and @method='post']";
    accountHttpCheck($resetPage->query($resetForm)->length === 1, 'A valid one-hour reset token must render its form.');
    $resetCsrf = accountHttpToken($resetPage, $resetForm);
    $resetBrowser->request('/reinitialiser-mot-de-passe', 303, [
        '_csrf' => 'invalid-reset-csrf', 'token' => $validResetToken,
        'new_password' => 'Replacement!-' . $suffix, 'password_confirmation' => 'Replacement!-' . $suffix,
    ], '/mot-de-passe-oublie');
    accountHttpCheck(password_verify($resetPassword, (string) $db->fetchOne('SELECT password_hash FROM quiz_membre WHERE id = ?', $memberId)['password_hash']), 'Invalid reset CSRF must preserve the current password hash.');
    $replacementPassword = 'Replacement!-' . $suffix;
    $resetBrowser->request('/reinitialiser-mot-de-passe', 303, [
        '_csrf' => $resetCsrf, 'token' => $validResetToken,
        'new_password' => $replacementPassword, 'password_confirmation' => $replacementPassword,
    ], '/connexion');
    accountHttpCheck(password_verify($replacementPassword, (string) $db->fetchOne('SELECT password_hash FROM quiz_membre WHERE id = ?', $memberId)['password_hash']), 'A valid reset must save the replacement password hash.');
    accountHttpCheck($db->fetchOne('SELECT id FROM quiz_password_reset WHERE token_hash = ?', hash('sha256', $validResetToken)) === null, 'A successful reset must consume the stored token.');

    // Valid signed newsletter link: GET is confirmation-only; only CSRF-protected POST mutates preference.
    $member = new Member($memberId, $login, $resetPassword, $email, 'Compte', 'Après', false, 'default', true, '192.0.2.90', date('d/m/Y'));
    $tokenService = new NewsletterUnsubscribeToken((string) getenv('APP_PRIVATE_KEY'));
    $unsubscribeToken = $tokenService->create($member);
    $newsletter = new AccountHttpBrowser();
    $confirmPath = '/newsletter/desabonnement?token=' . rawurlencode($unsubscribeToken);
    $confirm = accountHttpXPath($newsletter->request($confirmPath, 200));
    $unsubscribeForm = "//form[@action='/newsletter/desabonnement' and @method='post']";
    $unsubscribeCsrf = accountHttpToken($confirm, $unsubscribeForm);
    accountHttpCheck((int) $db->fetchOne('SELECT newletter FROM quiz_membre WHERE id = ?', $memberId)['newletter'] === 1, 'Opening a signed GET link must not unsubscribe yet.');
    $newsletter->request('/newsletter/desabonnement', 400, ['_csrf' => 'bad-token', 'token' => $unsubscribeToken]);
    accountHttpCheck((int) $db->fetchOne('SELECT newletter FROM quiz_membre WHERE id = ?', $memberId)['newletter'] === 1, 'Invalid newsletter CSRF must not mutate the preference.');
    $confirmation = $newsletter->request('/newsletter/desabonnement', 200, ['_csrf' => $unsubscribeCsrf, 'token' => $unsubscribeToken]);
    accountHttpCheck(str_contains($confirmation, 'désabonnée') && (int) $db->fetchOne('SELECT newletter FROM quiz_membre WHERE id = ?', $memberId)['newletter'] === 0, 'Valid signed POST must unsubscribe the member.');

    // Invalid current password is harmless; official quiz ownership blocks deletion; removing that fixture permits deletion.
    $profile = accountHttpXPath($browser->request('/compte/profil', 200));
    $deleteForm = "//form[@action='/compte/supprimer' and @method='post']";
    $deleteCsrf = accountHttpToken($profile, $deleteForm);
    $browser->request('/compte/supprimer', 303, ['_csrf' => $deleteCsrf, 'current_password' => 'wrong-password'], '/compte/profil');
    accountHttpCheck($db->fetchOne('SELECT id FROM quiz_membre WHERE id = ?', $memberId) !== null, 'Wrong password must preserve the account.');
    $browser->request('/compte/supprimer', 303, ['_csrf' => $deleteCsrf, 'current_password' => $replacementPassword], '/compte/profil');
    accountHttpCheck($db->fetchOne('SELECT id FROM quiz_membre WHERE id = ?', $memberId) !== null, 'Account deletion must be refused while the member owns an official quiz.');
    accountHttpCheck($db->fetchOne('SELECT id_quiz FROM quiz WHERE id_quiz = ?', $officialQuizId) !== null, 'The official quiz must remain untouched after deletion refusal.');
    $db->execute('DELETE FROM quiz WHERE id_quiz = ?', $officialQuizId);
    $profile = accountHttpXPath($browser->request('/compte/profil', 200));
    $deleteCsrf = accountHttpToken($profile, $deleteForm);
    $browser->request('/compte/supprimer', 200, ['_csrf' => $deleteCsrf, 'current_password' => $replacementPassword]);
    accountHttpCheck($db->fetchOne('SELECT id FROM quiz_membre WHERE id = ?', $memberId) === null, 'A confirmed deletion must remove the member account.');
    accountHttpCheck($db->fetchOne('SELECT id_quiz FROM quiz WHERE id_quiz = ?', $foreignQuizId) !== null, 'Deleting one account must preserve another member’s quiz.');

    echo "Account and newsletter HTTP integration checks passed.\n";
} finally {
    if ($officialQuizId !== null) $db->execute('DELETE FROM quiz WHERE id_quiz = ?', $officialQuizId);
    if ($communityQuizId !== null) $db->execute('DELETE FROM quiz WHERE id_quiz = ?', $communityQuizId);
    if ($foreignQuizId !== null) $db->execute('DELETE FROM quiz WHERE id_quiz = ?', $foreignQuizId);
    if ($memberId !== null) {
        $db->execute('DELETE FROM quiz_password_reset WHERE member_id = ?', $memberId);
        $db->execute('DELETE FROM quiz_membre WHERE id = ?', $memberId);
    }
    if (isset($foreignMemberId)) $db->execute('DELETE FROM quiz_membre WHERE id = ?', $foreignMemberId);
    $db->close();
}
