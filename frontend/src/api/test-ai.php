<?php
/**
 * API: Tester la connexion à un endpoint IA (Texte, Vision ou Transcription)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$api_url = trim($data['api_url'] ?? '');
$api_key = $data['api_key'] ?? '';
$model = $data['model'] ?? '';

if (empty($api_url)) {
    echo json_encode(['success' => false, 'error' => "Veuillez renseigner une URL d'API avant de tester."]);
    exit;
}

$backend_api_url = getenv('BACKEND_API_URL') ?: 'http://backend:8000';

$start = microtime(true);

$ch = curl_init("$backend_api_url/api/settings/test-ai");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'api_url' => $api_url,
    'api_key' => $api_key,
    'model' => $model
]));
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$latency = round((microtime(true) - $start) * 1000);

if ($response && $http_code === 200) {
    $res = json_decode($response, true);
    $res['latency_ms'] = $latency;
    echo json_encode($res);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Impossible de joindre le service backend (' . ($http_code ? "HTTP $http_code" : "Timeout") . ')',
        'latency_ms' => $latency
    ]);
}
