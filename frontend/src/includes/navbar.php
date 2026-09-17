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

                    <div class="h-6 w-px bg-slate-200"></div>

                    <div class="flex items-center space-x-3">
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
                    <a href="/auth/login.php" class="text-sm font-medium text-slate-700 hover:text-brand-600 px-3 py-2">
                        Connexion
                    </a>
                    <a href="/auth/register.php" class="text-sm font-medium text-white bg-brand-500 hover:bg-brand-600 px-4 py-2 rounded-lg shadow-sm transition-colors">
                        Créer un compte
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
