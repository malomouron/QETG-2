<?php

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$css = array(
    $basePath . '/css.css',
    $basePath . '/commun/bandeau.css',
    $basePath . '/resources/assets/css/home.css',
    $basePath . '/resources/assets/css/information.css',
);
?>
<!doctype html>
<html lang="fr">
<head>
<?php afficher_head($page === 'about' ? 'À propos - Quiz en tout genre' : 'Aide - Quiz en tout genre', $css, 'UTF-8'); ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="<?= $page === 'about' ? 'Découvrez Quiz en tout genre, nos contacts et nos informations de confidentialité.' : 'Trouvez des réponses pour utiliser Quiz en tout genre.' ?>">
</head>
<body class="home-page information-page">
<div class="home-shell">
    <header class="bandeau home-header">
        <a class="home-brand" href="<?= $escape($basePath) ?>/accueil"><img src="<?= $escape($basePath) ?>/assets/images/community/icon.png" alt="" class="home-brand-icon"><span>Quiz en tout genre</span></a>
        <nav class="home-nav" aria-label="Navigation principale">
            <a href="<?= $escape($basePath) ?>/accueil">Accueil</a>
            <a href="<?= $escape($basePath) ?>/quiz_utilisateur.php">Quiz membres</a>
            <a href="<?= $escape($basePath) ?>/poster_quiz.php">Créer un quiz</a>
            <a href="<?= $escape($basePath) ?>/aide">Aide</a>
        </nav>
        <div class="home-account">
            <?php if (isset($_SESSION['login'])): ?>
                <a class="home-account-link" href="<?= $escape($basePath) ?>/compte/profil"><?= $escape((string) $_SESSION['login']) ?></a>
            <?php else: ?>
                <a class="home-account-link" href="<?= $escape($basePath) ?>/connexion">Connexion</a>
                <a class="home-button home-button--small" href="<?= $escape($basePath) ?>/inscription">Créer un compte</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="information-main">
        <?php if ($page === 'about'): ?>
            <section class="information-hero">
                <p class="home-eyebrow">Quiz en tout genre</p>
                <h1>La curiosité est un bon début.</h1>
                <p>Un site indépendant pour découvrir des quiz, apprendre, tester ses connaissances et partager ses propres créations.</p>
                <a class="home-button" href="<?= $escape($basePath) ?>/accueil#catalogue">Découvrir les quiz <span aria-hidden="true">→</span></a>
            </section>

            <nav class="information-toc" aria-label="Dans cette page">
                <a href="#apropos">À propos</a><a href="#2">Contact</a><a href="#3">Règles d’utilisation</a><a href="#4">Cookies</a><a href="#5">Confidentialité</a>
            </nav>

            <section class="information-card" id="apropos">
                <p class="home-eyebrow">Le projet</p>
                <h2>À propos de Quiz en tout genre</h2>
                <p>Quiz en tout genre (QETG) est un projet indépendant réalisé pour apprendre et pratiquer le développement web. Le site rassemble des quiz de culture générale, de divertissement et d’apprentissage, ainsi que des quiz proposés par les membres.</p>
                <p>Le site évolue progressivement. Certaines fonctions ou contenus peuvent encore être incomplets ; les retours constructifs sont les bienvenus.</p>
            </section>

            <section class="information-card" id="2">
                <p class="home-eyebrow">Écrivez-nous</p>
                <h2>Contact</h2>
                <p>Pour une question, un problème de compte ou un signalement, vous pouvez nous contacter par e-mail. N’envoyez jamais votre mot de passe.</p>
                <a class="contact-link" href="mailto:admin@malomouron.fr"><span aria-hidden="true">✉</span> admin@malomouron.fr</a>
                <div class="contact-qr"><img src="<?= $escape($basePath) ?>/assets/images/community/Qr_code.png" alt="QR code de partage du site"><p>Vous pouvez aussi partager le site avec ce QR code.</p></div>
            </section>

            <section class="information-card information-card--warning" id="3">
                <p class="home-eyebrow">Bien vivre ensemble</p>
                <h2>Règles d’utilisation</h2>
                <p>Les contenus publics doivent rester respectueux et conformes aux droits d’autrui. Les insultes, le harcèlement, les menaces, les contenus haineux ou sexuels non sollicités, le spam et les contenus illégaux ne sont pas acceptés. Des mesures de modération ou de restriction de compte peuvent être prises en cas d’abus.</p>
                <p>Si vous rencontrez un contenu problématique, utilisez les outils de signalement disponibles ou contactez-nous.</p>
            </section>

            <section class="information-card" id="4">
                <p class="home-eyebrow">Vos préférences</p>
                <h2>Cookies et services tiers</h2>
                <p>Le site utilise des cookies nécessaires à son fonctionnement et peut charger des services tiers, notamment pour la mesure d’audience ou la protection anti-robot. Ces services peuvent traiter des informations techniques comme l’adresse IP ou des identifiants de navigateur selon leurs propres règles.</p>
                <p>Vous pouvez consulter ou modifier vos choix via le panneau de gestion des cookies affiché sur le site. Les services tiers peuvent ne pas fonctionner si vous les refusez.</p>
                <button class="information-link-button" type="button" onclick="if (window.tarteaucitron) { window.tarteaucitron.userInterface.openPanel(); }">Gérer mes préférences cookies</button>
            </section>

            <section class="information-card" id="5">
                <p class="home-eyebrow">Données personnelles</p>
                <h2>Confidentialité</h2>
                <p>La création d’un compte utilise un identifiant, une adresse e-mail, un nom et un prénom. Le mot de passe est conservé sous forme de hachage ; il n’est pas stocké en clair. Les informations de profil, les quiz créés, les résultats et les interactions sont utilisés pour fournir les fonctions du site.</p>
                <p>Les quiz, commentaires et autres contributions publiés peuvent être visibles par les visiteurs. Ne publiez pas d’informations sensibles dans ces contenus. L’adresse IP peut également être traitée pour certaines fonctions techniques, de sécurité ou de limitation des abus.</p>
                <p>Le site ne vend pas les informations de compte. Les prestataires techniques (hébergement, e-mail et services tiers choisis dans le panneau cookies) peuvent traiter les données nécessaires à leur service. Pour une question ou une demande relative à vos données, écrivez à <a href="mailto:admin@malomouron.fr">admin@malomouron.fr</a>.</p>
                <p class="information-note">Cette page décrit les traitements actuellement repérés dans l’application ; elle ne remplace pas un avis juridique. Elle doit être vérifiée et complétée par l’exploitant selon les services réellement configurés et les obligations applicables.</p>
            </section>
        <?php else: ?>
            <section class="information-hero information-hero--help">
                <p class="home-eyebrow">Centre d’aide</p>
                <h1>Comment pouvons-nous vous aider ?</h1>
                <p>Retrouvez les réponses aux questions courantes sur les quiz, votre compte et les créations de la communauté.</p>
                <label class="help-search" for="help-search-input"><span aria-hidden="true">⌕</span><input id="help-search-input" type="search" placeholder="Rechercher dans l’aide…" autocomplete="off"></label>
            </section>
            <section class="help-content" aria-label="Questions fréquentes">
                <p class="home-eyebrow">Questions fréquentes</p>
                <div class="help-faq-list" id="help-faq-list">
                    <details class="help-faq" data-search="compte inscription créer compte email">
                        <summary>Comment créer un compte ?</summary>
                        <p>Choisissez « Créer un compte », remplissez le formulaire puis ouvrez le lien de confirmation reçu par e-mail. Vérifiez aussi vos courriers indésirables si le message tarde.</p>
                    </details>
                    <details class="help-faq" data-search="connexion login identifiant email mot de passe">
                        <summary>Je n’arrive pas à me connecter</summary>
                        <p>Vous pouvez vous connecter avec votre nom d’utilisateur ou l’adresse e-mail du compte. Vérifiez la saisie du mot de passe. Si besoin, demandez un lien de réinitialisation depuis la page de connexion.</p>
                        <a href="<?= $escape($basePath) ?>/mot-de-passe-oublie">Réinitialiser mon mot de passe →</a>
                    </details>
                    <details class="help-faq" data-search="mot de passe oublié réinitialiser changer sécurité">
                        <summary>Comment changer ou réinitialiser mon mot de passe ?</summary>
                        <p>Une fois connecté, ouvrez votre profil puis « Modifier le mot de passe ». Pour un mot de passe oublié, demandez un lien temporaire depuis la page de connexion.</p>
                        <a href="<?= $escape($basePath) ?>/connexion">Aller à la connexion →</a>
                    </details>
                    <details class="help-faq" data-search="jouer quiz question réponse progression résultat score">
                        <summary>Comment jouer à un quiz ?</summary>
                        <p>Choisissez un quiz dans l’accueil ou dans le catalogue des quiz membres, ouvrez sa présentation puis démarrez la partie. Les résultats des membres connectés peuvent être retrouvés dans le profil.</p>
                        <a href="<?= $escape($basePath) ?>/accueil#catalogue">Choisir un quiz →</a>
                    </details>
                    <details class="help-faq" data-search="créer publier quiz question brouillon privé public modifier">
                        <summary>Comment créer et publier un quiz ?</summary>
                        <p>Connectez-vous, choisissez « Créer un quiz » et ajoutez ses questions et réponses. Vous pouvez enregistrer un brouillon puis reprendre sa préparation depuis « Mes quiz » dans votre profil.</p>
                        <a href="<?= $escape($basePath) ?>/poster_quiz.php">Créer un quiz →</a>
                    </details>
                    <details class="help-faq" data-search="favori favoris historique profil xp niveau succès">
                        <summary>Où retrouver mes favoris et ma progression ?</summary>
                        <p>Ouvrez votre profil. Vous y trouverez l’historique des quiz, les favoris, l’expérience, le niveau et les succès débloqués.</p>
                        <a href="<?= $escape($basePath) ?>/compte/profil">Ouvrir mon profil →</a>
                    </details>
                    <details class="help-faq" data-search="avatar image photo profil téléverser fichier">
                        <summary>Comment changer mon avatar ?</summary>
                        <p>Dans votre profil, choisissez un avatar proposé ou téléversez une image JPEG, PNG, GIF ou WebP de 2 Mo maximum.</p>
                        <a href="<?= $escape($basePath) ?>/compte/profil#avatar">Gérer mon avatar →</a>
                    </details>
                    <details class="help-faq" data-search="compte supprimer fermeture effacer données">
                        <summary>Comment supprimer mon compte ?</summary>
                        <p>La section « Supprimer mon compte » de votre profil demande votre mot de passe avant de confirmer l’action. La suppression est définitive ; les contenus officiels qui doivent être conservés peuvent empêcher la suppression automatique du compte.</p>
                        <a href="<?= $escape($basePath) ?>/compte/profil">Ouvrir mon profil →</a>
                    </details>
                    <details class="help-faq" data-search="signaler commentaire utilisateur problème contact email">
                        <summary>Comment signaler un problème ou contacter l’équipe ?</summary>
                        <p>Utilisez le lien de signalement sur un profil lorsque disponible. Pour toute autre demande, écrivez à admin@malomouron.fr sans inclure votre mot de passe.</p>
                        <a href="mailto:admin@malomouron.fr">Nous contacter →</a>
                    </details>
                </div>
                <p class="help-no-results" id="help-no-results" hidden>Aucun résultat. Essayez d’autres mots ou <a href="<?= $escape($basePath) ?>/a-propos#2">contactez-nous</a>.</p>
            </section>
            <section class="help-contact-card"><div><p class="home-eyebrow">Encore besoin d’aide ?</p><h2>Nous sommes à votre écoute.</h2><p>Écrivez-nous et décrivez le problème rencontré.</p></div><a class="home-button" href="mailto:admin@malomouron.fr">Contacter l’équipe →</a></section>
            <script>
                (function () {
                    const input = document.getElementById('help-search-input');
                    const items = Array.from(document.querySelectorAll('.help-faq'));
                    const empty = document.getElementById('help-no-results');
                    input.addEventListener('input', function () {
                        const query = input.value.trim().toLocaleLowerCase('fr');
                        let visibleCount = 0;
                        items.forEach(function (item) {
                            const searchable = (item.dataset.search + ' ' + item.textContent).toLocaleLowerCase('fr');
                            const visible = query === '' || searchable.includes(query);
                            item.hidden = !visible;
                            if (visible) visibleCount++;
                        });
                        empty.hidden = visibleCount > 0;
                    });
                }());
            </script>
        <?php endif; ?>
    </main>

    <footer class="home-footer"><p>Quiz en tout genre · Une communauté de curieux.</p><nav aria-label="Liens d’information"><a href="<?= $escape($basePath) ?>/a-propos">À propos</a><a href="<?= $escape($basePath) ?>/aide">Aide</a><a href="<?= $escape($basePath) ?>/a-propos#5">Confidentialité</a></nav></footer>
</div>
</body>
</html>
