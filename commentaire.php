<?php
// Connexion à la base de données SQLite
$db = new PDO('sqlite:commentaires.db');

// Création de la table si elle n'existe pas
$db->exec("
    CREATE TABLE IF NOT EXISTS commentaires (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        commentaire TEXT NOT NULL,
        date TEXT NOT NULL
    )
");

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commentaire = $_POST['commentaire'];
    $date = date('Y-m-d H:i:s');

    // Insérer le commentaire dans la base de données
    $stmt = $db->prepare("INSERT INTO commentaires (commentaire, date) VALUES (?, ?)");
    $stmt->execute([$commentaire, $date]);

    // Rediriger pour éviter la resoumission du formulaire
    header('Location: commentaire.php');
    exit;
}

// Récupérer tous les commentaires de la base de données
$stmt = $db->query("SELECT * FROM commentaires ORDER BY date DESC");
$commentaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Commentaires</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: rgb(48, 8, 86);
            color: white;
            padding: 10px 20px;
        }

        header a {
            color: white;
            text-decoration: none;
            padding: 10px;
            border: 1px solid white;
            border-radius: 5px;
            margin-left: 10px;
            transition: background-color 0.3s;
        }

        header a:hover {
            background-color: #555;
        }

        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: white;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        h1 {
            text-align: center;
            color: #333;
        }

        form {
            margin-bottom: 20px;
        }

        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            padding: 10px 20px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #555;
        }

        .commentaires {
            margin-top: 20px;
        }

        .commentaire {
            background-color: #f9f9f9;
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid #333;
            border-radius: 4px;
        }

        .commentaire p {
            margin: 0;
        }

        .commentaire strong {
            color: #333;
        }

        #anonyme {
            text-align: center;
        }
    </style>
</head>

<body>
    <header>
        <div>
            <a href='accueil.php'>Accueil</a>
            <a href='data.php'>Ajouter un QCM</a>
            <a href='ancrage.php'>Ancrage</a>
            <a href="exam_univ.php">Examens Universitaires</a>
            <a href="exam_EDN.php">Annales EDN</a>
            <a href="compte_user.php">Compte User</a>
        </div>
        <div>
            <a href="profil.php" style="float: right;">Profil</a>
        </div>
    </header>
    <div class="container">
        <h1>Donnez-nous votre avis</h1>
        <h5 id="anonyme">(Le commentaire est bien-sur anonyme)</h5>
        <form method="POST" action="commentaire.php">
            <textarea name="commentaire" rows="4" required></textarea><br>
            <button type="submit">Valider</button>
        </form>

        <h2>Commentaires</h2>
        <div class="commentaires">
            <?php foreach ($commentaires as $commentaire): ?>
                <div class="commentaire">
                    <p><strong><?= htmlspecialchars($commentaire['date']) ?></strong></p>
                    <p><?= htmlspecialchars($commentaire['commentaire']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>

</html>