<?php
/**
 * API: Supprimer une recette
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$user_id = getCurrentUserId();
$data = json_decode(file_get_contents('php://input'), true);
$recipe_id = (int)($data['recipe_id'] ?? 0);

if ($recipe_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID invalide']);
    exit;
}

try {
    $db = getDB();

    if (isAdmin()) {
        // L'administrateur du serveur peut supprimer n'importe quelle recette
        $stmt = $db->prepare("DELETE FROM recipes WHERE id = :id");
        $stmt->execute([':id' => $recipe_id]);
    } else {
        $stmt = $db->prepare("DELETE FROM recipes WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $recipe_id, ':uid' => $user_id]);
    }

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'error' => "Vous n'êtes pas autorisé à supprimer cette recette."]);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
