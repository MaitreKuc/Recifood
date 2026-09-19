<?php
/**
 * Barre de navigation principale Recifood
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header class="bg-white/95 backdrop-blur-md border-b border-slate-200 sticky top-0 z-40 shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Logo & Nav principale -->
            <div class="flex items-center space-x-8">
                <a href="/pages/dashboard.php" class="flex items-center space-x-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-500 to-amber-500 flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform">
                        <i class="fa-solid fa-utensils text-lg"></i>
                    </div>
                    <div>
                        <span class="text-xl font-bold bg-gradient-to-r from-brand-600 to-amber-600 bg-clip-text text-transparent font-['Plus_Jakarta_Sans']">Recifood</span>
                        <span class="text-[10px] block font-semibold uppercase tracking-wider text-slate-400">By MaitreKuc</span>
                    </div>
                </a>

                <nav class="hidden md:flex space-x-1">
                    <a href="/pages/dashboard.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors <?= ($current_page === 'dashboard.php' || $current_page === 'recipe-detail.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        <i class="fa-solid fa-book-open mr-1.5 text-xs"></i> Recettes
                    </a>
                    <?php if (isLoggedIn()): ?>
                    <a href="/pages/import.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors <?= ($current_page === 'import.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        <i class="fa-solid fa-cloud-arrow-down mr-1.5 text-xs"></i> Importer
                    </a>
                    <a href="/pages/recipe-new.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors <?= ($current_page === 'recipe-new.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        <i class="fa-solid fa-plus-circle mr-1.5 text-xs"></i> Nouvelle Recette
                    </a>
                    <a href="/pages/ai-imagine.php" class="px-3.5 py-2 rounded-lg text-sm font-medium transition-colors <?= ($current_page === 'ai-imagine.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100' ?>">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1.5 text-xs"></i> IA — Imagine
                    </a>
                    <?php endif; ?>
                </nav>
            </div>

            <!-- Actions Utilisateur -->
            <div class="flex items-center space-x-3">
                <?php if (isLoggedIn()): ?>
                    <a href="/pages/settings.php" title="Paramètres IA & yt-dlp" class="p-2.5 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors <?= ($current_page === 'settings.php') ? 'bg-slate-100 text-brand-600' : '' ?>">
                        <i class="fa-solid fa-gear text-lg"></i>
                    </a>

                    <div class="h-6 w-px bg-slate-200 hidden sm:block"></div>

                    <div class="hidden sm:flex items-center space-x-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-700 font-semibold text-xs flex items-center justify-center border border-brand-200 uppercase">
                                <?= strtoupper(substr(getCurrentUsername(), 0, 2)) ?>
                            </div>
                            <span class="text-sm font-medium text-slate-700 hidden sm:inline-block">
                                <?= htmlspecialchars(getCurrentUsername()) ?>
                            </span>
                        </div>
                        <a href="/auth/logout.php" title="Se déconnecter" class="text-xs px-3 py-1.5 rounded-lg text-red-600 hover:bg-red-50 border border-red-200 font-medium transition-colors">
                            <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Quitter
                        </a>
                    </div>
                <?php else: ?>
                    <a href="/auth/login.php" class="text-sm font-medium text-slate-700 hover:text-brand-600 px-3 py-2 hidden sm:inline-block">
                        Connexion
                    </a>
                    <a href="/auth/register.php" class="text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 px-4 py-2 rounded-lg shadow-sm transition-colors hidden sm:inline-block">
                        Créer un compte
                    </a>
                <?php endif; ?>

                <!-- Bouton menu mobile (hamburger) -->
                <button type="button" id="mobile-menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false"
                        class="md:hidden p-2.5 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors">
                    <i class="fa-solid fa-bars text-lg" id="mobile-menu-icon-open"></i>
                    <i class="fa-solid fa-xmark text-lg hidden" id="mobile-menu-icon-close"></i>
                </button>
            </div>
        </div>

        <!-- Menu mobile déroulant -->
        <nav id="mobile-menu" class="hidden md:hidden pb-4 space-y-1 border-t border-slate-100 pt-3">
            <a href="/pages/dashboard.php" class="block px-3.5 py-2.5 rounded-lg text-sm font-medium <?= ($current_page === 'dashboard.php' || $current_page === 'recipe-detail.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-book-open mr-2 text-xs w-4 inline-block"></i> Recettes
            </a>
            <?php if (isLoggedIn()): ?>
            <a href="/pages/import.php" class="block px-3.5 py-2.5 rounded-lg text-sm font-medium <?= ($current_page === 'import.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-cloud-arrow-down mr-2 text-xs w-4 inline-block"></i> Importer
            </a>
            <a href="/pages/recipe-new.php" class="block px-3.5 py-2.5 rounded-lg text-sm font-medium <?= ($current_page === 'recipe-new.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-plus-circle mr-2 text-xs w-4 inline-block"></i> Nouvelle Recette
            </a>
            <a href="/pages/ai-imagine.php" class="block px-3.5 py-2.5 rounded-lg text-sm font-medium <?= ($current_page === 'ai-imagine.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-wand-magic-sparkles mr-2 text-xs w-4 inline-block"></i> IA — Imagine
            </a>
            <a href="/pages/settings.php" class="block px-3.5 py-2.5 rounded-lg text-sm font-medium <?= ($current_page === 'settings.php') ? 'bg-brand-50 text-brand-600 font-semibold' : 'text-slate-600 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-gear mr-2 text-xs w-4 inline-block"></i> Paramètres
            </a>
            <div class="pt-2 mt-2 border-t border-slate-100 flex items-center justify-between px-3.5">
                <span class="text-sm font-medium text-slate-700 flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full bg-brand-100 text-brand-700 font-semibold text-xs flex items-center justify-center border border-brand-200 uppercase"><?= strtoupper(substr(getCurrentUsername(), 0, 2)) ?></span>
                    <?= htmlspecialchars(getCurrentUsername()) ?>
                </span>
                <a href="/auth/logout.php" class="text-xs px-3 py-1.5 rounded-lg text-red-600 hover:bg-red-50 border border-red-200 font-medium transition-colors">
                    <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> Quitter
                </a>
            </div>
            <?php else: ?>
            <a href="/auth/login.php" class="block px-3.5 py-2.5 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-100">
                Connexion
            </a>
            <a href="/auth/register.php" class="block px-3.5 py-2.5 rounded-lg text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 text-center">
                Créer un compte
            </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<script>
(function () {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const menu = document.getElementById('mobile-menu');
    const iconOpen = document.getElementById('mobile-menu-icon-open');
    const iconClose = document.getElementById('mobile-menu-icon-close');
    if (!toggleBtn || !menu) return;

    toggleBtn.addEventListener('click', function () {
        const isHidden = menu.classList.toggle('hidden');
        toggleBtn.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        iconOpen.classList.toggle('hidden', !isHidden);
        iconClose.classList.toggle('hidden', isHidden);
    });
})();
</script>
