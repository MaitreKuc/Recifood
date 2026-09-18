<?php
/**
 * API: Enregistrer une recette au format Schema.org dans PostgreSQL
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
$recipe = $data['recipe'] ?? null;

if (!$recipe || empty($recipe['name'])) {
    echo json_encode(['success' => false, 'error' => 'Données de recette incomplètes (nom requis)']);
    exit;
}

// Normalisation Schema.org
$recipe['@context'] = $recipe['@context'] ?? 'https://schema.org';
$recipe['@type'] = 'Recipe';

// L'auteur de la recette est toujours l'utilisateur connecté qui effectue l'import/la création,
// jamais l'auteur détecté par l'IA ou extrait de la source (site web, post de réseau social...).
$recipe['author'] = [
    '@type' => 'Person',
    'name' => getCurrentUsername()
];

$name = $recipe['name'];
$description = $recipe['description'] ?? '';
$image_url = is_array($recipe['image'] ?? null) ? ($recipe['image'][0] ?? '') : ($recipe['image'] ?? '');
$prep_time = $recipe['prepTime'] ?? '';
$cook_time = $recipe['cookTime'] ?? '';
$total_time = $recipe['totalTime'] ?? '';
$recipe_yield = is_array($recipe['recipeYield'] ?? null) ? implode(', ', $recipe['recipeYield']) : ($recipe['recipeYield'] ?? '');
$recipe_category = is_array($recipe['recipeCategory'] ?? null) ? implode(', ', $recipe['recipeCategory']) : ($recipe['recipeCategory'] ?? '');
$recipe_cuisine = is_array($recipe['recipeCuisine'] ?? null) ? implode(', ', $recipe['recipeCuisine']) : ($recipe['recipeCuisine'] ?? '');
$source_url = $recipe['url'] ?? ($recipe['mainEntityOfPage'] ?? '');

try {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO recipes (
            user_id, name, description, image_url, prep_time, cook_time, total_time,
            recipe_yield, recipe_category, recipe_cuisine, is_favorite, source_url, schema_data
        ) VALUES (
            :uid, :name, :desc, :img, :prep, :cook, :total,
            :yield, :cat, :cui, FALSE, :source, :schema
        ) RETURNING id
    ");
    $stmt->execute([
        ':uid' => $user_id,
        ':name' => $name,
        ':desc' => $description,
        ':img' => $image_url,
        ':prep' => $prep_time,
        ':cook' => $cook_time,
        ':total' => $total_time,
        ':yield' => $recipe_yield,
        ':cat' => $recipe_category,
        ':cui' => $recipe_cuisine,
        ':source' => $source_url,
        ':schema' => json_encode($recipe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ]);
    $new_id = $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'recipe_id' => (int)$new_id
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
