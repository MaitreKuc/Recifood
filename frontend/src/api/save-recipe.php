<?php
/**
 * API: Enregistrer une recette au format Schema.org dans PostgreSQL
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/source_url.php';
require_once __DIR__ . '/../includes/image_store.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$user_id = getCurrentUserId();
$data = json_decode(file_get_contents('php://input'), true);
$recipes = $data['recipes'] ?? null;
if (!is_array($recipes)) {
    $recipe = $data['recipe'] ?? null;
    $recipes = $recipe ? [$recipe] : [];
}

if (!$recipes) {
    echo json_encode(['success' => false, 'error' => 'Données de recette incomplètes (nom requis)']);
    exit;
}

foreach ($recipes as $recipe) {
    if (!is_array($recipe) || empty($recipe['name'])) {
        echo json_encode(['success' => false, 'error' => 'Chaque recette doit contenir un nom.']);
        exit;
    }
}

try {
    $db = getDB();

    $source_urls = [];
    foreach ($recipes as $recipe) {
        $source_url = normalizeSourceUrl($recipe['url'] ?? ($recipe['mainEntityOfPage'] ?? ''));
        if ($source_url !== '') {
            $source_urls[$source_url] = true;
        }
    }

    $rejectDuplicate = static function (): void {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'error' => 'Cette URL a déjà été importée. Les doublons de source ne sont pas autorisés.'
        ]);
        exit;
    };

    // Pré-contrôle hors transaction : évite de télécharger les images d'un import déjà connu.
    foreach (array_keys($source_urls) as $source_url) {
        if (findRecipeIdBySourceUrl($db, $source_url)) {
            $rejectDuplicate();
        }
    }

    // Les miniatures sont copiées en local avant la transaction : les URL des CDN sociaux
    // expirent au bout de quelques jours et casseraient l'affichage. Le téléchargement de
    // plusieurs images peut dépasser le max_execution_time par défaut (30 s).
    @set_time_limit(180);
    foreach ($recipes as $index => $recipe) {
        $recipes[$index] = localizeRecipeImages(
            $recipe,
            normalizeSourceUrl($recipe['url'] ?? ($recipe['mainEntityOfPage'] ?? ''))
        );
    }

    $db->beginTransaction();

    foreach (array_keys($source_urls) as $source_url) {
        // Verrou transactionnel PostgreSQL : deux imports simultanés de la même URL ne peuvent pas passer ensemble.
        $lock = $db->prepare("SELECT pg_advisory_xact_lock(hashtext(:source))");
        $lock->execute([':source' => $source_url]);

        if (findRecipeIdBySourceUrl($db, $source_url)) {
            $db->rollBack();
            $rejectDuplicate();
        }
    }

    $stmt = $db->prepare("
        INSERT INTO recipes (
            user_id, name, description, image_url, prep_time, cook_time, total_time,
            recipe_yield, recipe_category, recipe_cuisine, is_favorite, source_url, schema_data
        ) VALUES (
            :uid, :name, :desc, :img, :prep, :cook, :total,
            :yield, :cat, :cui, FALSE, :source, :schema
        ) RETURNING id
    ");

    $recipe_ids = [];
    foreach ($recipes as $recipe) {
        $recipe['@context'] = $recipe['@context'] ?? 'https://schema.org';
        $recipe['@type'] = 'Recipe';
        $recipe['author'] = [
            '@type' => 'Person',
            'name' => getCurrentUsername()
        ];

        $source_url = normalizeSourceUrl($recipe['url'] ?? ($recipe['mainEntityOfPage'] ?? ''));
        if ($source_url !== '') {
            $recipe['url'] = $source_url;
        }

        $stmt->execute([
            ':uid' => $user_id,
            ':name' => $recipe['name'],
            ':desc' => $recipe['description'] ?? '',
            ':img' => is_array($recipe['image'] ?? null) ? ($recipe['image'][0] ?? '') : ($recipe['image'] ?? ''),
            ':prep' => $recipe['prepTime'] ?? '',
            ':cook' => $recipe['cookTime'] ?? '',
            ':total' => $recipe['totalTime'] ?? '',
            ':yield' => is_array($recipe['recipeYield'] ?? null) ? implode(', ', $recipe['recipeYield']) : ($recipe['recipeYield'] ?? ''),
            ':cat' => is_array($recipe['recipeCategory'] ?? null) ? implode(', ', $recipe['recipeCategory']) : ($recipe['recipeCategory'] ?? ''),
            ':cui' => is_array($recipe['recipeCuisine'] ?? null) ? implode(', ', $recipe['recipeCuisine']) : ($recipe['recipeCuisine'] ?? ''),
            ':source' => $source_url,
            ':schema' => json_encode($recipe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ]);
        $recipe_ids[] = (int)$stmt->fetchColumn();
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'recipe_id' => $recipe_ids[0],
        'recipe_ids' => $recipe_ids,
        'recipe_count' => count($recipe_ids)
    ]);
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
