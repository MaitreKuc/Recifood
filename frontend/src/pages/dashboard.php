<?php
/**
 * Tableau de bord des recettes
 * Page publique : consultable sans compte. Les actions (favoris, "déjà cuisiné")
 * ne sont proposées qu'aux utilisateurs connectés, et seulement sur leurs propres recettes.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$user_id = getCurrentUserId(); // peut être null si visiteur non connecté
$search = trim($_GET['q'] ?? '');
$filter_category = trim($_GET['category'] ?? '');
$filter_cuisine = trim($_GET['cuisine'] ?? '');
$filter_favorite = isset($_GET['favorite']) && $_GET['favorite'] === '1';
$filter_cooked = isset($_GET['cooked']) && $_GET['cooked'] === '1';

$recipes = [];
$categories = [];
$cuisines = [];
$stats = [
    'total' => 0,
    'favorites' => 0,
    'cooked' => 0,
];

try {
    $db = getDB();

    // Récupérer les catégories et cuisines distinctes pour les filtres (toutes les recettes publiques)
    $stmt_cat = $db->prepare("SELECT DISTINCT recipe_category FROM recipes WHERE recipe_category IS NOT NULL AND recipe_category != '' ORDER BY recipe_category ASC");
    $stmt_cat->execute();
    $categories = $stmt_cat->fetchAll(PDO::FETCH_COLUMN);

    $stmt_cui = $db->prepare("SELECT DISTINCT recipe_cuisine FROM recipes WHERE recipe_cuisine IS NOT NULL AND recipe_cuisine != '' ORDER BY recipe_cuisine ASC");
    $stmt_cui->execute();
    $cuisines = $stmt_cui->fetchAll(PDO::FETCH_COLUMN);

    // Statistiques : total public, favoris/déjà cuisinées personnels à l'utilisateur connecté
    $stmt_stats = $db->prepare("
        SELECT
            (SELECT COUNT(*) FROM recipes) as total,
            (SELECT COUNT(*) FROM user_recipe_status WHERE user_id = :uid AND is_favorite = TRUE) as favorites,
            (SELECT COUNT(*) FROM user_recipe_status WHERE user_id = :uid AND already_cooked = TRUE) as cooked
    ");
    $stmt_stats->execute([':uid' => $user_id ?: 0]);
    $stats = $stmt_stats->fetch() ?: ['total' => 0, 'favorites' => 0, 'cooked' => 0];

    // Construction de la requête filtrée (recettes publiques, tous utilisateurs confondus)
    // Le statut favori/déjà cuisiné est joint pour l'utilisateur actuellement connecté (0 = aucune correspondance possible si visiteur)
    $sql = "
        SELECT r.id, r.user_id, r.name, r.description, r.image_url, r.prep_time, r.cook_time, r.total_time,
               r.recipe_yield, r.recipe_category, r.recipe_cuisine,
               COALESCE(urs.is_favorite, FALSE) as is_favorite,
               COALESCE(urs.already_cooked, FALSE) as already_cooked,
               r.schema_data, r.created_at
        FROM recipes r
        LEFT JOIN user_recipe_status urs ON urs.recipe_id = r.id AND urs.user_id = :current_uid
        WHERE 1=1
    ";
    $params = [':current_uid' => $user_id ?: 0];

    if (!empty($search)) {
        $sql .= " AND (LOWER(r.name) LIKE :q OR LOWER(r.description) LIKE :q OR r.schema_data::text ILIKE :q)";
        $params[':q'] = '%' . strtolower($search) . '%';
    }

    if (!empty($filter_category)) {
        $sql .= " AND r.recipe_category = :category";
        $params[':category'] = $filter_category;
    }

    if (!empty($filter_cuisine)) {
        $sql .= " AND r.recipe_cuisine = :cuisine";
        $params[':cuisine'] = $filter_cuisine;
    }

    if ($filter_favorite) {
        $sql .= " AND urs.is_favorite = TRUE";
    }

    if ($filter_cooked) {
        $sql .= " AND urs.already_cooked = TRUE";
    }

    $sql .= " ORDER BY is_favorite DESC, r.created_at DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();

} catch (Exception $e) {
    $error_msg = $e->getMessage();
}

$page_title = "Mes Recettes - Recifood";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- En-tête du Dashboard -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight font-['Plus_Jakarta_Sans']">
                Mes Recettes de Cuisine
            </h1>
            <p class="text-sm text-slate-500 mt-1 flex items-center gap-2">
                <span><i class="fa-solid fa-layer-group text-brand-500"></i> <?= (int)$stats['total'] ?> recette<?= $stats['total'] > 1 ? 's' : '' ?> enregistrée<?= $stats['total'] > 1 ? 's' : '' ?></span>
                <span>&bull;</span>
                <span><i class="fa-solid fa-heart text-red-500"></i> <?= (int)$stats['favorites'] ?> favori<?= $stats['favorites'] > 1 ? 's' : '' ?></span>
                <span>&bull;</span>
                <span><i class="fa-solid fa-utensils text-emerald-500"></i> <?= (int)$stats['cooked'] ?> déjà cuisinée<?= $stats['cooked'] > 1 ? 's' : '' ?></span>
                <span>&bull;</span>
                <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
                    <i class="fa-solid fa-code mr-1"></i> schema.org/Recipe
                </span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <a href="/pages/import.php" class="inline-flex items-center px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-700 text-sm font-semibold hover:bg-slate-50 shadow-sm transition">
                <i class="fa-solid fa-cloud-arrow-down text-brand-500 mr-2"></i> Importer
            </a>
            <a href="/pages/recipe-new.php" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white text-sm font-semibold shadow-md hover:shadow-lg transition">
                <i class="fa-solid fa-plus mr-2"></i> Nouvelle Recette
            </a>
        </div>
    </div>

    <!-- Barre de Filtres et Recherche -->
    <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200 shadow-sm mb-8 space-y-4">
        <form method="GET" action="/pages/dashboard.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
            <!-- Recherche texte -->
            <div class="lg:col-span-5 relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                    placeholder="Rechercher par titre, ingrédient..."
                    class="block w-full pl-10 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
            </div>

            <!-- Filtre Catégorie -->
            <div class="lg:col-span-3">
                <select name="category" onchange="this.form.submit()"
                    class="block w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition text-slate-700">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Filtre Cuisine -->
            <div class="lg:col-span-2">
                <select name="cuisine" onchange="this.form.submit()"
                    class="block w-full py-2 px-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition text-slate-700">
                    <option value="">Toutes les cuisines</option>
                    <?php foreach ($cuisines as $cui): ?>
                        <option value="<?= htmlspecialchars($cui) ?>" <?= $filter_cuisine === $cui ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cui) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Bouton Filtrer / Reset -->
            <div class="lg:col-span-2 flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-3 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-sm font-medium transition shadow-sm">
                    Filtrer
                </button>
                <?php if (!empty($search) || !empty($filter_category) || !empty($filter_cuisine) || $filter_favorite || $filter_cooked): ?>
                    <a href="/pages/dashboard.php" title="Réinitialiser les filtres" class="p-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-sm transition">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Filtres rapides sous forme de pills -->
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 text-xs">
            <span class="text-slate-400 font-medium mr-1">Filtres rapides :</span>
            <a href="/pages/dashboard.php" class="px-3 py-1.5 rounded-lg border <?= empty($filter_category) && !$filter_favorite && !$filter_cooked ? 'bg-brand-50 border-brand-200 text-brand-700 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                Toutes
            </a>
            <?php if (isLoggedIn()): ?>
            <a href="/pages/dashboard.php?favorite=1" class="px-3 py-1.5 rounded-lg border inline-flex items-center gap-1.5 <?= $filter_favorite ? 'bg-red-50 border-red-200 text-red-600 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-heart text-red-500"></i> Coups de cœur
            </a>
            <a href="/pages/dashboard.php?cooked=1" class="px-3 py-1.5 rounded-lg border inline-flex items-center gap-1.5 <?= $filter_cooked ? 'bg-emerald-50 border-emerald-200 text-emerald-700 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                <i class="fa-solid fa-utensils text-emerald-500"></i> J'ai déjà cuisiné
            </a>
            <?php endif; ?>
            <?php foreach (array_slice($categories, 0, 4) as $quick_cat): ?>
                <a href="/pages/dashboard.php?category=<?= urlencode($quick_cat) ?>" class="px-3 py-1.5 rounded-lg border <?= $filter_category === $quick_cat ? 'bg-brand-50 border-brand-200 text-brand-700 font-semibold' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100' ?>">
                    <?= htmlspecialchars($quick_cat) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Grille des Recettes -->
    <?php if (empty($recipes)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center max-w-xl mx-auto shadow-sm my-8">
            <div class="w-20 h-20 bg-brand-50 text-brand-500 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                <i class="fa-solid fa-utensils"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Aucune recette trouvée</h3>
            <p class="text-sm text-slate-500 mt-1 mb-6">
                <?= (!empty($search) || !empty($filter_category) || $filter_favorite || $filter_cooked) ? 'Essayez de modifier ou de réinitialiser vos critères de recherche.' : 'Commencez par ajouter ou importer votre première recette !' ?>
            </p>
            <div class="flex justify-center gap-3">
                <?php if (isLoggedIn()): ?>
                    <a href="/pages/import.php" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl shadow transition">
                        <i class="fa-solid fa-cloud-arrow-down mr-1.5"></i> Importer une recette
                    </a>
                    <a href="/pages/recipe-new.php" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition">
                        <i class="fa-solid fa-pen-to-square mr-1.5"></i> Créer manuellement
                    </a>
                <?php else: ?>
                    <a href="/auth/login.php" class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white text-sm font-semibold rounded-xl shadow transition">
                        <i class="fa-solid fa-right-to-bracket mr-1.5"></i> Connectez-vous pour ajouter des recettes
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($recipes as $recipe): 
                $schema = json_decode($recipe['schema_data'], true) ?: [];
                $rating = $schema['aggregateRating']['ratingValue'] ?? null;
                $review_count = $schema['aggregateRating']['reviewCount'] ?? null;
                $ingredients_count = is_array($schema['recipeIngredient'] ?? null) ? count($schema['recipeIngredient']) : 0;
                // Favori / déjà cuisiné : statut personnel, accessible à tout utilisateur connecté (peu importe le créateur)
                $can_toggle = isLoggedIn();
            ?>
                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col card-hover relative group">
                    <!-- Image et Badges -->
                    <div class="relative h-48 sm:h-52 bg-slate-100 overflow-hidden">
                        <?php if (!empty($recipe['image_url'])): ?>
                            <img src="<?= htmlspecialchars($recipe['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($recipe['name']) ?>"
                                 loading="lazy"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-amber-50 to-orange-100 text-brand-300">
                                <i class="fa-solid fa-bowl-food text-5xl"></i>
                            </div>
                        <?php endif; ?>

                        <?php if ($can_toggle): ?>
                            <!-- Bouton Favori flottant (statut personnel de l'utilisateur connecté) -->
                            <button onclick="toggleFavorite(<?= (int)$recipe['id'] ?>, this, event)"
                                    title="<?= $recipe['is_favorite'] ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"
                                    class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md shadow-md flex items-center justify-center text-sm transition hover:scale-110 <?= $recipe['is_favorite'] ? 'text-red-500' : 'text-slate-400 hover:text-red-500' ?>">
                                <i class="<?= $recipe['is_favorite'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
                            </button>

                            <!-- Bouton "Déjà cuisiné" flottant (statut personnel de l'utilisateur connecté) -->
                            <button onclick="toggleCooked(<?= (int)$recipe['id'] ?>, this, event)"
                                    title="<?= $recipe['already_cooked'] ? 'Marquer comme non cuisinée' : "J'ai déjà cuisiné cette recette" ?>"
                                    class="absolute top-3 right-14 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md shadow-md flex items-center justify-center text-sm transition hover:scale-110 <?= $recipe['already_cooked'] ? 'text-emerald-500' : 'text-slate-400 hover:text-emerald-500' ?>">
                                <i class="<?= $recipe['already_cooked'] ? 'fa-solid' : 'fa-regular' ?> fa-circle-check"></i>
                            </button>
                        <?php else: ?>
                            <!-- Badges informatifs en lecture seule (visiteur non connecté) -->
                            <?php if (!empty($recipe['is_favorite'])): ?>
                                <span title="Coup de cœur" class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md shadow-md flex items-center justify-center text-sm text-red-500">
                                    <i class="fa-solid fa-heart"></i>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (!empty($recipe['already_cooked'])): ?>
                            <span class="absolute top-3 left-3 text-[10px] font-bold px-2 py-1 rounded-full bg-emerald-500/90 text-white backdrop-blur-md shadow-md flex items-center gap-1">
                                <i class="fa-solid fa-utensils"></i> Déjà cuisinée
                            </span>
                        <?php endif; ?>

                        <!-- Catégorie & Cuisine Tags -->
                        <div class="absolute bottom-3 left-3 flex flex-wrap gap-1.5">
                            <?php if (!empty($recipe['recipe_category'])): ?>
                                <span class="bg-slate-900/80 backdrop-blur-md text-white text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                                    <?= htmlspecialchars($recipe['recipe_category']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($recipe['recipe_cuisine'])): ?>
                                <span class="bg-brand-500/90 backdrop-blur-md text-white text-[11px] font-semibold px-2.5 py-1 rounded-lg">
                                    <?= htmlspecialchars($recipe['recipe_cuisine']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contenu de la carte -->
                    <div class="p-5 flex-1 flex flex-col justify-between">
                        <div>
                            <!-- Rating si présent -->
                            <?php if ($rating): ?>
                                <div class="flex items-center space-x-1 text-amber-500 text-xs mb-1.5 font-medium">
                                    <i class="fa-solid fa-star text-[11px]"></i>
                                    <span class="text-slate-700 font-semibold"><?= htmlspecialchars((string)$rating) ?></span>
                                    <?php if ($review_count): ?>
                                        <span class="text-slate-400">(<?= htmlspecialchars((string)$review_count) ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Titre -->
                            <h2 class="text-lg font-bold text-slate-900 group-hover:text-brand-600 transition line-clamp-1 font-['Plus_Jakarta_Sans']">
                                <a href="/pages/recipe-detail.php?id=<?= (int)$recipe['id'] ?>">
                                    <?= htmlspecialchars($recipe['name']) ?>
                                </a>
                            </h2>

                            <!-- Description -->
                            <p class="text-xs text-slate-500 mt-1.5 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($recipe['description'] ?? 'Aucune description disponible.') ?>
                            </p>
                        </div>

                        <!-- Métriques de la recette (Prépa, Cuisson, Ingrédients, Portions) -->
                        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
                            <div class="flex items-center gap-3">
                                <span title="Temps total de préparation et cuisson" class="flex items-center gap-1 font-medium text-slate-700">
                                    <i class="fa-regular fa-clock text-brand-500"></i>
                                    <?= htmlspecialchars(formatIsoDuration($recipe['total_time'] ?: $recipe['prep_time'])) ?>
                                </span>
                                <?php if (!empty($recipe['recipe_yield'])): ?>
                                    <span title="Portions" class="flex items-center gap-1 text-slate-500">
                                        <i class="fa-solid fa-user-group text-slate-400"></i>
                                        <?= htmlspecialchars($recipe['recipe_yield']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <a href="/pages/recipe-detail.php?id=<?= (int)$recipe['id'] ?>" class="text-brand-600 hover:text-brand-700 font-semibold flex items-center gap-1">
                                Voir <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script>
async function toggleFavorite(recipeId, btn, event) {
    event.preventDefault();
    event.stopPropagation();
    try {
        const res = await fetch('/api/toggle-favorite.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recipe_id: recipeId })
        });
        const data = await res.json();
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.is_favorite) {
                btn.classList.remove('text-slate-400');
                btn.classList.add('text-red-500');
                icon.className = 'fa-solid fa-heart';
            } else {
                btn.classList.remove('text-red-500');
                btn.classList.add('text-slate-400');
                icon.className = 'fa-regular fa-heart';
            }
        }
    } catch (e) {
        console.error('Erreur toggle favori', e);
    }
}

async function toggleCooked(recipeId, btn, event) {
    event.preventDefault();
    event.stopPropagation();
    try {
        const res = await fetch('/api/toggle-cooked.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recipe_id: recipeId })
        });
        const data = await res.json();
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.already_cooked) {
                btn.classList.remove('text-slate-400');
                btn.classList.add('text-emerald-500');
                icon.className = 'fa-solid fa-circle-check';
                btn.title = 'Marquer comme non cuisinée';
            } else {
                btn.classList.remove('text-emerald-500');
                btn.classList.add('text-slate-400');
                icon.className = 'fa-regular fa-circle-check';
                btn.title = "J'ai déjà cuisiné cette recette";
            }
        }
    } catch (e) {
        console.error('Erreur toggle déjà cuisiné', e);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
