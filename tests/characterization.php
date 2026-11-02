<?php

error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function assertSameValue($expected, $actual, $message)
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

class FakeResult
{
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function fetchArray()
    {
        return $this->value;
    }

    public function fetchAll()
    {
        return $this->value;
    }

    public function numRows()
    {
        return $this->value;
    }
}

class FakeDatabase
{
    public $queries = array();
    public $achievementCount = 0;
    public $question = array('id_question' => 7);
    public $answers = array(array('id_reponse' => 1), array('id_reponse' => 2));

    public function query($sql)
    {
        $this->queries[] = $sql;
        if (strpos($sql, 'SELECT * FROM quiz_membre_succes') === 0) {
            return new FakeResult($this->achievementCount);
        }
        if (strpos($sql, 'select * from quiz_question') === 0) {
            return new FakeResult($this->question);
        }
        if (strpos($sql, 'select * from quiz_reponse') === 0) {
            return new FakeResult($this->answers);
        }
        return new FakeResult(null);
    }
}

$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'qetg-test-' . bin2hex(random_bytes(12));
$originalDirectory = getcwd();
if ($originalDirectory === false || !mkdir($fixture, 0700)) {
    throw new RuntimeException('Unable to prepare isolated test directory.');
}

try {
    foreach (array('functions.php', 'db.php') as $file) {
        if (!copy($root . DIRECTORY_SEPARATOR . $file, $fixture . DIRECTORY_SEPARATOR . $file)) {
            throw new RuntimeException('Unable to copy test fixture: ' . $file);
        }
    }
    $config = '<?php $servername = "fixture-host"; $username = "fixture-user"; $password = "fixture-password"; $dbname = "fixture-db";';
    if (file_put_contents($fixture . DIRECTORY_SEPARATOR . 'config.inc.php', $config) === false) {
        throw new RuntimeException('Unable to write test configuration.');
    }

    chdir($root . DIRECTORY_SEPARATOR . 'admin');
    $_SERVER['DOCUMENT_ROOT'] = $fixture . DIRECTORY_SEPARATOR . 'not-the-project';
    require $fixture . DIRECTORY_SEPARATOR . 'functions.php';
    require_once $fixture . DIRECTORY_SEPARATOR . 'db.php';

    assertSameValue(true, class_exists('db', false), 'database class loads');
    assertSameValue('fixture-host', $dbhost, 'database host loads');
    assertSameValue('fixture-user', $dbuser, 'database user loads');
    assertSameValue('fixture-password', $dbpass, 'database password loads');
    assertSameValue('fixture-db', $dbname, 'database name loads');

    $adminNavigation = file_get_contents($root . DIRECTORY_SEPARATOR . 'commun' . DIRECTORY_SEPARATOR . 'bandeau-admin.php');
    assertSameValue(true, strpos($adminNavigation, 'src="../commun/icon.png"') !== false, 'admin logo is relative to the application');
    assertSameValue(false, strpos($adminNavigation, 'href="/lien-bandeau/') !== false, 'admin information links do not depend on the web root');

    foreach (array('', '  ', null) as $invalidDatabaseName) {
        try {
            new db('fixture-host', 'fixture-user', 'fixture-password', $invalidDatabaseName);
            throw new RuntimeException('An empty database name must be rejected before connecting.');
        } catch (InvalidArgumentException $exception) {
            assertSameValue(true, strpos($exception->getMessage(), '$dbname') !== false, 'missing database error identifies the setting');
        }
    }

    assertSameValue(0, strlen(Genere_Password(0)), 'zero-length password');
    $password = Genere_Password(24);
    assertSameValue(24, strlen($password), 'password length');
    assertSameValue(1, preg_match('/^[a-z0-9](?:[A-Z0-9][a-z0-9]){11}[A-Z0-9]$/', $password), 'password character pattern');

    assertSameValue('Lundi-02-Novembre-2026', dateToFrench('2026-11-02 12:00:00', 'l-d-F-Y'), 'French date');
    assertSameValue('test &lt;script&gt;', securisation('  test <script>  '), 'input formatting');

    assertSameValue(true, verifierMotDePasse('legacy-password', md5('legacy-password')), 'legacy password verification');
    assertSameValue(false, verifierMotDePasse('incorrect', md5('legacy-password')), 'legacy wrong password');
    $modernHash = hacherMotDePasse('modern-password');
    assertSameValue(true, verifierMotDePasse('modern-password', $modernHash), 'modern password verification');
    assertSameValue(false, verifierMotDePasse('incorrect', $modernHash), 'modern wrong password');
    assertSameValue(false, motDePasseDoitEtreRehache($modernHash), 'new password hash uses current policy');
    assertSameValue(true, motDePasseDoitEtreRehache(md5('legacy-password')), 'legacy hash needs upgrading');
    $bcryptHash = password_hash(str_repeat('b', 72), PASSWORD_BCRYPT);
    assertSameValue(true, verifierMotDePasse(str_repeat('b', 72), $bcryptHash), 'bcrypt password at the length limit is accepted');
    assertSameValue(false, verifierMotDePasse(str_repeat('b', 72) . 'suffix', $bcryptHash), 'bcrypt password suffix is never ignored');
    if (defined('PASSWORD_ARGON2ID')) {
        $longPassword = str_repeat('a', 72) . 'first';
        $longHash = hacherMotDePasse($longPassword);
        assertSameValue(true, verifierMotDePasse($longPassword, $longHash), 'long password verification');
        assertSameValue(false, verifierMotDePasse(str_repeat('a', 72) . 'other', $longHash), 'long passwords are not truncated');
    }

    $database = new FakeDatabase();
    succes(4, 3, $database);
    assertSameValue(2, count($database->queries), 'new achievement performs a lookup and insert');
    assertSameValue(true, strpos($database->queries[1], 'INSERT INTO `quiz_membre_succes`') === 0, 'new achievement is recorded');
    $database = new FakeDatabase();
    $database->achievementCount = 1;
    succes(4, 3, $database);
    assertSameValue(1, count($database->queries), 'existing achievement is not inserted again');

    $_SESSION = array();
    $database = new FakeDatabase();
    assertSameValue('identifier.php', boutonFinirQuiz(12, array('id_user' => 4), $database), 'anonymous publishing requires login');
    assertSameValue(0, count($database->queries), 'anonymous publishing makes no queries');

    $_SESSION = array('id' => 4);
    $database = new FakeDatabase();
    assertSameValue('makeQuiz.php?id_quiz=12', boutonFinirQuiz(12, array('id_user' => 4), $database), 'complete quiz publication');
    assertSameValue(true, strpos($database->queries[2], 'UPDATE quiz SET quizComplet') === 0, 'complete quiz is marked published');

    $database = new FakeDatabase();
    $database->answers = array(array('id_reponse' => 1));
    assertSameValue('poster_reponse.php?action=finirQuiz&idQuiz=12', boutonFinirQuiz(12, array('id_user' => 4), $database), 'incomplete quiz redirects to answers');
    assertSameValue(2, count($database->queries), 'incomplete quiz is not published');

    $database = new FakeDatabase();
    assertSameValue('identifier.php', boutonFinirQuiz(12, array('id_user' => 5), $database), 'another member cannot publish the quiz');
    assertSameValue(2, count($database->queries), 'another member does not publish the quiz');

    echo "Characterization checks passed.\n";
} finally {
    chdir($originalDirectory);
    foreach (array('functions.php', 'db.php', 'config.inc.php') as $file) {
        $path = $fixture . DIRECTORY_SEPARATOR . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
    rmdir($fixture);
}
