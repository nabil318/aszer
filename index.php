<?php
session_start();
$message = '';

// Connexion à la base de données SQLite
$dbpath = 'QCM.db'; // Chemin vers votre base de données SQLite
try {
    $pdo = new PDO("sqlite:$dbpath");
    // Définir les options de PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Inscription
    if (isset($_POST['inscription'])) {
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $sexe = $_POST['sexe'];
        $mail = $_POST['mail_universitaire'];
        $pseudo = $_POST['pseudo'];
        $faculte = $_POST['faculte'];
        $annee = $_POST['annee_medecine'];
        $password = password_hash($_POST['mot_de_passe'], PASSWORD_BCRYPT);

        // Liste des adresses e-mail autorisées pour les administrateurs
        $admin_emails = ['nailekkllllbbbd@example.com', 'autre_admissn@example.com'];

        // Vérifier si l'adresse e-mail est dans la liste des administrateurs
        $is_admin = in_array($mail, $admin_emails) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, sexe, mail_universitaire, pseudo, faculte, annee_medecine, mot_de_passe, is_admin)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$nom, $prenom, $sexe, $mail, $pseudo, $faculte, $annee, $password, $is_admin])) {
            if ($is_admin) {
                $message = "Inscription en tant qu'administrateur réussie ! Vous pouvez vous connecter.";
            } else {
                $message = "Inscription réussie ! Vous pouvez vous connecter.";
            }
        } else {
            $message = "Erreur lors de l'inscription.";
        }
    }

    // Connexion
    if (isset($_POST['connexion'])) {
        $pseudo = $_POST['pseudo'];
        $password = $_POST['mot_de_passe'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE pseudo = ?");
        $stmt->execute([$pseudo]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            $_SESSION['pseudo'] = $user['pseudo'];
            $_SESSION['is_admin'] = $user['is_admin']; // Stocker le statut administrateur dans la session
            header('Location: accueil.php');
            exit();
        } else {
            $message = "Utilisateur non reconnu. Veuillez réessayer.";
        }
    }

    // Mot de passe oublié (envoyer le code OTP)
    if (isset($_POST['motdepasseoublie'])) {
        $email = $_POST['email'];

        // Vérifier si l'email existe dans la base de données
        $stmt = $pdo->prepare("SELECT * FROM users WHERE mail_universitaire = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Générer un code OTP
            $otp = random_int(100000, 999999); // Code à 6 chiffres
            $expiration = time() + 300; // Code expirant après 5 minutes

            // Sauvegarder l'OTP dans la base de données
            $stmt = $pdo->prepare("UPDATE users SET otp_code = ?, otp_expiration = ? WHERE mail_universitaire = ?");
            $stmt->execute([$otp, $expiration, $email]);

            // Envoyer un email avec le code OTP
            $subject = "Votre code de réinitialisation";
            $message = "Votre code OTP pour réinitialiser votre mot de passe est : $otp. Ce code expire dans 5 minutes.";
            $headers = "From: no-reply@votre-site.com";
            mail($email, $subject, $message, $headers);

            $message = "Un email contenant le code de réinitialisation a été envoyé.";
        } else {
            $message = "Aucun utilisateur trouvé avec cet email.";
        }
    }

    // Vérifier le code OTP et réinitialiser le mot de passe
    if (isset($_POST['verifierotp'])) {
        $email = $_POST['email'];
        $otp = $_POST['otp'];

        // Vérifier le code OTP
        $stmt = $pdo->prepare("SELECT otp_code, otp_expiration FROM users WHERE mail_universitaire = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $user['otp_code'] == $otp) {
            if (time() < $user['otp_expiration']) {
                // Code OTP valide
                header('Location: reset_password.php?email=' . urlencode($email)); // Rediriger vers la page de réinitialisation
                exit();
            } else {
                $message = "Le code OTP a expiré.";
            }
        } else {
            $message = "Code OTP incorrect.";
        }
    }

    // Réinitialisation du mot de passe
    if (isset($_POST['resetpassword'])) {
        $email = $_POST['email'];
        $new_password = password_hash($_POST['new_password'], PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("UPDATE users SET mot_de_passe = ?, otp_code = NULL, otp_expiration = NULL WHERE mail_universitaire = ?");
        if ($stmt->execute([$new_password, $email])) {
            $message = "Mot de passe réinitialisé avec succès.";
        } else {
            $message = "Erreur lors de la réinitialisation du mot de passe.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AFM - Inscription & Connexion</title>
    <style>
        #connexionForm,
        #inscriptionForm {
            display: none;
        }

        .message {
            color: green;
            margin-top: 10px;
        }

        .message-error {
            color: red;
            margin-top: 10px;
        }

        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 20px;
        }

        form {
            margin: 20px auto;
            padding: 10px;
            width: 300px;
            border: 1px solid #ccc;
        }

        input,
        select {
            width: 90%;
            padding: 10px;
            margin: 10px 0;
        }

        button {
            padding: 10px 20px;
        }

        .tab {
            display: inline-block;
            margin: 0 10px;
            cursor: pointer;
        }

        .active {
            font-weight: bold;
            color: blue;
        }

        #connexionForm,
        #inscriptionForm,
        #motdepasseoublieForm,
        #resetPasswordForm {
            display: none;
        }

        .message {
            color: green;
            margin-top: 10px;
        }
    </style>
    <script>
        function switchForm(formId) {
            document.getElementById("inscriptionForm").style.display = "none";
            document.getElementById("connexionForm").style.display = "none";
            document.getElementById("motdepasseoublieForm").style.display = "none";
            document.getElementById("resetPasswordForm").style.display = "none";
            document.getElementById(formId).style.display = "block";
        }
    </script>
</head>

<body>
    <h1>Bienvenue sur AFM (Annales des Facultés de Médecine)</h1>

    <div>
        <span class="tab active" onclick="switchForm('inscriptionForm')">Inscription</span>
        <span class="tab" onclick="switchForm('connexionForm')">Connexion</span>
        <span class="tab" onclick="switchForm('motdepasseoublieForm')">Mot de passe oublié</span>
    </div>

    <?php if ($message): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <!-- Formulaire d'inscription -->
    <form id="inscriptionForm" action="" method="post" style="display: block;">
        <h2>Inscription</h2>
        <input type="hidden" name="inscription" value="1">
        <input type="text" name="nom" placeholder="Nom" required>
        <input type="text" name="prenom" placeholder="Prénom" required>
        <select name="sexe" required>
            <option value="">Sexe</option>
            <option value="Homme">Homme</option>
            <option value="Femme">Femme</option>
            <option value="Autre">Autre</option>
        </select>
        <input type="email" name="mail_universitaire" placeholder="Email universitaire" required>
        <input type="text" name="pseudo" placeholder="Pseudo" required>
        <select name="faculte" required>
            <option value="">Faculté</option>
            <option value="Paris">Paris</option>
            <option value="Lille">Lille</option>
            <option value="Marseille">Marseille</option>
        </select>
        <select name="annee_medecine" required>
            <option value="">Année de médecine</option>
            <option value="MED-3">MED-3</option>
            <option value="MED-4">MED-4</option>
            <option value="MED-5">MED-5</option>
            <option value="MED-6">MED-6</option>
        </select>
        <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
        <button type="submit">S'inscrire</button>
    </form>

    <!-- Formulaire de connexion -->
    <form id="connexionForm" action="" method="post">
        <h2>Connexion</h2>
        <input type="hidden" name="connexion" value="1">
        <input type="text" name="pseudo" placeholder="Pseudo" required>
        <input type="password" name="mot_de_passe" placeholder="Mot de passe" required>
        <button type="submit">Se connecter</button>
    </form>

    <!-- Formulaire Mot de passe oublié -->
    <form id="motdepasseoublieForm" action="" method="post">
        <h2>Mot de passe oublié</h2>
        <input type="email" name="email" placeholder="Email universitaire" required>
        <button type="submit" name="motdepasseoublie">Envoyer le code OTP</button>
    </form>

    <!-- Formulaire Réinitialisation du mot de passe -->
    <form id="resetPasswordForm" action="" method="post">
        <h2>Réinitialisation du mot de passe</h2>
        <input type="hidden" name="resetpassword" value="1">
        <input type="email" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" required readonly>
        <input type="password" name="new_password" placeholder="Nouveau mot de passe" required>
        <button type="submit">Réinitialiser le mot de passe</button>
    </form>
</body>

</html>