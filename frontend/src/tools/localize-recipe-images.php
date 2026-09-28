<?php
/**
 * Rattrapage : télécharge en local les miniatures des recettes encore hébergées sur un CDN distant.
 *
 * Si le lien direct a déjà expiré (cas fréquent des CDN Instagram/Facebook), le script repart de
 * l'URL d'origine de la recette pour récupérer une adresse d'image fraîchement signée.
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
$rows = $db->query("SELECT id, name, image_url, source_url, schema_data FROM recipes ORDER BY id")->fetchAll();

$update = $db->prepare("UPDATE recipes SET image_url = :img, schema_data = :schema WHERE id = :id");

$converted = 0;
$failed = 0;

foreach ($rows as $row) {
    $schema = json_decode((string)$row['schema_data'], true) ?: [];
    $original_image_url = (string)$row['image_url'];
    $original_schema_images = $schema['image'] ?? null;

    // L'URL d'origine sert de plan B : quand le lien direct de l'image a expiré (CDN social),
    // la page du post expose toujours une adresse fraîchement signée.
    $source_url = (string)($row['source_url'] ?: ($schema['url'] ?? ''));

    $schema = localizeRecipeImages($schema, $source_url);
    $schema_images = $schema['image'] ?? [];

    $image_url = $original_image_url;
    if (!isLocalRecipeImage($image_url)) {
        // La vignette suit la première image du schéma si elle vient d'être localisée.
        $first = is_array($schema_images) ? ($schema_images[0] ?? '') : (string)$schema_images;
        $image_url = isLocalRecipeImage((string)$first)
            ? (string)$first
            : storeRecipeImageWithFallback($image_url, $source_url);
    }

    // Une URL distante encore présente signifie que ni le lien direct ni la page d'origine n'ont abouti.
    if (!isLocalRecipeImage($image_url) && $original_image_url !== '') {
        $failed++;
        echo "  ! Recette #{$row['id']} « {$row['name']} » : image irrécupérable"
            . ($source_url !== '' ? " (page d'origine : {$source_url})" : " (aucune URL d'origine enregistrée)")
            . ", URL distante conservée.\n";
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

echo "\n{$converted} recette(s) mise(s) à jour, {$failed} image(s) irrécupérable(s).\n";
