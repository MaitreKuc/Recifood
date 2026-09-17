<?php
/**
 * API: Télécharger / Exporter une recette au format Schema.org JSON-LD
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Export public : la recette est visible par tous, l'export l'est donc aussi.
$recipe_id = (int)($_GET['id'] ?? 0);

if ($recipe_id <= 0) {
    die("ID de recette invalide.");
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT name, schema_data FROM recipes WHERE id = :id");
    $stmt->execute([':id' => $recipe_id]);
    $recipe = $stmt->fetch();

    if (!$recipe) {
        die("Recette introuvable.");
    }

    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($recipe['name'])) . '-schema-recipe.json';

    header('Content-Type: application/ld+json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode(json_decode($recipe['schema_data']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}
