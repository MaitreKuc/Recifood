<?php
/**
 * API: Enregistrer le contenu du fichier cookies.txt pour yt-dlp
 * Le contenu n'est JAMAIS stocké en base de données ni renvoyé au client :
 * il est écrit directement sur le volume partagé utilisé par le backend Python.
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
$cookies_content = $data['cookies'] ?? '';

$cookies_path = getenv('COOKIES_FILE_PATH') ?: '/data/cookies/cookies.txt';

try {
    if (trim($cookies_content) === '') {
        // Suppression du fichier existant si demandé (vider la configuration)
        if (file_exists($cookies_path)) {
            unlink($cookies_path);
        }

        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, ytdlp_cookies_configured, ytdlp_cookies_updated_at)
            VALUES (:uid, FALSE, NULL)
            ON CONFLICT (user_id) DO UPDATE SET ytdlp_cookies_configured = FALSE, ytdlp_cookies_updated_at = NULL
        ");
        $stmt->execute([':uid' => $user_id]);

        echo json_encode(['success' => true, 'configured' => false, 'message' => 'Cookies supprimés.']);
        exit;
    }

    $dir = dirname($cookies_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $bytes_written = file_put_contents($cookies_path, $cookies_content);
    if ($bytes_written === false) {
        throw new Exception("Impossible d'écrire le fichier de cookies sur le volume partagé.");
    }
    chmod($cookies_path, 0600);

    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO user_settings (user_id, ytdlp_cookies_configured, ytdlp_cookies_updated_at)
        VALUES (:uid, TRUE, CURRENT_TIMESTAMP)
        ON CONFLICT (user_id) DO UPDATE SET ytdlp_cookies_configured = TRUE, ytdlp_cookies_updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([':uid' => $user_id]);

    echo json_encode([
        'success' => true,
        'configured' => true,
        'bytes' => $bytes_written,
        'message' => 'Cookies enregistrés avec succès (' . $bytes_written . ' octets).'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
