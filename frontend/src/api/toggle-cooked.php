<?php
/**
 * API: Basculer le statut "déjà cuisiné" d'une recette
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

    // Vérifier que la recette existe (recette publique, accessible à tous)
    $check = $db->prepare("SELECT id FROM recipes WHERE id = :id");
    $check->execute([':id' => $recipe_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Recette introuvable']);
        exit;
    }

    // Le statut "déjà cuisiné" est personnel : chaque utilisateur peut le marquer sur n'importe quelle recette publique
    $stmt = $db->prepare("
        INSERT INTO user_recipe_status (user_id, recipe_id, already_cooked)
        VALUES (:uid, :id, TRUE)
        ON CONFLICT (user_id, recipe_id)
        DO UPDATE SET already_cooked = NOT user_recipe_status.already_cooked, updated_at = CURRENT_TIMESTAMP
        RETURNING already_cooked
    ");
    $stmt->execute([':id' => $recipe_id, ':uid' => $user_id]);
    $new_status = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'already_cooked' => (bool)$new_status
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
