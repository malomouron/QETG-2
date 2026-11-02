<?php
	session_start();
	include ('functions.php');
	$db = new db($dbhost, $dbuser, $dbpass, $dbname);
	if (isset($_POST['connexion']) && $_POST['connexion'] == 'Connexion') {
		if ((isset($_POST['login']) && !empty($_POST['login'])) && (isset($_POST['pass']) && !empty($_POST['pass']))) {
			$membres = $db->query('SELECT id, login, pass_md5, admin FROM quiz_membre WHERE login = ? OR email = ?', $_POST['login'], $_POST['login'])->fetchAll();
			$membres_valides = array();
			foreach ($membres as $membre) {
				if (verifierMotDePasse($_POST['pass'], $membre['pass_md5'])) {
					$membres_valides[] = $membre;
				}
			}
			if (count($membres_valides) === 1) {
				$select_le_membre = $membres_valides[0];
				if (motDePasseDoitEtreRehache($select_le_membre['pass_md5'])) {
					$db->query('UPDATE quiz_membre SET pass_md5 = ? WHERE id = ? AND pass_md5 = ?', hacherMotDePasse($_POST['pass']), $select_le_membre['id'], $select_le_membre['pass_md5']);
					if ($db->affectedRows() !== 1) {
						throw new RuntimeException('Le mot de passe a changé pendant la connexion. Veuillez réessayer.');
					}
				}
				session_regenerate_id(true);
				$_SESSION['login'] = $select_le_membre['login'];
				$_SESSION['id'] = $select_le_membre["id"];
				$_SESSION['admin'] = $select_le_membre["admin"];
				include ('footer.php');
				header('Location: index.php');
				exit();
			}
			elseif (count($membres_valides) === 0) {
				$erreur = 'Compte non reconnu ou mot de passe incorrect.';
			}
			else {
				$erreur = 'Probème dans la base de données : plusieurs membres ont les mêmes identifiants de connexion.';
			}
		} else {
			$erreur = 'Au moins un des champs est vide.';
		}
	}
?>
<!DOCTYPE html>
<html>
	<head>
<?php
		$css = array("css.css", "commun/bandeau.css");
		afficher_head("Identifier", $css, "UTF-8");
?>
	</head>
	<body id="identifier-body">
<?php	include ('bandeau.php'); ?>
		<div>
			<div id="identifier-menu-gauche">
			</div>	
			<div class="identifier-div" id="identifier-div1">
				<h1 id="identifier-h1">Connexion à l'espace membre :</h1>
				<?php if (isset($erreur)) echo ' <div class="identifier-div" id="identifier-div-erreur">'.$erreur.'</div>';?>
				<form action="identifier.php" method="post" id="identifier-form">
					<label class="identifier-label" for="login">Login/Adress email : </label><input class="identifier-input" type="text" name="login" value="<?php if (isset($_POST['login'])) echo htmlentities(trim($_POST['login'])); ?>" required>
					<label class="identifier-label" for="pass">Mot de passe : </label><input class="identifier-input" type="password" name="pass" required>
					<img class="identifier-show-password" id="identifier-monImage" src="commun/oeil_cache.png"/>
					<br>
					<a id="identifier-lien-mdm-o" href="mot-pass-oublier.php">Mot de pass oublier ?</a>
					<div class="identifier-div" class="identifier-button">
						<input class="identifier-input" type="submit" name="connexion" value="Connexion" id="identifier-id-bt">
					</div>
				</form>
				<a href="inscription.php" id="identifier-inscr">Vous inscrire</a>
			</div>
			<div id="identifier-menu-droit">
			</div>
		</div>
		<script src="node_modules/jquery/dist/jquery.min.js"></script>
		<script type="text/javascript">
			$(document).ready(function(){
				$('.identifier-show-password').click(function() {
					if($(this).prev('input').prop('type') == 'password') {
						//Si c'est un input type password
						$(this).prev('input').prop('type','text');
						$('#identifier-monImage').attr('src','commun/oeil.png');
					} else {
						//Sinon
						$(this).prev('input').prop('type','password');
						$('#identifier-monImage').attr('src','commun/oeil_cache.png');
					}
				});
			});
		</script>
	</body>
</html>