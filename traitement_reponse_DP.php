<?php
session_start();

// Connexion à la base de données SQLite
try {
    $db = new PDO('sqlite:QCM.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . htmlspecialchars($e->getMessage()));
}

// Récupérer les réponses de l'utilisateur
$user_id = $_SESSION['user_id'] ?? null;
$qcm_id = $_POST['qcm_id'] ?? null;
$reponses = $_POST['reponse'] ?? [];

if (!$user_id || !$qcm_id || empty($reponses)) {
    die("Erreur : Données manquantes.");
}

// Récupérer les réponses correctes et les types de questions
$questions = [];
for ($i = 1; $i <= 8; $i++) {
    $sql = "SELECT type, reponse_correcte FROM DP_" . $i . " WHERE id_DP = :qcm_id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':qcm_id', $qcm_id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $questions[$i] = $result;
    }
}

// Calculer la note
$globalScore = 0;
$numberOfQuestions = count($questions);

foreach ($reponses as $questionId => $userAnswers) {
    $type = $questions[$questionId]['type'] ?? null;
    $correctAnswers = explode(',', $questions[$questionId]['reponse_correcte']);
    $partialScore = 0;

    if ($type === 'QRU') {
        if ($userAnswers === $correctAnswers[0]) {
            $partialScore = 1;
        }
    } elseif ($type === 'QRM' || $type === 'QRP') {
        $correctCount = 0;
        $incorrectCount = 0;
        foreach ($userAnswers as $answer) {
            if (in_array($answer, $correctAnswers)) {
                $correctCount++;
            } else {
                $incorrectCount++;
            }
        }
        if ($incorrectCount === 0 && $correctCount === count($correctAnswers)) {
            $partialScore = 1;
        } elseif ($incorrectCount === 1) {
            $partialScore = 0.5;
        } elseif ($incorrectCount === 2) {
            $partialScore = 0.2;
        }
    } elseif ($type === 'QM') {
        if (in_array(strtolower(trim($userAnswers)), array_map('strtolower', array_map('trim', $correctAnswers)))) {
            $partialScore = 1;
        }
    } elseif ($type === 'QP') {
        $correctCount = 0;
        foreach ($userAnswers as $answer) {
            if (in_array($answer, $correctAnswers)) {
                $correctCount++;
            }
        }
        $partialScore = $correctCount / count($correctAnswers);
    }

    $globalScore += $partialScore;
}

// Calculer la note finale sur 20
$finalScore = ($globalScore / $numberOfQuestions) * 20;

// Enregistrer la note dans la base de données
$date = date('Y-m-d H:i:s');
$sql = "INSERT INTO user_results (user_id, qcm_id, date, note) VALUES (:user_id, :qcm_id, :date, :note)";
$stmt = $db->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':qcm_id', $qcm_id, PDO::PARAM_INT);
$stmt->bindParam(':date', $date);
$stmt->bindParam(':note', $finalScore, PDO::PARAM_STR);
$stmt->execute();

// Rediriger vers la page de résultats ou afficher un message de succès
header("Location: resultats.php");
exit();
