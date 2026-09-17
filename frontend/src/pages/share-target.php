<?php
/**
 * Point d'entrée du Web Share Target (PWA)
 *
 * Quand l'utilisateur partage un lien depuis une autre application (Instagram, YouTube,
 * navigateur mobile...) vers Recifood installé en PWA, Android/Chrome envoie une requête
 * GET vers cette page avec les paramètres "title", "text" et/ou "url" (voir manifest.json).
 *
 * Beaucoup d'applications (dont Instagram) ne remplissent que le champ "text" avec le lien
 * dedans plutôt que le champ "url" dédié : on extrait donc la première URL trouvée, peu
 * importe le champ dans lequel elle se trouve.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

function extractFirstUrl(string ...$candidates): ?string {
    foreach ($candidates as $text) {
        if (empty($text)) continue;
        if (preg_match('/https?:\/\/[^\s]+/i', $text, $m)) {
            return rtrim($m[0], '.,!?)]}\'"');
        }
    }
    return null;
}

$shared_url = extractFirstUrl($_GET['url'] ?? '', $_GET['text'] ?? '', $_GET['title'] ?? '');

// Construction du lien de retour vers cette même page de partage (utile après connexion)
$self_query = http_build_query([
    'url' => $_GET['url'] ?? '',
    'text' => $_GET['text'] ?? '',
    'title' => $_GET['title'] ?? '',
]);
$self_url = '/pages/share-target.php?' . $self_query;

if (!isLoggedIn()) {
    // Connexion requise avant importation : on redirige vers le login qui reviendra ici ensuite.
    header('Location: /auth/login.php?redirect=' . urlencode($self_url));
    exit;
}

if ($shared_url) {
    // Redirection vers la page d'import avec l'URL pré-remplie et l'importation lancée automatiquement.
    header('Location: /pages/import.php?shared_url=' . urlencode($shared_url) . '&autorun=1');
    exit;
}

// Aucune URL détectable dans le contenu partagé : on retombe sur l'import avec un message d'erreur.
header('Location: /pages/import.php?share_error=1');
exit;
