<?php

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function expect($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function requestPage($path, $post = null, $cookie = '', $upload = null)
{
    $headers = array();
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $options = array('method' => $post === null ? 'GET' : 'POST', 'ignore_errors' => true, 'follow_location' => 0);
    if ($post !== null) {
        if ($upload === null) {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($post);
        } else {
            $boundary = 'qetg-' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/form-data; boundary=' . $boundary;
            $content = '';
            foreach ($post as $name => $value) {
                $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
            }
            $content .= "--$boundary\r\nContent-Disposition: form-data; name=\"image_uploads\"; filename=\"" . $upload['name'] . "\"\r\n";
            $content .= "Content-Type: image/png\r\n\r\n" . $upload['content'] . "\r\n--$boundary--\r\n";
            $options['content'] = $content;
        }
    }
    $options['header'] = implode("\r\n", $headers);
    $body = file_get_contents('http://127.0.0.1:8123/' . $path, false, stream_context_create(array('http' => $options)));
    $responseHeaders = isset($http_response_header) ? $http_response_header : array();
    if ($body === false || !isset($responseHeaders[0])) {
        throw new RuntimeException('Request failed: ' . $path);
    }
    return array($body, $responseHeaders);
}

$root = dirname(__DIR__);
$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qetg-web-' . bin2hex(random_bytes(12));
$files = array(
    'functions.php', 'db.php', 'index.php', 'identifier.php', 'makeQuiz.php',
    'Quiz-presentation.php', 'question.php', 'reponse.php', 'bandeau.php',
    'aide.php', 'footer.php', 'afficher-commentaire.php', 'admin.php', 'changer-mdp_prof.php', 'supr-compte_prof.php', 'changer.php', 'mail.php', 'email_confirmation.php', 'confirmation.php',
    'abonnement.php', 'changer-en-favoris.php', 'changer-en-non-favoris.php', 'likedislike.php', 'likedislike_user.php', 'reponse_commentaire.php', 'signal.php', 'deleteQuiz.php', 'poster_quiz.php', 'poster_quiz_action.php',
    'commun/bandeau.php',
);
if (!mkdir($directory, 0700) || !mkdir($directory . DIRECTORY_SEPARATOR . 'commun', 0700)) {
    throw new RuntimeException('Unable to create isolated web fixture.');
}
$server = null;
try {
    foreach ($files as $file) {
        if (!copy($root . DIRECTORY_SEPARATOR . $file, $directory . DIRECTORY_SEPARATOR . $file)) {
            throw new RuntimeException('Unable to copy fixture: ' . $file);
        }
    }
    expect(mkdir($directory . '/vendor', 0700), 'Unable to create fixture vendor directory.');
    expect(mkdir($directory . '/quiz_perso', 0700), 'Unable to create fixture uploads directory.');
    expect(copy($root . '/tests/fixtures/FakePHPMailer.php', $directory . '/vendor/FakePHPMailer.php'), 'Unable to copy fake mail transport.');
    expect(file_put_contents($directory . '/vendor/autoload.php', "<?php\nrequire_once __DIR__ . '/FakePHPMailer.php';\n") !== false, 'Unable to prepare fake Composer autoloader.');
    putenv('QETG_TEST_OUTBOX=' . $directory . '/outbox.html');
    $config = <<<'PHP'
<?php
$servername = 'db';
$username = 'root';
$password = 'disposable-test-only';
$dbname = 'test';
$site_key = '';
$nombreDeQuizAfficher = 5;
$domaine = 'http://127.0.0.1:8123/';
$os = 'linux';
$smtp_host = 'fixture-smtp';
$smtp_username = 'fixture-user';
$smtp_password = 'fixture-password';
$smtp_from = 'sender@example.invalid';
PHP;
    expect(file_put_contents($directory . DIRECTORY_SEPARATOR . 'config.inc.php', $config) !== false, 'Unable to create fixture configuration.');

    $db = new mysqli('db', 'root', 'disposable-test-only', 'test');
    $statements = array(
        "INSERT INTO quiz_membre (id, login, pass_md5, email, nom, prenom, admin, ip, date) VALUES (1, 'fixture-user', MD5('fixture-password'), 'fixture@example.invalid', 'Fixture', 'User', 0, '127.0.0.1', '2026-01-01'), (2, 'delete-user', MD5('delete-password'), 'delete@example.invalid', 'Fixture', 'Delete', 0, '127.0.0.1', '2026-01-01'), (3, 'fail-user', MD5('unchanged-password'), 'fail@example.invalid', 'Fixture', 'Fail', 0, '127.0.0.1', '2026-01-01'), (4, 'long-user', MD5(CONCAT(REPEAT('a', 72), 'first')), 'long@example.invalid', 'Fixture', 'Long', 0, '127.0.0.1', '2026-01-01')",
        "INSERT INTO quiz_image (id_image, src, alt) VALUES (1, 'fixture.png', 'Fixture')",
        "INSERT INTO quiz_difficulte (id_difficulte, libelle) VALUES (1, 'Simple')",
        "INSERT INTO quiz_succes (id_succes, nom_succes, xp_succes, descri_succes) VALUES (4, 'Inscription', 1, 'Fixture')",
        "INSERT INTO quiz (id_quiz, id_image_quiz, nom_quiz, bienvenue, id_user, id_quiz_user, nbrQuestion, quizComplet, portee, src) VALUES (1, 1, 'Quiz officiel fixture', 'Bienvenue', 1, 0, 3, 1, 1, '/fixture.png'), (2, NULL, 'Quiz membre fixture', 'Bienvenue', 1, 1, 1, 1, 1, '/fixture.png')",
        "INSERT INTO quiz_question (id_question, id_quiz, id_image_question, texte_bienvenue, id_difficulte, question_texte, numero_question, nbrReponse) VALUES (1, 1, 1, 'Introduction', 1, 'Question officielle fixture', 1, 2), (2, 2, NULL, 'Introduction', 1, 'Question membre fixture', 1, 2), (3, 1, 1, 'Suite', 1, 'Deuxieme question officielle', 2, 2), (4, 1, 1, 'Fin', 1, 'Troisieme question officielle', 3, 2)",
        "INSERT INTO quiz_reponse (id_reponse, id_question, id_image_reponse, choix_possible_quiz, reponseOK, texte_reponse_explicatif, num_reponse) VALUES (1, 1, 1, 'Bonne officielle', '1', 'Explication officielle', 1), (2, 1, 1, 'Mauvaise officielle', '0', 'Recommencer', 2), (3, 2, NULL, 'Bonne membre', '1', 'Explication membre', 1), (4, 2, NULL, 'Mauvaise membre', '0', '', 2), (5, 3, 1, 'Bonne suite', '1', 'Explication suite', 1), (6, 3, 1, 'Mauvaise suite', '0', '', 2), (7, 4, 1, 'Bonne fin', '1', '', 1), (8, 4, 1, 'Mauvaise fin', '0', '', 2)",
    );
    foreach ($statements as $statement) {
        $db->query($statement);
    }

    $server = proc_open(
        array(PHP_BINARY, '-d', 'output_buffering=4096', '-d', 'opcache.enable=0', '-S', '127.0.0.1:8123', '-t', $directory),
        array(0 => array('file', '/dev/null', 'r'), 1 => array('file', '/dev/null', 'a'), 2 => array('file', '/dev/null', 'a')),
        $pipes
    );
    expect(is_resource($server), 'Unable to start fixture web server.');
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', 8123, $errorNumber, $errorMessage, 0.1);
        if ($socket !== false) {
            fclose($socket);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    expect($ready, 'Fixture web server did not start.');

    list($body, $headers) = requestPage('index.php');
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Quiz officiel fixture') !== false, 'Homepage must show the official quiz.');
    expect(strpos($body, 'href="/quiz/lien-bandeau/"') === false, 'Homepage navigation must not depend on a /quiz/ deployment path.');
    expect(strpos($body, 'href="/lien-bandeau/"') === false, 'Homepage navigation must not depend on the web root.');
    list($body, $headers) = requestPage('Quiz-presentation.php?id_quiz=1');
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Quiz officiel fixture') !== false, 'Official quiz presentation must load.');
    expect(strpos($body, 'src="commun/icon.png"') !== false && strpos($body, 'href="/quiz/lien-bandeau/"') === false, 'Shared navigation must use paths relative to the application.');
    list($body, $headers) = requestPage('question.php?id_quiz=1&numero_question=1&id_question=1');
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Question officielle fixture') !== false, 'Official question must load for a visitor.');
    list($body, $headers) = requestPage('reponse.php?id_quiz=1&id_question=1&id_reponse=1&numero_question=1&nombre_de_question=1');
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Explication officielle') !== false, 'Correct official answer must show its explanation.');

    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'fixture@example.invalid', 'pass' => 'incorrect'));
    expect(strpos($headers[0], '200') !== false && strpos($body, 'mot de passe incorrect') !== false, 'Invalid email/password must be rejected.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'fixture-user', 'pass' => 'incorrect'));
    expect(strpos($headers[0], '200') !== false && strpos($body, 'mot de passe incorrect') !== false, 'Invalid username/password must be rejected.');
    $legacy = $db->query('SELECT pass_md5 FROM quiz_membre WHERE id = 1')->fetch_assoc();
    expect($legacy['pass_md5'] === md5('fixture-password'), 'Failed login must not change the stored password.');
    $longPassword = str_repeat('a', 72) . 'first';
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'long-user', 'pass' => $longPassword));
    expect(strpos($headers[0], '302') !== false, 'A long legacy password must allow login.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'long-user', 'pass' => str_repeat('a', 72) . 'other'));
    expect(strpos($body, 'mot de passe incorrect') !== false, 'A long password must not be truncated during migration.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'fixture-user', 'pass' => 'fixture-password'));
    expect(strpos($headers[0], '302') !== false, 'Valid username/password must be accepted.');
    $upgraded = $db->query('SELECT pass_md5 FROM quiz_membre WHERE id = 1')->fetch_assoc();
    expect(password_verify('fixture-password', $upgraded['pass_md5']), 'A successful legacy login must upgrade the password hash.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'fixture@example.invalid', 'pass' => 'fixture-password'));
    expect(strpos($headers[0], '302') !== false, 'Valid member login must redirect.');
    $cookie = '';
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
            $cookie = explode(';', substr($header, strlen('Set-Cookie: ')), 2)[0];
            break;
        }
    }
    expect($cookie !== '', 'Login must create a session.');
    list($body, $headers) = requestPage('Quiz-presentation.php?id_quiz=1', null, $cookie);
    expect(strpos($headers[0], '200') !== false && strpos($body, 'id="quiz-presentation-a1"') !== false, 'Official quiz presentation must offer a start link for a member.');
    list($body, $headers) = requestPage('question.php?id_quiz=1&numero_question=1&id_question=1', null, $cookie);
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Question officielle fixture') !== false, 'Official question must load for a member.');
    $progress = $db->query('SELECT id_question FROM quiz_progression WHERE id_user = 1')->fetch_assoc();
    expect($progress !== null && (int) $progress['id_question'] === 1, 'Official quiz progress must be recorded.');
    list($body, $headers) = requestPage('Quiz-presentation.php?id_quiz=1', null, $cookie);
    expect(strpos($headers[0], '200') !== false && strpos($body, 'id="quiz-presentation-a1"') !== false, 'Official quiz presentation must load with saved progress.');
    list($body, $headers) = requestPage('question.php?id_quiz=1&numero_question=2&id_question=3', null, $cookie);
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Deuxieme question officielle') !== false, 'A member must reach the next official question.');
    $progress = $db->query('SELECT id_question FROM quiz_progression WHERE id_user = 1')->fetch_all(MYSQLI_ASSOC);
    expect(count($progress) === 1 && (int) $progress[0]['id_question'] === 3, 'Official progress must update instead of inserting a duplicate.');
    list($body, $headers) = requestPage('Quiz-presentation.php?id_quiz=1', null, $cookie);
    expect(strpos($body, 'question.php?id_quiz=1&numero_question=2') !== false, 'An unfinished official quiz must resume at the saved question.');
    list($body, $headers) = requestPage('admin.php', null, $cookie);
    expect(strpos($body, 'Acc') !== false && strpos($body, 'Administration du site') === false, 'A non-admin must not see the administration dashboard.');
    list($body, $headers) = requestPage('makeQuiz.php?id_quiz=2', null, $cookie);
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Question membre fixture') !== false, 'Member quiz must show its question.');
    list($body, $headers) = requestPage('makeQuiz.php', array('id_quiz' => 2, 'reponsepourquestion1' => 1), $cookie);
    expect(strpos($headers[0], '200') !== false && strpos($body, 'Votre note est de 1/1') !== false, 'Member quiz must calculate the score.');
    $row = $db->query('SELECT note FROM quiz_statistique WHERE id_user = 1 AND id_quiz = 2')->fetch_assoc();
    expect($row !== null && (int) $row['note'] === 1, 'Member quiz must persist the score.');

    $db->query("INSERT INTO quiz_statistique (id_user, id_quiz, date, quiz_utilisateur) VALUES (1, 1, 'fixture-date', 0)");
    requestPage('changer-en-favoris.php?id_quiz=1', null, $cookie);
    $favorite = $db->query('SELECT favoris FROM quiz_statistique WHERE id_user = 1 AND id_quiz = 1')->fetch_assoc();
    expect((int) $favorite['favoris'] === 1, 'A member must be able to favorite a completed quiz.');
    requestPage('changer-en-non-favoris.php?id_quiz=1', null, $cookie);
    $favorite = $db->query('SELECT favoris FROM quiz_statistique WHERE id_user = 1 AND id_quiz = 1')->fetch_assoc();
    expect((int) $favorite['favoris'] === 0, 'A member must be able to remove a favorite.');

    requestPage('abonnement.php?a=3', null, $cookie);
    expect($db->query('SELECT idAbonnement FROM quiz_subscription_membre WHERE idUser = 1 AND idUserAbonnement = 3')->num_rows === 1, 'A member must be able to follow another member.');
    requestPage('abonnement.php?a=3&b=1', null, $cookie);
    expect($db->query('SELECT idAbonnement FROM quiz_subscription_membre WHERE idUser = 1 AND idUserAbonnement = 3')->num_rows === 0, 'A member must be able to unfollow another member.');

    requestPage('index.php', null, $cookie);
    list($body, $headers) = requestPage('likedislike_user.php?q=2&l=1', null, $cookie);
    expect(strpos($headers[0], '302') !== false, 'A reaction to a member quiz must complete.');
    $reaction = $db->query('SELECT likeOrDislike, esQuizUser FROM quiz_like_quiz WHERE id_quiz = 2')->fetch_assoc();
    expect($reaction !== null && (int) $reaction['likeOrDislike'] === 1 && (int) $reaction['esQuizUser'] === 1, 'A like on a member quiz must be recorded.');
    requestPage('index.php', null, $cookie);
    requestPage('likedislike_user.php?q=2&l=0', null, $cookie);
    $reaction = $db->query('SELECT likeOrDislike FROM quiz_like_quiz WHERE id_quiz = 2')->fetch_assoc();
    expect($reaction !== null && (int) $reaction['likeOrDislike'] === 0, 'A member quiz reaction must be switchable.');
    requestPage('index.php', null, $cookie);
    requestPage('likedislike_user.php?q=2&l=0', null, $cookie);
    expect($db->query('SELECT id_like FROM quiz_like_quiz WHERE id_quiz = 2')->num_rows === 0, 'A repeated reaction must be removed.');

    $db->query("INSERT INTO quiz_commentaire (id, nom, prenom, mail, commentaire, date_creation) VALUES (1, 'Fixture', 'User', 'fixture@example.invalid', 'Commentaire fixture', '2026-01-01')");
    requestPage('reponse_commentaire.php', array('id_comm' => 1, 'reponse_comm' => 'Reponse fixture'), $cookie);
    expect($db->query("SELECT id_reponse_commentaire FROM quiz_reponse_commentaire WHERE id_commentaire = 1 AND id_user = 1 AND reponse_val = 'Reponse fixture'")->num_rows === 1, 'A member must be able to reply to a comment.');
    requestPage('signal.php?id=3', null, $cookie);
    requestPage('signal.php?id=3', null, $cookie);
    expect($db->query('SELECT id_signalement FROM quiz_signalement WHERE id_user_origine = 1 AND id_user_cible = 3')->num_rows === 1, 'A member report must not be duplicated.');

    $quizForm = array(
        'name_quiz' => 'Quiz cree fixture', 'nb_question' => '1', 'porte' => '1',
        'descrip_quiz' => 'Description initiale', 'difficult_question1' => '1',
        'question1' => 'Question creee', 'nbr_response_question_1' => '2',
        'bonne_reponse_question_1' => '1', 'rep1q1' => 'Oui', 'rep2q1' => 'Non',
        'text_explicatif_reponse_1' => 'Explication creee',
    );
    $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=', true);
    expect($image !== false, 'Fixture image could not be decoded.');
    list($body, $headers) = requestPage('poster_quiz_action.php', $quizForm, $cookie, array('name' => 'fixture.png', 'content' => $image));
    expect(strpos($headers[0], '302') !== false, 'Creating a member quiz must redirect.');
    $createdQuiz = $db->query("SELECT id_quiz, quizComplet FROM quiz WHERE nom_quiz = 'Quiz cree fixture' AND id_user = 1")->fetch_assoc();
    expect($createdQuiz !== null && (int) $createdQuiz['quizComplet'] === 1, 'A new quiz must be created and published.');
    $createdId = (int) $createdQuiz['id_quiz'];
    $createdQuestion = $db->query('SELECT id_question FROM quiz_question WHERE id_quiz = ' . $createdId)->fetch_assoc();
    expect($createdQuestion !== null, 'A new quiz must contain its question.');
    expect($db->query('SELECT id_reponse FROM quiz_reponse WHERE id_question = ' . (int) $createdQuestion['id_question'])->num_rows === 2, 'A new question must contain both answers.');
    list($body, $headers) = requestPage('poster_quiz.php?q=' . $createdId, null, $cookie);
    expect(strpos($body, 'Modifier un quiz') !== false, 'The quiz owner must be able to open its editor.');
    $quizForm['descrip_quiz'] = 'Description modifiee';
    $quizForm['question1'] = 'Question modifiee';
    list($body, $headers) = requestPage('poster_quiz_action.php?q=' . $createdId, $quizForm, $cookie, array('name' => '', 'content' => ''));
    expect(strpos($headers[0], '302') !== false, 'Editing a member quiz must redirect.');
    $editedQuiz = $db->query('SELECT bienvenue FROM quiz WHERE id_quiz = ' . $createdId)->fetch_assoc();
    expect($editedQuiz['bienvenue'] === 'Description modifiee', 'Editing a quiz without replacing the image must preserve the submitted description.');
    $editedQuestion = $db->query('SELECT question_texte FROM quiz_question WHERE id_quiz = ' . $createdId)->fetch_assoc();
    expect($editedQuestion['question_texte'] === 'Question modifiee', 'Editing a quiz must update its question.');
    $db->query("INSERT INTO quiz (id_user, nom_quiz, bienvenue, id_quiz_user, nbrQuestion) VALUES (3, 'Quiz autre membre', '', 1, 1)");
    $otherId = (int) $db->insert_id;
    requestPage('deleteQuiz.php?q=' . $otherId, null, $cookie);
    expect($db->query('SELECT id_quiz FROM quiz WHERE id_quiz = ' . $otherId)->num_rows === 1, 'A member must not delete another member quiz.');
    requestPage('poster_quiz_action.php?q=' . $otherId, $quizForm, $cookie, array('name' => '', 'content' => ''));
    $otherQuiz = $db->query('SELECT nom_quiz FROM quiz WHERE id_quiz = ' . $otherId)->fetch_assoc();
    expect($otherQuiz['nom_quiz'] === 'Quiz autre membre', 'A member must not edit another member quiz.');
    requestPage('deleteQuiz.php?q=' . $createdId, null, $cookie);
    expect($db->query('SELECT id_quiz FROM quiz WHERE id_quiz = ' . $createdId)->num_rows === 0, 'A quiz owner must be able to delete their quiz.');

    list($body, $headers) = requestPage('changer-mdp_prof.php', array('pass' => 'new-fixture-password', 'pass_confirm' => 'new-fixture-password'), $cookie);
    expect(strpos($headers[0], '200') !== false, 'Password change must complete.');
    $changed = $db->query('SELECT pass_md5 FROM quiz_membre WHERE id = 1')->fetch_assoc();
    expect(password_verify('new-fixture-password', $changed['pass_md5']), 'Changed passwords must use a modern hash.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'fixture-user', 'pass' => 'fixture-password'));
    expect(strpos($headers[0], '200') !== false && strpos($body, 'mot de passe incorrect') !== false, 'Old password must stop working after a change.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'fixture-user', 'pass' => 'new-fixture-password'));
    expect(strpos($headers[0], '302') !== false, 'New password must allow login.');

    list($body, $headers) = requestPage('changer.php', array('email' => 'fail@example.invalid'));
    expect(strpos($body, 'Erreur, message non envoyé') !== false, 'A failed reset email must report the error.');
    $failureHash = $db->query('SELECT pass_md5 FROM quiz_membre WHERE id = 3')->fetch_assoc();
    expect($failureHash['pass_md5'] === md5('unchanged-password'), 'A failed reset email must not change the password.');
    list($body, $headers) = requestPage('changer.php', array('email' => 'fixture@example.invalid'));
    expect(strpos($body, 'Nous vous avons envoyé un mail') !== false, 'A successful reset must be reported.');
    $outbox = file_get_contents($directory . '/outbox.html');
    expect(preg_match('~<span style="color: black;text-decoration: underline;">([A-Za-z0-9]+)</span>~', $outbox, $matches) === 1, 'The fixture email must contain the reset password.');
    $resetHash = $db->query('SELECT pass_md5 FROM quiz_membre WHERE id = 1')->fetch_assoc();
    expect(password_verify($matches[1], $resetHash['pass_md5']), 'The reset password must use a modern hash.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'fixture-user', 'pass' => $matches[1]));
    expect(strpos($headers[0], '302') !== false, 'The password sent by the fixture transport must allow login.');

    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'delete-user', 'pass' => 'delete-password'));
    $deleteCookie = '';
    foreach ($headers as $header) {
        if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
            $deleteCookie = explode(';', substr($header, strlen('Set-Cookie: ')), 2)[0];
            break;
        }
    }
    expect($deleteCookie !== '', 'Deletion fixture must have a session.');
    list($body, $headers) = requestPage('supr-compte_prof.php', array('pass_supr' => 'wrong'), $deleteCookie);
    expect($db->query('SELECT id FROM quiz_membre WHERE id = 2')->num_rows === 1, 'Wrong password must not delete an account.');
    list($body, $headers) = requestPage('supr-compte_prof.php', array('pass_supr' => 'delete-password'), $deleteCookie);
    expect($db->query('SELECT id FROM quiz_membre WHERE id = 2')->num_rows === 0, 'Correct modern password must allow account deletion.');

    $registrationSource = file_get_contents($root . '/email_confirmation.php');
    expect(preg_match('/\\$key_password\\s*=\\s*"([^"]+)";/', $registrationSource, $keyMatch) === 1, 'Registration fixture cannot find the legacy encryption key.');
    $registrationKey = 'fixture-registration-key';
    $encryptedKey = openssl_encrypt($registrationKey, 'AES-128-ECB', $keyMatch[1]);
    expect(is_string($encryptedKey), 'Registration fixture key encryption failed.');
    list($body, $headers) = requestPage('email_confirmation.php', array(
        'key' => $encryptedKey,
        'g-recaptcha-response' => 'fixture-only',
        'inscription' => 'Inscription',
        'login' => 'registration-user',
        'pass' => 'registration-password',
        'pass_confirm' => 'registration-password',
        'nom' => 'Fixture',
        'prenom' => 'Registration',
        'email' => 'registration@example.invalid',
        'newletter' => '0',
    ));
    expect(strpos($body, 'Nous vous avons envoyé un mail') !== false, 'Registration must send a confirmation using the fake transport.');
    $pending = $db->query("SELECT cle_pass_md5 FROM quiz_cle WHERE cle = 'fixture-registration-key'")->fetch_assoc();
    expect($pending !== null && password_verify('registration-password', $pending['cle_pass_md5']), 'A new pending registration must store a modern password hash.');
    list($body, $headers) = requestPage('confirmation.php?k=fixture-registration-key&v=o');
    expect(strpos($headers[0], '302') !== false, 'Confirmation must create the account and redirect.');
    $registered = $db->query("SELECT pass_md5 FROM quiz_membre WHERE login = 'registration-user'")->fetch_assoc();
    expect($registered !== null && password_verify('registration-password', $registered['pass_md5']), 'Confirmation must preserve the modern hash.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'registration-user', 'pass' => 'registration-password'));
    expect(strpos($headers[0], '302') !== false, 'A newly registered member must be able to log in.');
    $db->query("INSERT INTO quiz_cle (cle, cle_login, cle_pass_md5, cle_email, cle_nom, cle_prenom, cle_date) VALUES ('legacy-pending-key', 'legacy-pending-user', MD5('pending-password'), 'pending@example.invalid', 'Fixture', 'Pending', '2026-01-01')");
    list($body, $headers) = requestPage('confirmation.php?k=legacy-pending-key&v=o');
    expect(strpos($headers[0], '302') !== false, 'A pending legacy registration must still be confirmed.');
    list($body, $headers) = requestPage('identifier.php', array('connexion' => 'Connexion', 'login' => 'legacy-pending-user', 'pass' => 'pending-password'));
    expect(strpos($headers[0], '302') !== false, 'A previously pending member must still be able to log in.');
    $pendingUpgrade = $db->query("SELECT pass_md5 FROM quiz_membre WHERE login = 'legacy-pending-user'")->fetch_assoc();
    expect(password_verify('pending-password', $pendingUpgrade['pass_md5']), 'A previously pending legacy password must be upgraded on login.');

    $missingSmtp = str_replace("\$smtp_host = 'fixture-smtp';", '', $config);
    expect($missingSmtp !== $config, 'Unable to remove the SMTP host from the fixture configuration.');
    expect(file_put_contents($directory . DIRECTORY_SEPARATOR . 'config.inc.php', $missingSmtp) !== false, 'Unable to prepare missing SMTP configuration.');
    $hashBeforeMissingSmtp = $db->query("SELECT pass_md5 FROM quiz_membre WHERE login = 'fixture-user'")->fetch_assoc()['pass_md5'];
    list($body, $headers) = requestPage('changer.php', array('email' => 'fixture@example.invalid'));
    expect(strpos($headers[0], '500') !== false, 'A missing SMTP configuration must fail explicitly; received ' . $headers[0]);
    $hashAfterMissingSmtp = $db->query("SELECT pass_md5 FROM quiz_membre WHERE login = 'fixture-user'")->fetch_assoc()['pass_md5'];
    expect($hashBeforeMissingSmtp === $hashAfterMissingSmtp, 'A missing SMTP configuration must not change the password.');

    echo "Isolated HTTP and database integration checks passed.\n";
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    foreach ($files as $file) {
        $path = $directory . DIRECTORY_SEPARATOR . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
    $configPath = $directory . DIRECTORY_SEPARATOR . 'config.inc.php';
    if (is_file($configPath)) {
        unlink($configPath);
    }
    foreach (array('FakePHPMailer.php', 'autoload.php') as $file) {
        $path = $directory . '/vendor/' . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_file($directory . '/outbox.html')) {
        unlink($directory . '/outbox.html');
    }
    if (is_dir($directory . '/vendor')) {
        rmdir($directory . '/vendor');
    }
    if (is_dir($directory . '/quiz_perso')) {
        foreach (new DirectoryIterator($directory . '/quiz_perso') as $file) {
            if (!$file->isDot() && $file->isFile()) {
                unlink($file->getPathname());
            }
        }
        rmdir($directory . '/quiz_perso');
    }
    rmdir($directory . DIRECTORY_SEPARATOR . 'commun');
    rmdir($directory);
}
