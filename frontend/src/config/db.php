<?php
/**
 * Connexion à la base de données PostgreSQL pour Recifood
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '5432';
$db_name = getenv('DB_NAME') ?: 'recifood';
$db_user = getenv('DB_USER') ?: 'recifood_user';
$db_pass = getenv('DB_PASSWORD') ?: 'recifood_secret_2026';

$backend_api_url = getenv('BACKEND_API_URL') ?: 'http://backend:8000';

$pdo = null;

try {
    $dsn = "pgsql:host=$db_host;port=$db_port;dbname=$db_name;options='--client_encoding=UTF8'";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Si la DB n'est pas encore accessible, on conserve l'erreur
    $db_error = $e->getMessage();
}

/**
 * Fonction utilitaire pour récupérer l'instance PDO
 */
function getDB() {
    global $pdo, $db_error;
    if (!$pdo) {
        throw new Exception("Erreur de connexion à la base de données PostgreSQL : " . ($db_error ?? 'Inconnue'));
    }
    return $pdo;
}

/**
 * Convertit une durée ISO 8601 (ex: PT25M, PT1H30M) en texte lisible en français
 */
function formatIsoDuration(?string $iso): string {
    if (!$iso) return 'N/C';
    // Si déjà formaté en clair
    if (strpos($iso, 'PT') === false && strpos($iso, 'P') === false) {
        return $iso;
    }
    try {
        $interval = new DateInterval($iso);
        $parts = [];
        if ($interval->h > 0) $parts[] = $interval->h . 'h';
        if ($interval->i > 0) $parts[] = $interval->i . 'min';
        if ($interval->s > 0 && $interval->h == 0 && $interval->i == 0) $parts[] = $interval->s . 's';
        return empty($parts) ? '0 min' : implode(' ', $parts);
    } catch (Exception $e) {
        return preg_replace('/[^0-9hm]/', '', strtolower($iso));
    }
}

/**
 * Convertit des minutes en format ISO 8601 (ex: 45 -> PT45M, 90 -> PT1H30M)
 */
function minutesToIsoDuration(int $minutes): string {
    if ($minutes <= 0) return 'PT0M';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    $res = 'PT';
    if ($hours > 0) $res .= $hours . 'H';
    if ($mins > 0 || $hours === 0) $res .= $mins . 'M';
    return $res;
}

/**
 * Convertit une durée ISO 8601 (ex: PT1H30M) en nombre de minutes entier. Utilisé pour pré-remplir les formulaires.
 */
function isoDurationToMinutes(?string $iso): int {
    if (empty($iso)) return 0;
    try {
        $interval = new DateInterval($iso);
        return ((int)$interval->h * 60) + (int)$interval->i;
    } catch (Exception $e) {
        return 0;
    }
}
