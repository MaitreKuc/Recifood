<?php
/**
 * Page des Paramètres (Compte, Configuration yt-dlp, Providers IA)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();

$user_id = getCurrentUserId();
$success = '';
$error = '';
$account_success = '';
$account_error = '';

try {
    $db = getDB();

    // Formulaire "Compte" : changement de pseudo et/ou de mot de passe (formulaires distincts,
    // identifiés par le champ caché "form_action" pour ne pas interférer avec les paramètres IA/yt-dlp).
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'update_username') {
        $new_username = trim($_POST['new_username'] ?? '');

        if (strlen($new_username) < 3 || strlen($new_username) > 50) {
            $account_error = "Le nom d'utilisateur doit comporter entre 3 et 50 caractères.";
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(:u) AND id != :uid");
            $stmt->execute([':u' => $new_username, ':uid' => $user_id]);
            if ($stmt->fetch()) {
                $account_error = "Ce nom d'utilisateur est déjà utilisé.";
            } else {
                $stmt = $db->prepare("UPDATE users SET username = :u WHERE id = :uid");
                $stmt->execute([':u' => $new_username, ':uid' => $user_id]);
                $_SESSION['username'] = $new_username;
                $account_success = "Votre nom d'utilisateur a été mis à jour avec succès.";
            }
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? '') === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $new_password_confirm = $_POST['new_password_confirm'] ?? '';

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :uid");
        $stmt->execute([':uid' => $user_id]);
        $current_hash = $stmt->fetchColumn();

        if (empty($current_password) || empty($new_password)) {
            $account_error = "Veuillez renseigner votre mot de passe actuel et le nouveau mot de passe.";
        } elseif (!$current_hash || !password_verify($current_password, $current_hash)) {
            $account_error = "Le mot de passe actuel est incorrect.";
        } elseif (strlen($new_password) < 6) {
            $account_error = "Le nouveau mot de passe doit comporter au moins 6 caractères.";
        } elseif ($new_password !== $new_password_confirm) {
            $account_error = "Les deux mots de passe ne correspondent pas.";
        } else {
            $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password_hash = :p WHERE id = :uid");
            $stmt->execute([':p' => $new_hash, ':uid' => $user_id]);
            $account_success = "Votre mot de passe a été mis à jour avec succès.";
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? 'save_settings') === 'save_settings') {
        $ytdlp_path = trim($_POST['ytdlp_path'] ?? 'yt-dlp');
        $ytdlp_download_video = isset($_POST['ytdlp_download_video']) ? 1 : 0;
        $ytdlp_audio_only = isset($_POST['ytdlp_audio_only']) ? 1 : 0;

        $ai_text_api_url = trim($_POST['ai_text_api_url'] ?? '');
        $ai_text_api_key = trim($_POST['ai_text_api_key'] ?? '');
        $ai_text_model = trim($_POST['ai_text_model'] ?? '');

        $ai_vision_api_url = trim($_POST['ai_vision_api_url'] ?? '');
        $ai_vision_api_key = trim($_POST['ai_vision_api_key'] ?? '');
        $ai_vision_model = trim($_POST['ai_vision_model'] ?? '');

        $ai_transcription_api_url = trim($_POST['ai_transcription_api_url'] ?? '');
        $ai_transcription_api_key = trim($_POST['ai_transcription_api_key'] ?? '');
        $ai_transcription_model = trim($_POST['ai_transcription_model'] ?? '');

        // Sauvegarde ou mise à jour (les colonnes liées aux cookies ne sont jamais touchées ici)
        $stmt = $db->prepare("
            INSERT INTO user_settings (
                user_id, ytdlp_path, ytdlp_download_video, ytdlp_audio_only,
                ai_text_api_url, ai_text_api_key, ai_text_model,
                ai_vision_api_url, ai_vision_api_key, ai_vision_model,
                ai_transcription_api_url, ai_transcription_api_key, ai_transcription_model,
                updated_at
            ) VALUES (
                :uid, :ypath, :ydlvid, :yaudio,
                :taurl, :takey, :tamodel,
                :vaurl, :vakey, :vamodel,
                :xaurl, :xakey, :xamodel,
                CURRENT_TIMESTAMP
            )
            ON CONFLICT (user_id) DO UPDATE SET
                ytdlp_path = EXCLUDED.ytdlp_path,
                ytdlp_download_video = EXCLUDED.ytdlp_download_video,
                ytdlp_audio_only = EXCLUDED.ytdlp_audio_only,
                ai_text_api_url = EXCLUDED.ai_text_api_url,
                ai_text_api_key = EXCLUDED.ai_text_api_key,
                ai_text_model = EXCLUDED.ai_text_model,
                ai_vision_api_url = EXCLUDED.ai_vision_api_url,
                ai_vision_api_key = EXCLUDED.ai_vision_api_key,
                ai_vision_model = EXCLUDED.ai_vision_model,
                ai_transcription_api_url = EXCLUDED.ai_transcription_api_url,
                ai_transcription_api_key = EXCLUDED.ai_transcription_api_key,
                ai_transcription_model = EXCLUDED.ai_transcription_model,
                updated_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':uid' => $user_id,
            ':ypath' => $ytdlp_path,
            ':ydlvid' => $ytdlp_download_video,
            ':yaudio' => $ytdlp_audio_only,
            ':taurl' => $ai_text_api_url,
            ':takey' => $ai_text_api_key,
            ':tamodel' => $ai_text_model,
            ':vaurl' => $ai_vision_api_url,
            ':vakey' => $ai_vision_api_key,
            ':vamodel' => $ai_vision_model,
            ':xaurl' => $ai_transcription_api_url,
            ':xakey' => $ai_transcription_api_key,
            ':xamodel' => $ai_transcription_model
        ]);

        $success = "Vos paramètres ont été enregistrés avec succès.";
    }

    // Chargement des paramètres actuels
    $stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $settings = $stmt->fetch() ?: [
        'ytdlp_path' => 'yt-dlp',
        'ytdlp_download_video' => false,
        'ytdlp_audio_only' => true,
        'ytdlp_cookies_configured' => false,
        'ytdlp_cookies_updated_at' => null,
        'ai_text_api_url' => '', 'ai_text_api_key' => '', 'ai_text_model' => '',
        'ai_vision_api_url' => '', 'ai_vision_api_key' => '', 'ai_vision_model' => '',
        'ai_transcription_api_url' => '', 'ai_transcription_api_key' => '', 'ai_transcription_model' => '',
    ];

    // Chargement des informations du compte (pseudo / email actuels)
    $stmt = $db->prepare("SELECT username, email FROM users WHERE id = :uid");
    $stmt->execute([':uid' => $user_id]);
    $account = $stmt->fetch() ?: ['username' => getCurrentUsername(), 'email' => ''];

} catch (Exception $e) {
    $error = "Erreur : " . $e->getMessage();
}

$page_title = "Paramètres & Intégrations - Recifood";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';

/**
 * Petit composant réutilisable pour un bloc IA (Texte / Vision / Transcription)
 */
function render_ai_block(string $key, string $title, string $subtitle, string $icon, string $gradient, string $badge, array $settings) {
    $url = htmlspecialchars($settings["ai_{$key}_api_url"] ?? '');
    $apikey = htmlspecialchars($settings["ai_{$key}_api_key"] ?? '');
    $model = htmlspecialchars($settings["ai_{$key}_model"] ?? '');
    ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-5">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr <?= $gradient ?> flex items-center justify-center text-white shadow-sm">
                    <i class="<?= $icon ?> text-lg"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($title) ?></h2>
                    <p class="text-xs text-slate-500"><?= htmlspecialchars($subtitle) ?></p>
                </div>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 bg-slate-50 text-slate-700 rounded-lg border border-slate-200">
                <?= htmlspecialchars($badge) ?>
            </span>
        </div>

        <div>
            <label for="ai_<?= $key ?>_api_url" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                URL de l'API (compatible OpenAI)
            </label>
            <input type="text" id="ai_<?= $key ?>_api_url" name="ai_<?= $key ?>_api_url"
                   value="<?= $url ?>"
                   placeholder="https://api.exemple.com/v1"
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="ai_<?= $key ?>_api_key" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                    Clé API
                </label>
                <div class="relative">
                    <input type="password" id="ai_<?= $key ?>_api_key" name="ai_<?= $key ?>_api_key"
                           value="<?= $apikey ?>"
                           placeholder="sk-..."
                           class="w-full pl-4 pr-12 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-mono focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                    <button type="button" onclick="togglePasswordVisibility('ai_<?= $key ?>_api_key', this)"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600">
                        <i class="fa-regular fa-eye text-sm"></i>
                    </button>
                </div>
            </div>
            <div>
                <label for="ai_<?= $key ?>_model" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                    Modèle
                </label>
                <input type="text" id="ai_<?= $key ?>_model" name="ai_<?= $key ?>_model"
                       value="<?= $model ?>"
                       placeholder="ex: gpt-4o-mini"
                       class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
            </div>
        </div>

        <div class="pt-1 flex items-center justify-between">
            <button type="button" onclick="testAiConnection('<?= $key ?>')" id="btn-test-<?= $key ?>"
                    class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition flex items-center gap-2">
                <i class="fa-solid fa-plug"></i> Tester la connexion
            </button>
            <div id="ai-test-result-<?= $key ?>" class="text-xs"></div>
        </div>
    </div>
    <?php
}
?>

<main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-slate-900 font-['Plus_Jakarta_Sans']">
            Paramètres & Intégrations
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            Renseignez vos propres endpoints IA compatibles OpenAI (URL, clé, modèle) pour le texte, la vision et la transcription audio, ainsi que la configuration de yt-dlp.
        </p>
    </div>

    <?php if (!empty($success)): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-start space-x-3">
            <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start space-x-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-500"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($account_success)): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-start space-x-3">
            <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500"></i>
            <div><?= htmlspecialchars($account_success) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($account_error)): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start space-x-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-500"></i>
            <div><?= htmlspecialchars($account_error) ?></div>
        </div>
    <?php endif; ?>

    <!-- SECTION 0 : COMPTE (Pseudo & Mot de passe) -->
    <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6 mb-8">
        <div class="flex items-center space-x-3 pb-4 border-b border-slate-100">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-slate-700 to-slate-900 flex items-center justify-center text-white shadow-sm">
                <i class="fa-solid fa-user-gear text-lg"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-900">Mon Compte</h2>
                <p class="text-xs text-slate-500">Modifiez votre nom d'utilisateur et votre mot de passe</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Changement de pseudo -->
            <form method="POST" action="/pages/settings.php" class="space-y-3">
                <input type="hidden" name="form_action" value="update_username">
                <label for="new_username" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Nom d'utilisateur
                </label>
                <input type="text" id="new_username" name="new_username" required minlength="3" maxlength="50"
                       value="<?= htmlspecialchars($account['username']) ?>"
                       class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                <button type="submit"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl transition flex items-center gap-2">
                    <i class="fa-solid fa-pen"></i> Mettre à jour le pseudo
                </button>
            </form>

            <!-- Changement de mot de passe -->
            <form method="POST" action="/pages/settings.php" class="space-y-3">
                <input type="hidden" name="form_action" value="update_password">
                <div>
                    <label for="current_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Mot de passe actuel
                    </label>
                    <input type="password" id="current_password" name="current_password" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="new_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                            Nouveau
                        </label>
                        <input type="password" id="new_password" name="new_password" required minlength="6"
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                    </div>
                    <div>
                        <label for="new_password_confirm" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                            Confirmer
                        </label>
                        <input type="password" id="new_password_confirm" name="new_password_confirm" required minlength="6"
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                    </div>
                </div>
                <button type="submit"
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl transition flex items-center gap-2">
                    <i class="fa-solid fa-key"></i> Mettre à jour le mot de passe
                </button>
            </form>
        </div>
    </div>

    <form method="POST" action="/pages/settings.php" class="space-y-8">
        <input type="hidden" name="form_action" value="save_settings">

        <!-- SECTION 1 : IA TEXTE -->
        <?php render_ai_block(
            'text', 'IA — Texte', "Structure les recettes (titre, ingrédients, étapes...) au format Schema.org",
            'fa-solid fa-file-lines', 'from-amber-500 to-orange-500', 'Texte → JSON-LD', $settings
        ); ?>

        <!-- SECTION 2 : IA VISION -->
        <?php render_ai_block(
            'vision', 'IA — Vision', "Analyse des images ou pages de PDF scannées pour en extraire la recette",
            'fa-solid fa-eye', 'from-sky-500 to-blue-600', 'Image → JSON-LD', $settings
        ); ?>

        <!-- SECTION 3 : IA TRANSCRIPTION -->
        <?php render_ai_block(
            'transcription', 'IA — Transcription audio', "Transcrit l'audio des vidéos importées avant structuration",
            'fa-solid fa-microphone', 'from-purple-500 to-fuchsia-600', 'Audio → Texte', $settings
        ); ?>

        <!-- SECTION 4 : CONFIGURATION YT-DLP (EXTRACTION VIDÉO) -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-500 to-rose-600 flex items-center justify-center text-white shadow-sm">
                        <i class="fa-brands fa-youtube text-lg"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">Configuration yt-dlp</h2>
                        <p class="text-xs text-slate-500">Moteur d'extraction et de téléchargement multimédia (YouTube, TikTok, etc.)</p>
                    </div>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 bg-red-50 text-red-800 rounded-lg border border-red-200">
                    CLI yt-dlp
                </span>
            </div>

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Statut des cookies
                    </label>
                    <?php if (!empty($settings['ytdlp_cookies_configured'])): ?>
                        <div class="w-full px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-700 font-medium flex items-center gap-2">
                            <i class="fa-solid fa-circle-check"></i>
                            Configurés
                            <?php if (!empty($settings['ytdlp_cookies_updated_at'])): ?>
                                <span class="text-emerald-500 font-normal text-xs">(maj le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($settings['ytdlp_cookies_updated_at']))) ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm text-slate-500 flex items-center gap-2">
                            <i class="fa-regular fa-circle"></i> Non configurés
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="ytdlp_audio_only" value="1"
                           <?= !empty($settings['ytdlp_audio_only']) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-brand-500 focus:ring-brand-400 w-4 h-4">
                    <span class="text-sm text-slate-700 font-medium">Extraire uniquement l'audio pour transcription rapide (Recommandé)</span>
                </label>

                <label class="flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="ytdlp_download_video" value="1"
                           <?= !empty($settings['ytdlp_download_video']) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-brand-500 focus:ring-brand-400 w-4 h-4">
                    <span class="text-sm text-slate-700 font-medium">Conserver une copie locale de la vidéo de recette</span>
                </label>
            </div>

            <!-- Cookies : collage sécurisé, jamais réaffichés -->
            <div class="pt-4 border-t border-slate-100 space-y-2">
                <label for="cookies_paste" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                    Cookies (format Netscape cookies.txt)
                </label>
                <p class="text-[11px] text-slate-400">
                    Collez ici le contenu de votre fichier <code>cookies.txt</code> (exporté depuis votre navigateur) pour permettre à yt-dlp de télécharger des vidéos privées/limitées.
                    Il est écrit directement dans le volume partagé et <strong>n'est jamais réaffiché ni stocké en base de données</strong>.
                </p>
                <textarea id="cookies_paste" rows="5" placeholder="# Netscape HTTP Cookie File&#10;.youtube.com&#9;TRUE&#9;/&#9;TRUE&#9;...&#9;...&#9;..."
                          class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"></textarea>
                <div class="flex items-center justify-between pt-1">
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="saveCookies()" id="btn-save-cookies"
                                class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded-xl transition flex items-center gap-2">
                            <i class="fa-solid fa-lock"></i> Enregistrer les cookies
                        </button>
                        <?php if (!empty($settings['ytdlp_cookies_configured'])): ?>
                            <button type="button" onclick="clearCookies()" id="btn-clear-cookies"
                                    class="px-4 py-2 bg-white hover:bg-slate-50 text-red-600 border border-red-200 text-xs font-semibold rounded-xl transition flex items-center gap-2">
                                <i class="fa-solid fa-trash"></i> Supprimer
                            </button>
                        <?php endif; ?>
                    </div>
                    <div id="cookies-result" class="text-xs"></div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <button type="submit"
                    class="px-6 py-3 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Enregistrer les paramètres</span>
            </button>
        </div>
    </form>
</main>

<script>
function togglePasswordVisibility(id, btn) {
    const input = document.getElementById(id);
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fa-regular fa-eye-slash text-sm';
    } else {
        input.type = 'password';
        icon.className = 'fa-regular fa-eye text-sm';
    }
}

async function testAiConnection(key) {
    const btn = document.getElementById(`btn-test-${key}`);
    const resultBox = document.getElementById(`ai-test-result-${key}`);
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Test en cours...';
    resultBox.innerHTML = '';

    const apiUrl = document.getElementById(`ai_${key}_api_url`).value;
    const apiKey = document.getElementById(`ai_${key}_api_key`).value;
    const model = document.getElementById(`ai_${key}_model`).value;

    try {
        const res = await fetch('/api/test-ai.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ api_url: apiUrl, api_key: apiKey, model: model })
        });
        const data = await res.json();
        if (data.success) {
            resultBox.innerHTML = `<span class="text-emerald-600 font-semibold flex items-center gap-1"><i class="fa-solid fa-circle-check"></i> ${data.message || 'Connexion réussie'} (${data.latency_ms || 0}ms)</span>`;
        } else {
            resultBox.innerHTML = `<span class="text-red-600 font-semibold flex items-center gap-1"><i class="fa-solid fa-triangle-exclamation"></i> ${data.error || 'Échec du test'}</span>`;
        }
    } catch (e) {
        resultBox.innerHTML = `<span class="text-red-600 font-semibold"><i class="fa-solid fa-triangle-exclamation"></i> Erreur réseau</span>`;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-plug"></i> Tester la connexion';
    }
}

async function saveCookies() {
    const btn = document.getElementById('btn-save-cookies');
    const resultBox = document.getElementById('cookies-result');
    const content = document.getElementById('cookies_paste').value;

    if (!content.trim()) {
        resultBox.innerHTML = '<span class="text-red-600 font-semibold">Collez d\'abord le contenu du fichier cookies.txt</span>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';

    try {
        const res = await fetch('/api/save-cookies.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cookies: content })
        });
        const data = await res.json();
        if (data.success) {
            resultBox.innerHTML = `<span class="text-emerald-600 font-semibold flex items-center gap-1"><i class="fa-solid fa-circle-check"></i> ${data.message}</span>`;
            document.getElementById('cookies_paste').value = '';
            setTimeout(() => location.reload(), 1200);
        } else {
            resultBox.innerHTML = `<span class="text-red-600 font-semibold">${data.error || "Échec de l'enregistrement"}</span>`;
        }
    } catch (e) {
        resultBox.innerHTML = '<span class="text-red-600 font-semibold">Erreur réseau</span>';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> Enregistrer les cookies';
    }
}

async function clearCookies() {
    if (!confirm('Supprimer les cookies enregistrés ?')) return;
    try {
        const res = await fetch('/api/save-cookies.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cookies: '' })
        });
        const data = await res.json();
        if (data.success) {
            location.reload();
        }
    } catch (e) {
        alert('Erreur réseau');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
