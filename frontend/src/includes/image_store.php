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
const RECIPE_IMAGE_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function recipeImageStorageDir(): string
{
    return __DIR__ . '/../uploads/recipes';
}

/**
 * Les échecs sont silencieux côté utilisateur (une recette doit toujours pouvoir être enregistrée),
 * mais tracés dans les logs Apache pour rester diagnosticables : `docker compose logs frontend`.
 */
function logRecipeImageFailure(string $reason, string $url = ''): void
{
    error_log('[recifood][image] ' . $reason . ($url !== '' ? ' | ' . substr($url, 0, 200) : ''));
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
 * Vérifie que le dossier de stockage existe et est réellement accessible en écriture
 * par l'utilisateur qui exécute PHP (www-data sous Apache, root en CLI).
 */
function ensureRecipeImageDirWritable(): bool
{
    $dir = recipeImageStorageDir();

    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        logRecipeImageFailure("création impossible du dossier {$dir} (permissions du montage ?)");
        return false;
    }

    if (!is_writable($dir)) {
        logRecipeImageFailure("dossier {$dir} non accessible en écriture par l'utilisateur PHP courant");
        return false;
    }

    return true;
}

function isFetchableImageUrl(string $url): bool
{
    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

    return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Télécharge une image distante et renvoie son contenu binaire, ou null en cas d'échec.
 */
function fetchRemoteImageBytes(string $url): ?string
{
    $headers = ['Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8'];

    // Les CDN Meta (Instagram/Facebook) renvoient parfois 403 sans Referer cohérent.
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    if (str_contains($host, 'cdninstagram') || str_contains($host, 'fbcdn')) {
        $headers[] = 'Referer: https://www.instagram.com/';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => RECIPE_IMAGE_USER_AGENT,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        // Interrompt le téléchargement dès que l'image dépasse la taille maximale autorisée.
        CURLOPT_NOPROGRESS => false,
        CURLOPT_PROGRESSFUNCTION => static function ($res, $downloadSize, $downloaded) {
            return ($downloaded > RECIPE_IMAGE_MAX_BYTES || $downloadSize > RECIPE_IMAGE_MAX_BYTES) ? 1 : 0;
        },
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $body === '') {
        logRecipeImageFailure('téléchargement échoué' . ($error !== '' ? " ({$error})" : ''), $url);
        return null;
    }

    if ($http_code < 200 || $http_code >= 300) {
        logRecipeImageFailure("réponse HTTP {$http_code} (lien probablement expiré)", $url);
        return null;
    }

    return (string)$body;
}

/**
 * Écrit le contenu d'une image sur le disque et renvoie son chemin public, ou null en cas d'échec.
 */
function writeRecipeImageFile(string $body, string $url = ''): ?string
{
    // Le type MIME réel prime sur l'en-tête renvoyé par le CDN (souvent générique).
    $info = @getimagesizefromstring($body);
    $ext = recipeImageExtensionFromMime((string)($info['mime'] ?? ''));
    if ($ext === null) {
        logRecipeImageFailure('contenu téléchargé non reconnu comme une image', $url);
        return null;
    }

    if (!ensureRecipeImageDirWritable()) {
        return null;
    }

    $filename = uniqid('recipe_', true) . '.' . $ext;
    $path = recipeImageStorageDir() . '/' . $filename;
    if (@file_put_contents($path, $body) === false) {
        logRecipeImageFailure("écriture impossible de {$path}", $url);
        return null;
    }
    @chmod($path, 0644);

    return RECIPE_IMAGE_PUBLIC_DIR . '/' . $filename;
}

/**
 * Télécharge une image distante et renvoie son chemin public local.
 * En cas d'échec (URL invalide, réseau indisponible, contenu non-image...), l'URL d'origine
 * est renvoyée telle quelle afin de ne jamais bloquer l'enregistrement d'une recette.
 */
function storeRecipeImageLocally(mixed $url): string
{
    if (is_array($url)) {
        // Un objet schema.org ImageObject expose l'adresse réelle dans 'url' ou 'contentUrl'.
        $url = $url['url'] ?? ($url['contentUrl'] ?? ($url[0] ?? ''));
    }
    $url = trim((string)$url);

    if ($url === '' || isLocalRecipeImage($url)) {
        return $url;
    }

    if (!isFetchableImageUrl($url)) {
        logRecipeImageFailure("URL d'image invalide", $url);
        return $url;
    }

    $body = fetchRemoteImageBytes($url);
    if ($body === null) {
        return $url;
    }

    return writeRecipeImageFile($body, $url) ?? $url;
}

/**
 * Récupère les adresses d'images déclarées par une page (og:image, twitter:image).
 * Sert de plan B lorsque l'URL d'image enregistrée a expiré : la page d'origine, elle, expose
 * toujours une adresse fraîchement signée.
 */
function fetchSourcePageImageUrls(string $source_url): array
{
    $source_url = trim($source_url);
    if ($source_url === '' || !isFetchableImageUrl($source_url)) {
        return [];
    }

    // Le robot Facebook obtient les balises og: des posts sociaux sans authentification.
    $user_agents = [
        'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
        RECIPE_IMAGE_USER_AGENT,
    ];

    foreach ($user_agents as $user_agent) {
        $ch = curl_init($source_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => $user_agent,
            CURLOPT_HTTPHEADER => ['Accept-Language: fr-FR,fr;q=0.9,en;q=0.8'],
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        ]);
        $html = curl_exec($ch);
        $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($html) || $html === '' || $http_code < 200 || $http_code >= 300) {
            continue;
        }

        $candidates = [];
        if (preg_match_all(
            '/<meta[^>]+(?:property|name)\s*=\s*["\'](?:og:image(?::secure_url)?|twitter:image(?::src)?)["\'][^>]*>/i',
            $html,
            $meta_tags
        )) {
            foreach ($meta_tags[0] as $tag) {
                if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $tag, $m)) {
                    $candidates[] = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates, 'isFetchableImageUrl')));
        if ($candidates) {
            return $candidates;
        }
    }

    logRecipeImageFailure("aucune image exploitable trouvée sur la page d'origine", $source_url);

    return [];
}

/**
 * Stocke une image en local, avec repli sur la page d'origine si le lien direct a expiré.
 */
function storeRecipeImageWithFallback(mixed $url, string $source_url = ''): string
{
    $stored = storeRecipeImageLocally($url);
    if (isLocalRecipeImage($stored) || $source_url === '') {
        return $stored;
    }

    foreach (fetchSourcePageImageUrls($source_url) as $candidate) {
        $recovered = storeRecipeImageLocally($candidate);
        if (isLocalRecipeImage($recovered)) {
            return $recovered;
        }
    }

    return $stored;
}

/**
 * Remplace les images distantes d'une recette schema.org par leurs copies locales.
 * Renvoie la recette modifiée ; la première image sert de vignette.
 */
function localizeRecipeImages(array $recipe, string $source_url = ''): array
{
    $images = $recipe['image'] ?? [];
    if (!is_array($images) || isset($images['url']) || isset($images['contentUrl'])) {
        $images = ($images === '' || $images === []) ? [] : [$images];
    }

    $localized = [];
    $recovery_done = false;
    foreach ($images as $image) {
        if (!is_string($image) && !is_array($image)) {
            continue;
        }
        // Le repli via la page d'origine ne sert qu'une fois : une page n'expose qu'une image
        // principale, inutile de l'interroger pour chaque photo d'un carrousel.
        $stored = $recovery_done
            ? storeRecipeImageLocally($image)
            : storeRecipeImageWithFallback($image, $source_url);
        if (!isLocalRecipeImage($stored) && $source_url !== '') {
            $recovery_done = true;
        }
        if ($stored !== '' && !in_array($stored, $localized, true)) {
            $localized[] = $stored;
        }
    }

    $has_local = false;
    foreach ($localized as $image) {
        if (isLocalRecipeImage($image)) {
            $has_local = true;
            break;
        }
    }

    // Aucune image utilisable : la page d'origine peut tout de même en fournir une fraîche.
    if (!$has_local && $source_url !== '' && !$recovery_done) {
        foreach (fetchSourcePageImageUrls($source_url) as $candidate) {
            $recovered = storeRecipeImageLocally($candidate);
            if (isLocalRecipeImage($recovered)) {
                array_unshift($localized, $recovered);
                break;
            }
        }
    }

    if ($localized) {
        $recipe['image'] = $localized;
    }

    return $recipe;
}
