<?php
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
    function verifierMotDePasse($password, $storedHash)
        {
            if (preg_match('/\A[0-9a-f]{32}\z/i', $storedHash)) {
                return hash_equals(strtolower($storedHash), md5($password));
            }
            if (password_get_info($storedHash)['algoName'] === 'bcrypt' && strlen($password) > 72) {
                return false;
            }
            return password_verify($password, $storedHash);
        }

    function hacherMotDePasse($password)
        {
            $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            if ($algorithm === PASSWORD_DEFAULT && strlen($password) > 72) {
                throw new RuntimeException('Argon2id est requis pour hacher un mot de passe de plus de 72 octets.');
            }
            $hash = password_hash($password, $algorithm);
            if ($hash === false) {
                throw new RuntimeException('Impossible de hacher le mot de passe.');
            }
            return $hash;
        }

    function motDePasseDoitEtreRehache($hash)
        {
            $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
            return password_needs_rehash($hash, $algorithm);
        }

    function Genere_Password($size)
        {
            $password_gener = '';
            // Initialisation des caractères utilisables
            $characters = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");

            for($i=0;$i<$size;$i++)
            {
                $character = $characters[random_int(0, count($characters) - 1)];
                $password_gener .= ($i%2) ? strtoupper($character) : $character;
            }

            return $password_gener;

        }

    function afficher_head($title, $fichiers_css, $charset)
        {
            echo '<title>'.$title.'</title>
            ';
            echo '<meta charset="'.$charset.'">
            ';
            echo '<link rel="icon" type="image/ico" href="favicon.png">
            ';
    // et maintenant, il va falloir parcourir le tableau passé dans la fonction pour pouvoir générer autant de ligne que d'occurence dans le tableau
    // TODO --->  astuce ---> utiliser la fonction foreach
            foreach ($fichiers_css as $nom_fichier_css)
            {
                echo '<link rel="stylesheet" href="'.$nom_fichier_css.'">
                ';
            }
            echo '
            <script type="text/javascript" src="tarteaucitron/tarteaucitron.js"></script>
    
            <script type="text/javascript">
            tarteaucitron.init({
              "privacyUrl": "", /* Privacy policy url */
    
              "hashtag": "#tarteaucitron", /* Open the panel with this hashtag */
              "cookieName": "tarteaucitron", /* Cookie name */
        
              "orientation": "bottom", /* Banner position (top - bottom) */
           
              "groupServices": false, /* Group services by category */
                               
              "showAlertSmall": true, /* Show the small banner on bottom right */
              "cookieslist": true, /* Show the cookie list */
                               
              "closePopup": false, /* Show a close X on the banner */
    
              "showIcon": true, /* Show cookie icon to manage cookies */
              "iconSrc": "favicon.png", /* Optionnal: URL or base64 encoded image */
              "iconPosition": "BottomLeft", /* BottomRight, BottomLeft, TopRight and TopLeft */
    
              "adblocker": true, /* Show a Warning if an adblocker is detected */
                               
              "DenyAllCta" : false, /* Show the deny all button */
              "AcceptAllCta" : true, /* Show the accept all button when highPrivacy on */
              "highPrivacy": false, /* HIGHLY RECOMMANDED Disable auto consent */
                               
              "handleBrowserDNTRequest": false, /* If Do Not Track == 1, disallow all */
    
              "removeCredit": false, /* Remove credit link */
              "moreInfoLink": true, /* Show more info link */
    
              "useExternalCss": false, /* If false, the tarteaucitron.css file will be loaded */
              "useExternalJs": false, /* If false, the tarteaucitron.js file will be loaded */
    
              // "cookieDomain": "malomouron.fr/quiz/lien-bandeau.php", /* Shared cookie for multisite */
                              
              "readmoreLink": "", /* Change the default readmore link */
    
              "mandatory": false, /* Show a message about mandatory cookies */
            });
            </script>
            <script type="text/javascript">
                tarteaucitron.user.gajsUa = \'UA-159054530-1\';
                tarteaucitron.user.gajsMore = function () { /* add here your optionnal _ga.push() */ };
                (tarteaucitron.job = tarteaucitron.job || []).push(\'gajs\');
                (tarteaucitron.job = tarteaucitron.job || []).push(\'recaptcha\');
            </script>';
        }
    function show_tableau($tab){
        foreach ($tab as $cle => $val) {
                if (is_array($val)) {
                    echo $cle . ' : !Array!  ';
                    show_tableau($val);
                } else {
                    echo $cle . " : " . $val . '<br />';
                }
        }
    }
    function boutonFinirQuiz($idQuiz, $selectQuiz, $db){
            if(isset($_SESSION['id'])) {
                //$selectQuestionEtReponse = $db->query("SELECT * FROM `quiz_question` LEFT OUTER JOIN reponse on quiz_question.id_question = quiz_reponse.id_question WHERE quiz_question.id_quiz = " .$selete_le_quiz_user['id_quiz'])->fetchAll();
                $selectQuestion = $db->query("select * from quiz_question where id_quiz = " .$idQuiz)->fetchArray();
                $selectReponse = $db->query("select * from quiz_reponse WHERE id_question = " . $selectQuestion['id_question'])->fetchAll();
                if ($selectQuiz['id_user'] == $_SESSION['id']) {
                     if (count($selectQuiz) > 0) {
                        if (count($selectQuestion) > 0) {
                            if (count($selectReponse) >= 2) {
                                $updateQuizFini = $db->query("UPDATE quiz SET quizComplet = '1' WHERE quiz.id_quiz = ".$idQuiz);
                                $lien = "makeQuiz.php?id_quiz=". $idQuiz;
                            }else{
                                $lien = "poster_reponse.php?action=finirQuiz&idQuiz=". $idQuiz;
                            }
                        }else{
                            $lien = "poster_question.php?action=finirQuiz&idQuiz=". $idQuiz;
                        }
                    }else{
                        $lien = "poster_quiz.php";
                    }
                 }else{
                     $lien = "identifier.php";
                 }
            }else{
                $lien = "identifier.php";
            }
            return $lien;
        }
    function succes($id,$succes,$db)
    {
            $select_verif_stat_succes = $db->query("SELECT * FROM quiz_membre_succes WHERE id_membre = ".$id." AND id_succes = ".$succes)->numRows();
            if ($select_verif_stat_succes == 0){
                $newSucces = $db->query("INSERT INTO `quiz_membre_succes` (`id_membre_succes`, `id_succes`, `id_membre`, `date_succes`) VALUES (NULL, ".$succes.", ".$id.", '".date("d/m/Y")."')");
            }
    }
    function securisation($val){
        return addslashes(trim(htmlspecialchars($val)));
    }
    function dateToFrench($date, $format)
    {
        $english_days = array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday');
        $french_days = array('Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche');
        $english_months = array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
        $french_months = array('Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre');
        return str_replace($english_months, $french_months, str_replace($english_days, $french_days, date($format, strtotime($date) ) ) );
    }
?>