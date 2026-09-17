<?php
/**
 * API Handler pour les importations (Web Scraping JSON-LD, Vidéo yt-dlp, Texte IA)
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
$action = $data['action'] ?? '';

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $settings = $stmt->fetch() ?: [];

    $backend_api_url = getenv('BACKEND_API_URL') ?: 'http://backend:8000';

    if ($action === 'site_auto') {
        // Point d'entrée unique "Site Web" : le backend détecte automatiquement la meilleure stratégie
        // (Schema.org/microdata -> Vidéo yt-dlp -> Post réseau social) sans que l'utilisateur ait à choisir.
        $url = trim($data['url'] ?? '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'URL invalide']);
            exit;
        }

        $ch = curl_init("$backend_api_url/api/import/auto");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'url' => $url,
            'settings' => $settings
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 180); // Cascade schema -> vidéo -> réseau social, peut cumuler plusieurs tentatives
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $http_code === 200) {
            echo $response;
            exit;
        } else {
            $err = json_decode($response, true)['detail'] ?? 'Le service d\'importation Python est indisponible ou a échoué.';
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }

    } elseif ($action === 'scrape_url') {
        $url = trim($data['url'] ?? '');
        if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'error' => 'URL invalide']);
            exit;
        }

        // 1. Tenter d'abord via le backend Python
        $ch = curl_init("$backend_api_url/api/import/url");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $url]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $http_code === 200) {
            $parsed = json_decode($response, true);
            if (!empty($parsed['success']) && !empty($parsed['recipe'])) {
                echo json_encode(['success' => true, 'recipe' => $parsed['recipe']]);
                exit;
            }
        }

        // 2. Fallback PHP direct : Téléchargement du HTML et extraction des balises JSON-LD
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\nAccept-Language: fr,fr-FR;q=0.9,en;q=0.8\r\n",
                'timeout' => 12,
                'follow_location' => 1
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $html = @file_get_contents($url, false, $context);
        if (!$html) {
            echo json_encode(['success' => false, 'error' => 'Impossible de contacter le site cible.']);
            exit;
        }

        $recipe = extractSchemaRecipeFromHtml($html, $url);
        if ($recipe) {
            echo json_encode(['success' => true, 'recipe' => $recipe]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Aucune balise schema.org/Recipe JSON-LD trouvée sur cette page.']);
            exit;
        }

    } elseif ($action === 'video_ai') {
        $url = trim($data['url'] ?? '');
        
        $ch = curl_init("$backend_api_url/api/import/video");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'url' => $url,
            'settings' => $settings
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Les vidéos prennent plus de temps
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $http_code === 200) {
            echo $response;
            exit;
        } else {
            $err = json_decode($response, true)['detail'] ?? 'Le service d\'extraction vidéo Python est indisponible ou a échoué.';
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }

    } elseif ($action === 'text_ai') {
        $text = trim($data['text'] ?? '');

        $ch = curl_init("$backend_api_url/api/import/text");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'text' => $text,
            'settings' => $settings
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $http_code === 200) {
            echo $response;
            exit;
        } else {
            $err = json_decode($response, true)['detail'] ?? 'Échec lors de l\'appel au backend IA Python.';
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }

    } elseif ($action === 'social_ai') {
        $url = trim($data['url'] ?? '');

        $ch = curl_init("$backend_api_url/api/import/social");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'url' => $url,
            'settings' => $settings
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // Téléchargement photos/audio + IA Vision peut prendre du temps
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $http_code === 200) {
            echo $response;
            exit;
        } else {
            $err = json_decode($response, true)['detail'] ?? 'Le service d\'extraction du post social Python est indisponible ou a échoué.';
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }

    } elseif ($action === 'imagine_ai') {
        $prompt = trim($data['prompt'] ?? '');

        $ch = curl_init("$backend_api_url/api/import/imagine");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'prompt' => $prompt,
            'settings' => $settings
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response && $http_code === 200) {
            echo $response;
            exit;
        } else {
            $err = json_decode($response, true)['detail'] ?? 'Échec lors de l\'appel au backend IA Python.';
            echo json_encode(['success' => false, 'error' => $err]);
            exit;
        }

    } else {
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Fonction d'extraction de recipe Schema.org à partir du HTML
 */
function extractSchemaRecipeFromHtml(string $html, string $sourceUrl): ?array {
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $scripts = $dom->getElementsByTagName('script');

    foreach ($scripts as $script) {
        if ($script->getAttribute('type') === 'application/ld+json') {
            $content = trim($script->nodeValue);
            $json = json_decode($content, true);
            if (!$json) continue;

            $recipe = findRecipeInJsonLd($json);
            if ($recipe) {
                if (empty($recipe['url'])) $recipe['url'] = $sourceUrl;
                return $recipe;
            }
        }
    }
    return null;
}

function findRecipeInJsonLd($data) {
    if (!is_array($data)) return null;

    if (isset($data['@type'])) {
        $type = $data['@type'];
        if ($type === 'Recipe' || (is_array($type) && in_array('Recipe', $type))) {
            return $data;
        }
    }

    if (isset($data['@graph']) && is_array($data['@graph'])) {
        foreach ($data['@graph'] as $node) {
            $res = findRecipeInJsonLd($node);
            if ($res) return $res;
        }
    }

    // Tableau de premier niveau
    foreach ($data as $item) {
        if (is_array($item)) {
            $res = findRecipeInJsonLd($item);
            if ($res) return $res;
        }
    }

    return null;
}
