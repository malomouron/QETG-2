<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (getenv('QETG_ALLOW_DEMO_SEED') !== '1') {
    fwrite(STDERR, "Refusing to seed: set QETG_ALLOW_DEMO_SEED=1 explicitly.\n");
    exit(2);
}

require_once dirname(__DIR__) . '/bootstrap/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use QETG\Infrastructure\Persistence\MySQL\MySqlConnection;

function seedOne(MySqlConnection $database, string $sql, mixed ...$parameters): ?array
{
    return $database->fetchOne($sql, ...$parameters);
}

function seedImage(MySqlConnection $database, string $source, string $alt): int
{
    $row = seedOne($database, 'SELECT id_image FROM quiz_image WHERE src = ? LIMIT 1', $source);
    if ($row !== null) {
        return (int) $row['id_image'];
    }

    $database->execute('INSERT INTO quiz_image (src, alt) VALUES (?, ?)', $source, $alt);
    return $database->lastInsertId();
}

function seedDifficulty(MySqlConnection $database, string $label): int
{
    $row = seedOne($database, 'SELECT id_difficulte FROM quiz_difficulte WHERE libelle = ? LIMIT 1', $label);
    if ($row !== null) {
        return (int) $row['id_difficulte'];
    }

    $database->execute('INSERT INTO quiz_difficulte (libelle) VALUES (?)', $label);
    return $database->lastInsertId();
}

function seedAchievement(MySqlConnection $database, string $name, int $xp, string $description): int
{
    $row = seedOne($database, 'SELECT id_succes FROM quiz_succes WHERE nom_succes = ? LIMIT 1', $name);
    if ($row !== null) {
        return (int) $row['id_succes'];
    }

    $database->execute(
        'INSERT INTO quiz_succes (nom_succes, xp_succes, descri_succes) VALUES (?, ?, ?)',
        $name,
        $xp,
        $description
    );
    return $database->lastInsertId();
}

function seedQuiz(MySqlConnection $database, int $memberId, int $imageId, int $difficultyId, array $definition): array
{
    $quiz = seedOne(
        $database,
        'SELECT id_quiz FROM quiz WHERE id_user = ? AND id_quiz_user = ? AND nom_quiz = ? LIMIT 1',
        $memberId,
        $definition['member_created'] ? 1 : 0,
        $definition['name']
    );

    if ($quiz === null) {
        $database->execute(
            'INSERT INTO quiz (id_image_quiz, nom_quiz, bienvenue, xp, id_user, id_quiz_user, src, quizComplet, nbrQuestion, portee, vue) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 1, 0)',
            $imageId,
            $definition['name'],
            $definition['description'],
            $definition['xp'],
            $memberId,
            $definition['member_created'] ? 1 : 0,
            $definition['image'],
            count($definition['questions'])
        );
        $quizId = $database->lastInsertId();
    } else {
        $quizId = (int) $quiz['id_quiz'];
    }

    $existingQuestion = seedOne($database, 'SELECT id_question FROM quiz_question WHERE id_quiz = ? LIMIT 1', $quizId);
    if ($existingQuestion === null) {
        foreach ($definition['questions'] as $index => $questionData) {
            $number = $index + 1;
            $database->execute(
                'INSERT INTO quiz_question (id_quiz, id_image_question, texte_bienvenue, id_difficulte, question_texte, numero_question, nbrReponse) VALUES (?, ?, ?, ?, ?, ?, ?)',
                $quizId,
                $imageId,
                $definition['description'],
                $difficultyId,
                $questionData['question'],
                $number,
                count($questionData['answers'])
            );
            $questionId = $database->lastInsertId();

            foreach ($questionData['answers'] as $answerIndex => $answerData) {
                $database->execute(
                    'INSERT INTO quiz_reponse (id_question, id_image_reponse, choix_possible_quiz, reponseOK, texte_reponse_explicatif, num_reponse) VALUES (?, ?, ?, ?, ?, ?)',
                    $questionId,
                    $imageId,
                    $answerData['text'],
                    $answerData['correct'] ? '1' : '0',
                    $answerData['explanation'],
                    $answerIndex + 1
                );
            }
        }
    }

    return array('id' => $quizId, 'definition' => $definition);
}

function seedStatistic(MySqlConnection $database, int $memberId, int $quizId, int $score, bool $favorite, bool $memberQuiz): void
{
    $existing = seedOne(
        $database,
        'SELECT id_user FROM quiz_statistique WHERE id_user = ? AND id_quiz = ? LIMIT 1',
        $memberId,
        $quizId
    );
    if ($existing !== null) {
        return;
    }

    $database->execute(
        'INSERT INTO quiz_statistique (id_user, id_quiz, `date`, note, favoris, quiz_utilisateur) VALUES (?, ?, ?, ?, ?, ?)',
        $memberId,
        $quizId,
        date('Y-m-d'),
        $score,
        $favorite ? 1 : 0,
        $memberQuiz ? 1 : 0
    );
}

function seedAchievementGrant(MySqlConnection $database, int $memberId, int $achievementId): void
{
    $existing = seedOne(
        $database,
        'SELECT id_membre_succes FROM quiz_membre_succes WHERE id_membre = ? AND id_succes = ? LIMIT 1',
        $memberId,
        $achievementId
    );
    if ($existing !== null) {
        return;
    }

    $database->execute(
        'INSERT INTO quiz_membre_succes (id_succes, id_membre, date_succes) VALUES (?, ?, ?)',
        $achievementId,
        $memberId,
        date('d/m/Y')
    );
}

function seedReaction(MySqlConnection $database, int $quizId, string $ip, int $value, bool $memberQuiz): void
{
    $existing = seedOne($database, 'SELECT id_like FROM quiz_like_quiz WHERE id_quiz = ? AND ip = ? LIMIT 1', $quizId, $ip);
    if ($existing !== null) {
        return;
    }

    $database->execute(
        'INSERT INTO quiz_like_quiz (id_quiz, ip, likeOrDislike, esQuizUser) VALUES (?, ?, ?, ?)',
        $quizId,
        $ip,
        $value,
        $memberQuiz ? 1 : 0
    );
}

$database = new MySqlConnection($dbhost, $dbuser, $dbpass, $dbname);
$member = seedOne($database, 'SELECT id FROM quiz_membre WHERE login = ? LIMIT 1', 'malo');
if ($member === null) {
    fwrite(STDERR, "No existing member with login 'malo' was found; no demo data was written.\n");
    exit(3);
}
$memberId = (int) $member['id'];

$database->beginTransaction();
try {
    $difficultySimple = seedDifficulty($database, 'Simple');
    $difficultyNormal = seedDifficulty($database, 'Intermédiaire');
    seedDifficulty($database, 'Très simple');
    seedDifficulty($database, 'Difficile');
    seedDifficulty($database, 'Très difficile');
    $imageHtml = seedImage($database, 'assets/images/community/image-quiz-HTML.png', 'Illustration de quiz HTML');
    $imageCss = seedImage($database, 'assets/images/community/image-quiz-css.jpg', 'Illustration de quiz CSS');
    $imageGeography = seedImage($database, 'assets/images/community/image-quiz-geographie.jpg', 'Illustration de quiz géographie');
    $imagePhp = seedImage($database, 'assets/images/community/image-quiz-php.png', 'Illustration de quiz PHP');
    $imageMath = seedImage($database, 'assets/images/community/image-quiz-math.png', 'Illustration de quiz mathématiques');

    $quizDefinitions = array(
        array(
            'name' => 'DÉMO · Les bases du HTML', 'description' => 'Un quiz de découverte sur la structure des pages web.', 'xp' => 5, 'image' => 'assets/images/community/image-quiz-HTML.png', 'image_id' => $imageHtml, 'difficulty' => $difficultySimple, 'member_created' => false,
            'questions' => array(
                array('question' => 'Quel élément HTML contient généralement le contenu visible de la page ?', 'answers' => array(array('text' => '<body>', 'correct' => true, 'explanation' => 'Le contenu visible est placé dans body.'), array('text' => '<head>', 'correct' => false, 'explanation' => 'head contient surtout les métadonnées.'), array('text' => '<meta>', 'correct' => false, 'explanation' => 'meta décrit des métadonnées.'))),
                array('question' => 'Quel attribut fournit un texte alternatif à une image ?', 'answers' => array(array('text' => 'alt', 'correct' => true, 'explanation' => 'alt donne une alternative textuelle.'), array('text' => 'href', 'correct' => false, 'explanation' => 'href définit une destination de lien.'), array('text' => 'class', 'correct' => false, 'explanation' => 'class associe une classe CSS.'))),
            ),
        ),
        array(
            'name' => 'DÉMO · Premiers pas en CSS', 'description' => 'Sélecteurs, couleurs et règles de style pour le web.', 'xp' => 5, 'image' => 'assets/images/community/image-quiz-css.jpg', 'image_id' => $imageCss, 'difficulty' => $difficultySimple, 'member_created' => false,
            'questions' => array(
                array('question' => 'Quelle propriété CSS change la couleur du texte ?', 'answers' => array(array('text' => 'color', 'correct' => true, 'explanation' => 'color définit la couleur du texte.'), array('text' => 'background', 'correct' => false, 'explanation' => 'background concerne l’arrière-plan.'), array('text' => 'font-style', 'correct' => false, 'explanation' => 'font-style règle le style typographique.'))),
                array('question' => 'Quel sélecteur cible un élément portant id="menu" ?', 'answers' => array(array('text' => '#menu', 'correct' => true, 'explanation' => 'Un identifiant se cible avec #.'), array('text' => '.menu', 'correct' => false, 'explanation' => 'Le point cible une classe.'), array('text' => 'menu()', 'correct' => false, 'explanation' => 'Cette syntaxe n’est pas un sélecteur CSS.'))),
            ),
        ),
        array(
            'name' => 'DÉMO · Géographie générale', 'description' => 'Quelques repères géographiques pour jouer et apprendre.', 'xp' => 5, 'image' => 'assets/images/community/image-quiz-geographie.jpg', 'image_id' => $imageGeography, 'difficulty' => $difficultySimple, 'member_created' => false,
            'questions' => array(
                array('question' => 'Quelle est la capitale du Japon ?', 'answers' => array(array('text' => 'Tokyo', 'correct' => true, 'explanation' => 'Tokyo est la capitale du Japon.'), array('text' => 'Kyoto', 'correct' => false, 'explanation' => 'Kyoto fut une ancienne capitale.'), array('text' => 'Osaka', 'correct' => false, 'explanation' => 'Osaka est une grande ville japonaise.'))),
                array('question' => 'Sur quel continent se trouve le Pérou ?', 'answers' => array(array('text' => 'Amérique du Sud', 'correct' => true, 'explanation' => 'Le Pérou est en Amérique du Sud.'), array('text' => 'Asie', 'correct' => false, 'explanation' => 'Le Pérou ne se trouve pas en Asie.'), array('text' => 'Afrique', 'correct' => false, 'explanation' => 'Le Pérou ne se trouve pas en Afrique.'))),
            ),
        ),
        array(
            'name' => 'DÉMO · Découvrir PHP', 'description' => 'Un quiz créé par Malo autour des bases du langage PHP.', 'xp' => 10, 'image' => 'assets/images/community/image-quiz-php.png', 'image_id' => $imagePhp, 'difficulty' => $difficultyNormal, 'member_created' => true,
            'questions' => array(
                array('question' => 'Quelle balise ouvre un bloc PHP ?', 'answers' => array(array('text' => '<?php', 'correct' => true, 'explanation' => 'Un script PHP commence généralement par <?php.'), array('text' => '<script php>', 'correct' => false, 'explanation' => 'Ce n’est pas une balise PHP.'), array('text' => '<?', 'correct' => false, 'explanation' => 'Les balises courtes ne sont pas la forme recommandée.'))),
                array('question' => 'Quel symbole préfixe une variable en PHP ?', 'answers' => array(array('text' => '$', 'correct' => true, 'explanation' => 'Les variables PHP commencent par $. '), array('text' => '#', 'correct' => false, 'explanation' => '# ne préfixe pas les variables PHP.'), array('text' => '@@', 'correct' => false, 'explanation' => '@@ n’est pas le préfixe des variables PHP.'))),
            ),
        ),
        array(
            'name' => 'DÉMO · Calcul mental', 'description' => 'Quelques calculs courts pour tester ses réflexes.', 'xp' => 10, 'image' => 'assets/images/community/image-quiz-math.png', 'image_id' => $imageMath, 'difficulty' => $difficultyNormal, 'member_created' => true,
            'questions' => array(
                array('question' => 'Combien font 12 × 8 ?', 'answers' => array(array('text' => '96', 'correct' => true, 'explanation' => '12 multiplié par 8 donne 96.'), array('text' => '86', 'correct' => false, 'explanation' => 'La multiplication vaut 96.'), array('text' => '108', 'correct' => false, 'explanation' => 'La multiplication vaut 96.'))),
                array('question' => 'Quel nombre est premier ?', 'answers' => array(array('text' => '17', 'correct' => true, 'explanation' => '17 n’a que 1 et lui-même comme diviseurs positifs.'), array('text' => '21', 'correct' => false, 'explanation' => '21 est divisible par 3 et 7.'), array('text' => '27', 'correct' => false, 'explanation' => '27 est divisible par 3.'))),
            ),
        ),
    );

    $seededQuizzes = array();
    foreach ($quizDefinitions as $definition) {
        $seededQuizzes[] = seedQuiz($database, $memberId, $definition['image_id'], $definition['difficulty'], $definition);
    }

    $stats = array(8, 7, 2, 2, 1);
    foreach ($seededQuizzes as $index => $seededQuiz) {
        $definition = $seededQuiz['definition'];
        seedStatistic($database, $memberId, $seededQuiz['id'], $stats[$index], $index === 0, $definition['member_created']);
        seedReaction($database, $seededQuiz['id'], '192.0.2.' . (10 + $index * 2), 1, $definition['member_created']);
        seedReaction($database, $seededQuiz['id'], '192.0.2.' . (11 + $index * 2), 1, $definition['member_created']);
        seedReaction($database, $seededQuiz['id'], '198.51.100.' . (10 + $index), 0, $definition['member_created']);
    }

    $firstQuizAchievement = seedAchievement($database, 'DÉMO · Premier quiz terminé', 5, 'Succès de démonstration : terminer un premier quiz.');
    $quizCreatorAchievement = seedAchievement($database, 'DÉMO · Créateur de quiz', 15, 'Succès de démonstration : publier son premier quiz dans la communauté.');
    seedAchievementGrant($database, $memberId, $firstQuizAchievement);
    seedAchievementGrant($database, $memberId, $quizCreatorAchievement);

    $database->commit();

    fwrite(STDOUT, "Demo seed completed for existing member 'malo' (id {$memberId}).\n");
    fwrite(STDOUT, "Added or reused 5 demo quizzes, 2 difficulties, 5 images, 2 achievements, quiz history and demo reactions.\n");
    fwrite(STDOUT, "No member credentials, pending registrations, comments, reports, subscriptions or password-reset tokens were created.\n");
} catch (Throwable $exception) {
    $database->rollback();
    fwrite(STDERR, 'Demo seed failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $database->close();
}
