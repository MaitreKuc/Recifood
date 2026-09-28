<?php
/**
 * Stockage local des miniatures de recettes.
 *
 * Les URL d'images des réseaux sociaux (Instagram/Facebook CDN...) contiennent une signature
 * qui expire au bout de quelques jours : la vignette devient alors cassée. On télécharge donc
 * l'image une fois pour toutes dans /uploads/recipes et on ne conserve que le chemin local.
 */

const RECIPE_IMAGE_MAX_BYTES = 12 * 1024 * 1024;
const RECIPE_IMAGE_PUBLIC_DIR = '/uploads/recipes';

function recipeImageStorageDir(): string
{
    return __DIR__ . '/../uploads/recipes';
}

/**
 * Une image déjà servie par Recifood (chemin relatif) n'a pas besoin d'être retéléchargée.
 */
function isLocalRecipeImage(string $url): bool
{
    return $url !== '' && str_starts_with($url, RECIPE_IMAGE_PUBLIC_DIR . '/');
}

function recipeImageExtensionFromMime(string $mime): ?string
{
    return match (strtolower(trim(explode(';', $mime)[0]))) {
        'image/jpeg', 'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'image/avif' => 'avif',
        default => null,
    };
}

/**
 * Télécharge une image distante et renvoie son chemin public local.
 * En cas d'échec (URL invalide, réseau indisponible, contenu non-image...), l'URL d'origine
 * est renvoyée telle quelle afin de ne jamais bloquer l'enregistrement d'une recette.
 */
function storeRecipeImageLocally(mixed $url): string
{
    if (is_array($url)) {
        $url = $url[0] ?? '';
    }
    $url = trim((string)$url);

    if ($url === '' || isLocalRecipeImage($url)) {
        return $url;
    }

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || !filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    $dir = recipeImageStorageDir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return $url;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        // Interrompt le téléchargement dès que l'image dépasse la taille maximale autorisée.
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => static function ($res, $downloadSize, $downloaded) {
            return ($downloaded > RECIPE_IMAGE_MAX_BYTES || $downloadSize > RECIPE_IMAGE_MAX_BYTES) ? 1 : 0;
        },
    ]);

    $body = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($body === false || $body === '' || $http_code < 200 || $http_code >= 300) {
        return $url;
    }

    // Le type MIME réel prime sur l'en-tête renvoyé par le CDN (souvent générique).
    $info = @getimagesizefromstring($body);
    $mime = $info['mime'] ?? $content_type;
    $ext = recipeImageExtensionFromMime((string)$mime);
    if ($ext === null) {
        return $url;
    }

    $filename = uniqid('recipe_', true) . '.' . $ext;
    if (@file_put_contents($dir . '/' . $filename, $body) === false) {
        return $url;
    }

    return RECIPE_IMAGE_PUBLIC_DIR . '/' . $filename;
}

/**
 * Remplace les images distantes d'une recette schema.org par leurs copies locales.
 * Renvoie la recette modifiée ; la première image sert de vignette.
 */
function localizeRecipeImages(array $recipe): array
{
    $images = $recipe['image'] ?? [];
    if (!is_array($images)) {
        $images = $images === '' ? [] : [$images];
    }

    $localized = [];
    foreach ($images as $image) {
        if (!is_string($image) && !is_array($image)) {
            continue;
        }
        $stored = storeRecipeImageLocally($image);
        if ($stored !== '' && !in_array($stored, $localized, true)) {
            $localized[] = $stored;
        }
    }

    if ($localized) {
        $recipe['image'] = $localized;
    }

    return $recipe;
}
