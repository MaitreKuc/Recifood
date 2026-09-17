<?php
/**
 * API: Proxy multipart pour importer une recette depuis une image ou un PDF
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$user_id = getCurrentUserId();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Aucun fichier valide reçu.']);
    exit;
}

$allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
$ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext)) {
    echo json_encode(['success' => false, 'error' => 'Format de fichier non supporté. Utilisez une image (JPG, PNG...) ou un PDF.']);
    exit;
}

// Limite de taille : 20 Mo
if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (maximum 20 Mo).']);
    exit;
}

// Si le fichier importé est une image (photo/capture), on en conserve une copie locale
// afin de pouvoir l'utiliser comme vignette de la recette (les IA Vision ne renvoient pas d'URL d'image).
$image_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];
$saved_image_url = null;
if (in_array($ext, $image_exts)) {
    $uploads_dir = __DIR__ . '/../uploads/recipes';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }
    $saved_name = uniqid('recipe_', true) . '.' . $ext;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $uploads_dir . '/' . $saved_name)) {
        $saved_image_url = '/uploads/recipes/' . $saved_name;
    }
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $settings = $stmt->fetch() ?: [];

    $backend_api_url = getenv('BACKEND_API_URL') ?: 'http://backend:8000';

    // Le fichier a potentiellement déjà été déplacé par move_uploaded_file() ci-dessus :
    // on l'envoie donc depuis sa nouvelle destination si besoin, sinon depuis tmp_name.
    $file_to_send = $saved_image_url ? ($uploads_dir . '/' . $saved_name) : $_FILES['file']['tmp_name'];
    $cfile = new CURLFile($file_to_send, $_FILES['file']['type'], $_FILES['file']['name']);

    $ch = curl_init("$backend_api_url/api/import/file");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'file' => $cfile,
        'settings' => json_encode($settings)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response && $http_code === 200) {
        $data = json_decode($response, true);
        // Injection de la vignette locale si l'IA n'a pas fourni d'image et qu'on a réussi à en enregistrer une.
        if ($saved_image_url && !empty($data['success']) && isset($data['recipe']) && empty($data['recipe']['image'])) {
            $data['recipe']['image'] = [$saved_image_url];
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    } else {
        // En cas d'échec, on supprime l'image enregistrée pour ne pas laisser de fichier orphelin.
        if ($saved_image_url) {
            @unlink($uploads_dir . '/' . $saved_name);
        }
        $err = json_decode($response, true)['detail'] ?? "Échec de l'analyse du fichier par le service IA.";
        echo json_encode(['success' => false, 'error' => $err]);
    }
} catch (Exception $e) {
    if ($saved_image_url) {
        @unlink($uploads_dir . '/' . $saved_name);
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
