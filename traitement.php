<?php
// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

// Récupérer les paramètres depuis l'URL ou formulaire (GET ou POST)
$specialite = isset($_GET['specialite']) ? $_GET['specialite'] : null;
$item = isset($_GET['item']) ? $_GET['item'] : null;
$ville = isset($_GET['ville']) ? $_GET['ville'] : null;
$type_qcm = isset($_GET['type_qcm']) ? $_GET['type_qcm'] : null;

// Vérification si le type_qcm est défini pour éviter la recherche sans type
if ($type_qcm) {
    // Construction de la requête SQL de base
    $sql = "SELECT * FROM QI WHERE 1";  // "WHERE 1" permet de ne pas avoir d'erreur si aucun filtre n'est ajouté.
    $params = [];

    // Ajouter les filtres à la requête si nécessaire
    if ($specialite) {
        $sql .= " AND specialite = :specialite";
        $params[':specialite'] = $specialite;
    }

    if ($item) {
        $sql .= " AND item = :item";
        $params[':item'] = $item;
    }

    if ($ville) {
        $sql .= " AND ville = :ville";
        $params[':ville'] = $ville;
    }

    // Ajouter le filtre type_qcm
    $sql .= " AND type_qcm = :type_qcm";
    $params[':type_qcm'] = $type_qcm;

    // Préparer et exécuter la requête
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    // Récupérer les résultats
    $resultats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Affichage des résultats
    if (count($resultats) > 0) {
        echo "<h2>Résultats pour : ";
        echo htmlspecialchars($specialite ? $specialite : 'Aucune spécialité') . " - ";
        echo htmlspecialchars($item ? "Item " . $item : 'Aucun item') . " - ";
        echo htmlspecialchars($ville ? $ville : 'Aucune ville') . " - ";
        echo htmlspecialchars($type_qcm ? $type_qcm : 'Aucun type QCM');
        echo "</h2>";
        echo "<ul>";

        // Affichage des résultats sous forme de liste
        foreach ($resultats as $row) {
            echo "<li><strong>Rang:</strong> " . htmlspecialchars($row['rang']) . "<br>";
            echo "<strong>Énoncé:</strong> " . htmlspecialchars($row['enonce']) . "<br>";
            echo "<strong>Ville:</strong> " . htmlspecialchars($row['ville']) . "<br>";
            echo "<strong>Type QCM:</strong> " . htmlspecialchars($row['type_qcm']) . "<br>";
            echo "<strong>Réponses:</strong> ";
            echo htmlspecialchars($row['prop1']) . ", ";
            echo htmlspecialchars($row['prop2']) . ", ";
            echo htmlspecialchars($row['prop3']) . ", ";
            echo htmlspecialchars($row['prop4']) . ", ";
            echo htmlspecialchars($row['prop5']);
            echo "</li><hr>";
        }

        echo "</ul>";
    } else {
        // Si aucun résultat n'est trouvé
        echo "<p>Aucun résultat trouvé pour les critères sélectionnés.</p>";
    }
} else {
    // Si type_qcm est manquant ou invalide, afficher un message d'erreur
    echo "<p>Paramètre type_qcm manquant ou invalide.</p>";
}
