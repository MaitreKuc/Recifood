<?php
/**
 * Rattrapage : télécharge en local les miniatures des recettes encore hébergées sur un CDN distant.
 *
 * Usage (depuis l'hôte) :
 *   docker compose exec frontend php /var/www/html/tools/localize-recipe-images.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Ce script ne peut être exécuté qu'en ligne de commande.\n");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/image_store.php';

$db = getDB();
$rows = $db->query("SELECT id, image_url, schema_data FROM recipes ORDER BY id")->fetchAll();

$update = $db->prepare("UPDATE recipes SET image_url = :img, schema_data = :schema WHERE id = :id");

$converted = 0;
$failed = 0;

foreach ($rows as $row) {
    $schema = json_decode((string)$row['schema_data'], true) ?: [];
    $original_image_url = (string)$row['image_url'];
    $original_schema_images = $schema['image'] ?? null;

    $schema = localizeRecipeImages($schema);
    $schema_images = $schema['image'] ?? [];

    $image_url = $original_image_url;
    if (!isLocalRecipeImage($image_url)) {
        // La vignette suit la première image du schéma si elle vient d'être localisée.
        $first = is_array($schema_images) ? ($schema_images[0] ?? '') : (string)$schema_images;
        $image_url = isLocalRecipeImage((string)$first) ? (string)$first : storeRecipeImageLocally($image_url);
    }

    // Une URL distante encore présente signifie que le CDN a refusé le téléchargement (lien expiré).
    if (!isLocalRecipeImage($image_url) && $original_image_url !== '') {
        $failed++;
        echo "  ! Recette #{$row['id']} : téléchargement impossible, URL distante conservée.\n";
    }

    $changed = $image_url !== $original_image_url || ($schema['image'] ?? null) !== $original_schema_images;
    if (!$changed) {
        continue;
    }

    $update->execute([
        ':img' => $image_url,
        ':schema' => json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ':id' => $row['id'],
    ]);

    $converted++;
    echo "  > Recette #{$row['id']} mise à jour : {$image_url}\n";
}

echo "\n{$converted} recette(s) mise(s) à jour, {$failed} image(s) non téléchargeable(s).\n";
