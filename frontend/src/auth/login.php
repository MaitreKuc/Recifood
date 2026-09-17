<?php
/**
 * Page de Connexion
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

if (isLoggedIn()) {
    header('Location: /pages/dashboard.php');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['registered'])) {
    $success = "Compte créé avec succès ! Vous pouvez maintenant vous connecter.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_or_email = trim($_POST['username_or_email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username_or_email) || empty($password)) {
        $error = "Veuillez renseigner tous les champs.";
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT id, username, email, password_hash, is_admin 
                FROM users 
                WHERE LOWER(username) = LOWER(:val) OR LOWER(email) = LOWER(:val)
                LIMIT 1
            ");
            $stmt->execute([':val' => $username_or_email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Initialisation de la session
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['is_admin'] = (bool)$user['is_admin'];

                header('Location: /pages/dashboard.php');
                exit;
            } else {
                $error = "Identifiants invalides (nom d'utilisateur/email ou mot de passe incorrect).";
            }
        } catch (Exception $e) {
            $error = "Erreur système : " . $e->getMessage();
        }
    }
}

$page_title = "Connexion - Recifood";
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
                    <i class="fa-solid fa-cookie-bite text-2xl"></i>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 font-['Plus_Jakarta_Sans']">Bienvenue sur Recifood</h1>
                <p class="text-sm text-slate-500 mt-1">Connectez-vous pour accéder à vos recettes</p>
            </div>

            <!-- Messages d'erreur ou de succès -->
            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start space-x-3">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-500"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm flex items-start space-x-3">
                    <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <!-- Formulaire de Connexion -->
            <form action="/auth/login.php" method="POST" class="space-y-5">
                <div>
                    <label for="username_or_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Nom d'utilisateur ou Email
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-user text-sm"></i>
                        </div>
                        <input type="text" id="username_or_email" name="username_or_email" required autofocus
                            value="<?= htmlspecialchars($_POST['username_or_email'] ?? '') ?>"
                            class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"
                            placeholder="admin ou admin@recifood.local">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                            Mot de passe
                        </label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-sm"></i>
                        </div>
                        <input type="password" id="password" name="password" required
                            class="block w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"
                            placeholder="••••••••">
                    </div>
                </div>

                <button type="submit"
                    class="w-full py-3 px-4 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white font-semibold rounded-xl shadow-md hover:shadow-lg transition duration-200 flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-arrow-right-to-bracket text-sm"></i>
                    <span>Se connecter</span>
                </button>
            </form>

            <!-- Démo Box -->
            <div class="mt-6 p-3 bg-amber-50/70 border border-amber-200/60 rounded-xl text-xs text-amber-800 flex items-center justify-between">
                <div>
                    <span class="font-bold">Compte Démo :</span> <code>admin</code> / <code>admin123</code>
                </div>
                <button type="button" onclick="fillDemo()" class="text-xs text-brand-600 hover:text-brand-800 font-semibold underline">
                    Remplir
                </button>
            </div>

            <!-- Footer Card -->
            <div class="mt-8 pt-6 border-t border-slate-100 text-center text-sm text-slate-500">
                Vous n'avez pas de compte ?
                <a href="/auth/register.php" class="font-semibold text-brand-600 hover:text-brand-700 ml-1">
                    Créer un compte
                </a>
            </div>
        </div>
    </div>
</main>

<script>
function fillDemo() {
    document.getElementById('username_or_email').value = 'admin';
    document.getElementById('password').value = 'admin123';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
