<?php

function normalizeSourceUrl(mixed $source): string
{
    if (is_array($source)) {
        $source = $source['@id'] ?? $source['url'] ?? '';
    }
    $source = trim((string)$source);
    if ($source === '' || !filter_var($source, FILTER_VALIDATE_URL)) {
        return '';
    }

    $parts = parse_url($source);
    if (!$parts || empty($parts['host'])) {
        return $source;
    }

    $scheme = strtolower($parts['scheme'] ?? 'https');
    $host = strtolower($parts['host']);
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '/';
    $path = $path !== '/' ? rtrim($path, '/') : $path;

    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach (array_keys($query) as $key) {
            if (str_starts_with(strtolower((string)$key), 'utm_')
                || in_array(strtolower((string)$key), ['fbclid', 'gclid', 'stkn'], true)) {
                unset($query[$key]);
            }
        }
        ksort($query);
    }

    return $scheme . '://' . $host . $port . $path . ($query ? '?' . http_build_query($query) : '');
}

function findRecipeIdBySourceUrl(PDO $db, string $sourceUrl): ?int
{
    if ($sourceUrl === '') {
        return null;
    }

    $exact = $db->prepare("SELECT id FROM recipes WHERE source_url = :source LIMIT 1");
    $exact->execute([':source' => $sourceUrl]);
    $recipeId = $exact->fetchColumn();
    if ($recipeId) {
        return (int)$recipeId;
    }

    // Compatibilité avec les recettes enregistrées avant la normalisation des URL :
    // leurs paramètres de suivi (utm_*, fbclid, stkn...) peuvent encore être présents en base.
    $legacy = $db->query("SELECT id, source_url FROM recipes WHERE source_url IS NOT NULL AND source_url <> ''");
    foreach ($legacy->fetchAll() as $recipe) {
        if (normalizeSourceUrl($recipe['source_url']) === $sourceUrl) {
            return (int)$recipe['id'];
        }
    }

    return null;
}
