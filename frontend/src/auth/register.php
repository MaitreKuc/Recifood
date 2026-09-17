<?php
/**
 * Page d'Inscription
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Veuillez renseigner tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format d'adresse email invalide.";
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = "Le nom d'utilisateur doit comporter entre 3 et 50 caractères.";
    } elseif (strlen($password) < 6) {
        $error = "Le mot de passe doit comporter au moins 6 caractères.";
    } elseif ($password !== $password_confirm) {
        $error = "Les deux mots de passe ne correspondent pas.";
    } else {
        try {
            $db = getDB();

            // Vérification existence
            $stmt = $db->prepare("SELECT id FROM users WHERE LOWER(username) = LOWER(:u) OR LOWER(email) = LOWER(:e)");
            $stmt->execute([':u' => $username, ':e' => $email]);
            if ($stmt->fetch()) {
                $error = "Ce nom d'utilisateur ou cet email est déjà utilisé.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);

                // Le tout premier compte créé sur le serveur devient automatiquement administrateur
                $stmt_count = $db->query("SELECT COUNT(*) FROM users");
                $is_first_account = ((int)$stmt_count->fetchColumn() === 0);

                $stmt = $db->prepare("
                    INSERT INTO users (username, email, password_hash, is_admin)
                    VALUES (:u, :e, :p, :admin)
                    RETURNING id
                ");
                $stmt->execute([
                    ':u' => $username,
                    ':e' => $email,
                    ':p' => $hash,
                    ':admin' => $is_first_account ? 'true' : 'false',
                ]);
                $new_user_id = $stmt->fetchColumn();

                // Création des paramètres par défaut
                if ($new_user_id) {
                    $stmt_settings = $db->prepare("
                        INSERT INTO user_settings (user_id, ytdlp_path)
                        VALUES (:uid, 'yt-dlp')
                        ON CONFLICT (user_id) DO NOTHING
                    ");
                    $stmt_settings->execute([':uid' => $new_user_id]);
                }

                header('Location: /auth/login.php?registered=1');
                exit;
            }
        } catch (Exception $e) {
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}

$page_title = "Inscription - Recifood";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
    <div class="w-full max-w-md">
        <!-- Card Container -->
        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-8 sm:p-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-gradient-to-tr from-brand-500 to-amber-500 text-white shadow-lg mb-4">
                    <i class="fa-solid fa-user-plus text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 font-['Plus_Jakarta_Sans']">Créer un compte</h1>
                <p class="text-sm text-slate-500 mt-1">Rejoignez Recifood pour gérer vos recettes Schema.org</p>
            </div>

            <!-- Messages d'erreur -->
            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start space-x-3">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-500"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulaire d'Inscription -->
            <form action="/auth/register.php" method="POST" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Nom d'utilisateur
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-user text-sm"></i>
                        </div>
                        <input type="text" id="username" name="username" required
                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                            class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"
                            placeholder="gourmet2026">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Adresse Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-envelope text-sm"></i>
                        </div>
                        <input type="email" id="email" name="email" required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"
                            placeholder="vous@exemple.fr">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Mot de passe (min. 6 caractères)
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input type="password" id="password" name="password" required minlength="6"
                            class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"
                            placeholder="••••••••">
                    </div>
                </div>

                <div>
                    <label for="password_confirm" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Confirmer le mot de passe
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-check-double text-sm"></i>
                        </div>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                            class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit"
                    class="w-full mt-2 py-3 px-4 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition duration-200 flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-user-plus text-sm"></i>
                    <span>S'inscrire</span>
                </button>
            </form>

            <!-- Footer Card -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center text-sm text-slate-500">
                Vous avez déjà un compte ?
                <a href="/auth/login.php" class="font-semibold text-brand-600 hover:text-brand-700 ml-1">
                    Se connecter
                </a>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
