<?php
/**
 * Page d'affichage complet d'une recette (Conforme Schema.org/Recipe)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Page publique : la consultation d'une recette ne nécessite pas de compte.
$user_id = getCurrentUserId();
$recipe_id = (int)($_GET['id'] ?? 0);

if ($recipe_id <= 0) {
    header('Location: /pages/dashboard.php');
    exit;
}

try {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT r.id, r.user_id, r.name, r.description, r.image_url, r.prep_time, r.cook_time, r.total_time,
               r.recipe_yield, r.recipe_category, r.recipe_cuisine,
               COALESCE(urs.is_favorite, FALSE) as is_favorite,
               COALESCE(urs.already_cooked, FALSE) as already_cooked,
               r.source_url, r.schema_data, r.created_at, r.updated_at
        FROM recipes r
        LEFT JOIN user_recipe_status urs ON urs.recipe_id = r.id AND urs.user_id = :current_uid
        WHERE r.id = :id
    ");
    $stmt->execute([':id' => $recipe_id, ':current_uid' => $user_id ?: 0]);
    $recipe = $stmt->fetch();

    if (!$recipe) {
        header('Location: /pages/dashboard.php?error=not_found');
        exit;
    }

    $schema = json_decode($recipe['schema_data'], true) ?: [];

} catch (Exception $e) {
    die("Erreur : " . $e->getMessage());
}

// Favori / déjà cuisiné : statut personnel, accessible à tout utilisateur connecté.
// Modifier / supprimer la recette : réservé au créateur ou à l'administrateur du serveur.
$can_toggle = isLoggedIn();
$can_delete = isLoggedIn() && ((int)$recipe['user_id'] === (int)$user_id || isAdmin());

// Extraction des données Schema.org
$author_name = $schema['author']['name'] ?? ($schema['author'] ?? 'Chef Recifood');
if (is_array($author_name)) $author_name = $author_name['name'] ?? 'Chef';
$date_published = $schema['datePublished'] ?? date('Y-m-d', strtotime($recipe['created_at']));
$keywords = $schema['keywords'] ?? '';
$keywords_list = is_array($keywords) ? $keywords : array_filter(array_map('trim', explode(',', $keywords)));

$ingredients = $schema['recipeIngredient'] ?? [];
$instructions = $schema['recipeInstructions'] ?? [];
$nutrition = $schema['nutrition'] ?? null;
$rating = $schema['aggregateRating'] ?? null;
$video = $schema['video'] ?? null;

$page_title = htmlspecialchars($recipe['name']) . " - Recifood";
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Schema.org JSON-LD officiel injecté dans le head/body -->
<script type="application/ld+json">
<?= json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
</script>

<?php require_once __DIR__ . '/../includes/navbar.php'; ?>

<main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 recipe-container">
    <!-- Fil d'Ariane & Actions retour -->
    <div class="flex items-center justify-between gap-4 mb-6 no-print">
        <a href="/pages/dashboard.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-slate-800 transition">
            <i class="fa-solid fa-arrow-left mr-2"></i> Retour aux recettes
        </a>

        <!-- Toolbar d'actions -->
        <div class="flex items-center space-x-2">
            <?php if ($can_toggle): ?>
            <button onclick="toggleFavorite(<?= (int)$recipe['id'] ?>, this)" 
                    id="favorite-btn"
                    title="<?= $recipe['is_favorite'] ? 'Retirer des favoris' : 'Mettre en favoris' ?>"
                    class="p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 shadow-sm transition <?= $recipe['is_favorite'] ? 'text-red-500 border-red-200 bg-red-50' : 'text-slate-500' ?>">
                <i class="<?= $recipe['is_favorite'] ? 'fa-solid' : 'fa-regular' ?> fa-heart text-base"></i>
            </button>

            <button onclick="toggleCooked(<?= (int)$recipe['id'] ?>, this)" 
                    id="cooked-btn"
                    title="<?= $recipe['already_cooked'] ? 'Marquer comme non cuisinée' : "J'ai déjà cuisiné cette recette" ?>"
                    class="p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 shadow-sm transition flex items-center gap-2 <?= $recipe['already_cooked'] ? 'text-emerald-600 border-emerald-200 bg-emerald-50' : 'text-slate-500' ?>">
                <i class="<?= $recipe['already_cooked'] ? 'fa-solid' : 'fa-regular' ?> fa-circle-check text-base"></i>
                <span id="cooked-btn-label" class="text-sm font-semibold hidden sm:inline"><?= $recipe['already_cooked'] ? 'Déjà cuisinée' : "J'ai déjà cuisiné" ?></span>
            </button>
            <?php else: ?>
            <a href="/auth/login.php" title="Connectez-vous pour mettre en favoris ou marquer comme déjà cuisinée" class="p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 shadow-sm text-slate-400 transition">
                <i class="fa-regular fa-heart text-base"></i>
            </a>
            <?php endif; ?>

            <button onclick="window.print()" title="Imprimer la fiche recette" class="p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 shadow-sm text-slate-600 transition">
                <i class="fa-solid fa-print text-base"></i>
            </button>

            <a href="/api/export-jsonld.php?id=<?= (int)$recipe['id'] ?>" download="recette-<?= (int)$recipe['id'] ?>.json" title="Exporter en Schema.org JSON-LD" class="p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 shadow-sm text-brand-600 transition">
                <i class="fa-solid fa-file-code text-base"></i>
            </a>

            <button onclick="shareRecipe(this)" title="Partager le lien de la recette" class="p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 shadow-sm text-brand-600 transition">
                <i class="fa-solid fa-share-nodes text-base"></i>
            </button>

            <?php if ($can_delete): ?>
            <a href="/pages/recipe-edit.php?id=<?= (int)$recipe['id'] ?>" title="Modifier la recette" class="p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 shadow-sm transition">
                <i class="fa-solid fa-pen text-base"></i>
            </a>
            <button onclick="deleteRecipe(<?= (int)$recipe['id'] ?>)" title="Supprimer la recette" class="p-2.5 rounded-xl border border-red-200 bg-white hover:bg-red-50 text-red-600 shadow-sm transition">
                <i class="fa-solid fa-trash-can text-base"></i>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Conteneur principal de la Recette -->
    <article class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden p-6 sm:p-10 space-y-8">
        
        <!-- En-tête : Catégories, Titre, Description -->
        <div class="space-y-4">
            <div class="flex flex-wrap items-center gap-2">
                <?php if (!empty($recipe['recipe_category'])): ?>
                    <span class="px-3 py-1 bg-brand-50 text-brand-700 font-semibold text-xs rounded-lg border border-brand-200">
                        <i class="fa-solid fa-utensils mr-1 text-[10px]"></i> <?= htmlspecialchars($recipe['recipe_category']) ?>
                    </span>
                <?php endif; ?>

                <?php if (!empty($recipe['recipe_cuisine'])): ?>
                    <span class="px-3 py-1 bg-amber-50 text-amber-700 font-semibold text-xs rounded-lg border border-amber-200">
                        <i class="fa-solid fa-earth-americas mr-1 text-[10px]"></i> <?= htmlspecialchars($recipe['recipe_cuisine']) ?>
                    </span>
                <?php endif; ?>

                <span class="px-3 py-1 bg-emerald-50 text-emerald-700 font-semibold text-xs rounded-lg border border-emerald-200 inline-flex items-center">
                    <i class="fa-solid fa-shield-check mr-1 text-[10px]"></i> Schema.org Recipe
                </span>
            </div>

            <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight font-['Plus_Jakarta_Sans']">
                <?= htmlspecialchars($recipe['name']) ?>
            </h1>

            <?php if (!empty($recipe['description'])): ?>
                <p class="text-slate-600 text-base leading-relaxed sm:text-lg">
                    <?= nl2br(htmlspecialchars($recipe['description'])) ?>
                </p>
            <?php endif; ?>

            <!-- Auteur, Date, Rating -->
            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400 pt-2 border-t border-slate-100">
                <span class="flex items-center gap-1.5 text-slate-600">
                    <i class="fa-solid fa-user-pen text-slate-400"></i>
                    Par <strong><?= htmlspecialchars((string)$author_name) ?></strong>
                </span>
                <span>&bull;</span>
                <span class="flex items-center gap-1.5 text-slate-500">
                    <i class="fa-regular fa-calendar text-slate-400"></i>
                    <?= htmlspecialchars((string)$date_published) ?>
                </span>
                <?php if ($rating && !empty($rating['ratingValue'])): ?>
                    <span>&bull;</span>
                    <span class="flex items-center gap-1 text-amber-500 font-bold">
                        <i class="fa-solid fa-star"></i>
                        <?= htmlspecialchars((string)$rating['ratingValue']) ?> / 5
                        <?php if (!empty($rating['reviewCount'])): ?>
                            <span class="text-slate-400 font-normal">(<?= htmlspecialchars((string)$rating['reviewCount']) ?> avis)</span>
                        <?php endif; ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Grande Image Hero de la recette -->
        <?php if (!empty($recipe['image_url'])): ?>
            <div class="rounded-2xl overflow-hidden max-h-[440px] shadow-sm border border-slate-100">
                <img src="<?= htmlspecialchars($recipe['image_url']) ?>" 
                     alt="<?= htmlspecialchars($recipe['name']) ?>" 
                     class="w-full h-full object-cover">
            </div>
        <?php endif; ?>

        <!-- Métriques Clés de cuisson (Barre d'informations) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-5 bg-slate-50 rounded-2xl border border-slate-200/80 text-center">
            <div class="p-2">
                <span class="block text-xs uppercase font-semibold text-slate-400 tracking-wider">Préparation</span>
                <span class="text-lg sm:text-xl font-bold text-slate-800 flex items-center justify-center gap-1.5 mt-1">
                    <i class="fa-regular fa-clock text-brand-500 text-sm"></i>
                    <?= htmlspecialchars(formatIsoDuration($recipe['prep_time'])) ?>
                </span>
            </div>
            <div class="p-2 border-l border-slate-200/60">
                <span class="block text-xs uppercase font-semibold text-slate-400 tracking-wider">Cuisson</span>
                <span class="text-lg sm:text-xl font-bold text-slate-800 flex items-center justify-center gap-1.5 mt-1">
                    <i class="fa-solid fa-fire-burner text-amber-500 text-sm"></i>
                    <?= htmlspecialchars(formatIsoDuration($recipe['cook_time'])) ?>
                </span>
            </div>
            <div class="p-2 border-t sm:border-t-0 sm:border-l border-slate-200/60">
                <span class="block text-xs uppercase font-semibold text-slate-400 tracking-wider">Temps Total</span>
                <span class="text-lg sm:text-xl font-bold text-slate-800 flex items-center justify-center gap-1.5 mt-1">
                    <i class="fa-solid fa-hourglass-half text-brand-600 text-sm"></i>
                    <?= htmlspecialchars(formatIsoDuration($recipe['total_time'] ?: $recipe['prep_time'])) ?>
                </span>
            </div>
            <div class="p-2 border-t sm:border-t-0 border-l border-slate-200/60">
                <span class="block text-xs uppercase font-semibold text-slate-400 tracking-wider">Portions</span>
                <span class="text-lg sm:text-xl font-bold text-slate-800 flex items-center justify-center gap-1.5 mt-1">
                    <i class="fa-solid fa-users text-slate-500 text-sm"></i>
                    <?= htmlspecialchars($recipe['recipe_yield'] ?: 'N/C') ?>
                </span>
            </div>
        </div>

        <!-- Corps de la recette : Ingrédients (1/3) & Étapes (2/3) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 pt-4">
            
            <!-- Colonne de gauche : Ingrédients -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-amber-50/50 rounded-2xl p-6 border border-amber-200/50">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-xl font-bold text-slate-900 font-['Plus_Jakarta_Sans'] flex items-center gap-2">
                            <i class="fa-solid fa-basket-shopping text-brand-500"></i> Ingrédients
                        </h2>
                        <span class="text-xs font-semibold px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full">
                            <?= count($ingredients) ?> éléments
                        </span>
                    </div>

                    <?php if (empty($ingredients)): ?>
                        <p class="text-sm text-slate-500 italic">Aucun ingrédient listé.</p>
                    <?php else: ?>
                        <ul class="space-y-3">
                            <?php foreach ($ingredients as $idx => $ing): ?>
                                <li class="flex items-start gap-3 text-sm text-slate-700">
                                    <input type="checkbox" id="ing-<?= $idx ?>" 
                                           class="ingredient-checkbox mt-1 rounded border-slate-300 text-brand-500 focus:ring-brand-400 cursor-pointer w-4 h-4">
                                    <label for="ing-<?= $idx ?>" class="cursor-pointer select-none leading-snug">
                                        <span><?= htmlspecialchars(is_array($ing) ? ($ing['name'] ?? json_encode($ing)) : $ing) ?></span>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>

                <!-- Informations Nutritionnelles (Schema.org/NutritionInformation) -->
                <?php if ($nutrition && is_array($nutrition)): ?>
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200 text-xs">
                        <h3 class="font-bold text-slate-800 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <i class="fa-solid fa-heart-pulse text-red-500"></i> Valeurs Nutritionnelles
                        </h3>
                        <div class="grid grid-cols-2 gap-2 text-slate-600">
                            <?php if (!empty($nutrition['calories'])): ?>
                                <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-2xs">
                                    <span class="text-slate-400 block">Calories</span>
                                    <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars((string)$nutrition['calories']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($nutrition['proteinContent'])): ?>
                                <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-2xs">
                                    <span class="text-slate-400 block">Protéines</span>
                                    <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars((string)$nutrition['proteinContent']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($nutrition['carbohydrateContent'])): ?>
                                <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-2xs">
                                    <span class="text-slate-400 block">Glucides</span>
                                    <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars((string)$nutrition['carbohydrateContent']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($nutrition['fatContent'])): ?>
                                <div class="bg-white p-2.5 rounded-xl border border-slate-100 shadow-2xs">
                                    <span class="text-slate-400 block">Lipides</span>
                                    <span class="font-bold text-slate-800 text-sm"><?= htmlspecialchars((string)$nutrition['fatContent']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Mots-clés / Tags -->
                <?php if (!empty($keywords_list)): ?>
                    <div class="pt-2">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Mots-clés</span>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($keywords_list as $kw): ?>
                                <span class="badge-tag bg-slate-100 text-slate-600 border border-slate-200">
                                    #<?= htmlspecialchars($kw) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Colonne de droite : Instructions / Étapes -->
            <div class="lg:col-span-7 space-y-6">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 font-['Plus_Jakarta_Sans'] flex items-center gap-2 mb-4">
                        <i class="fa-solid fa-list-check text-brand-500"></i> Instructions de Préparation
                    </h2>

                    <?php if (empty($instructions)): ?>
                        <p class="text-sm text-slate-500 italic">Aucune instruction spécifiée.</p>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php 
                            $step_num = 1;
                            foreach ($instructions as $inst): 
                                $step_title = '';
                                $step_text = '';
                                if (is_array($inst)) {
                                    $step_title = $inst['name'] ?? '';
                                    $step_text = $inst['text'] ?? ($inst['description'] ?? '');
                                } else {
                                    $step_text = (string)$inst;
                                }
                            ?>
                                <div class="flex items-start gap-4 p-4 rounded-2xl bg-slate-50 hover:bg-slate-100/70 border border-slate-200/80 transition">
                                    <input type="checkbox" id="step-<?= $step_num ?>" class="step-checkbox mt-1.5 rounded text-brand-500 focus:ring-brand-400 cursor-pointer w-4 h-4">
                                    
                                    <div class="flex-1 space-y-1">
                                        <div class="flex items-center justify-between">
                                            <span class="text-xs font-bold uppercase tracking-wider text-brand-600">
                                                Étape <?= $step_num ?> <?= !empty($step_title) ? '&mdash; ' . htmlspecialchars($step_title) : '' ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-700 leading-relaxed">
                                            <?= nl2br(htmlspecialchars($step_text)) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php 
                                $step_num++;
                            endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Accordéon pour voir les données brutes Schema.org JSON-LD -->
                <div class="pt-6 border-t border-slate-100 no-print">
                    <details class="bg-slate-900 text-slate-200 rounded-2xl p-4 cursor-pointer text-xs group">
                        <summary class="font-bold flex items-center justify-between select-none text-slate-300 hover:text-white">
                            <span class="flex items-center gap-2">
                                <i class="fa-solid fa-code text-brand-400"></i> Données Schema.org/Recipe JSON-LD (Standard Web)
                            </span>
                            <span class="text-[10px] bg-slate-800 px-2 py-0.5 rounded text-slate-400 group-open:rotate-180 transition-transform">
                                <i class="fa-solid fa-chevron-down"></i>
                            </span>
                        </summary>
                        <div class="mt-4 pt-3 border-t border-slate-800">
                            <pre class="overflow-x-auto p-3 bg-black/40 rounded-xl font-mono text-[11px] text-emerald-400 leading-relaxed"><?= htmlspecialchars(json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></pre>
                        </div>
                    </details>
                </div>
            </div>
        </div>

    </article>
</main>

<script>
async function toggleFavorite(recipeId, btn) {
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
                btn.className = "p-2.5 rounded-xl border border-red-200 bg-red-50 text-red-500 shadow-sm transition";
                icon.className = 'fa-solid fa-heart text-base';
            } else {
                btn.className = "p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 shadow-sm transition";
                icon.className = 'fa-regular fa-heart text-base';
            }
        }
    } catch (e) {
        console.error('Erreur toggle favori', e);
    }
}

async function toggleCooked(recipeId, btn) {
    try {
        const res = await fetch('/api/toggle-cooked.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recipe_id: recipeId })
        });
        const data = await res.json();
        if (data.success) {
            const icon = btn.querySelector('i');
            const label = document.getElementById('cooked-btn-label');
            if (data.already_cooked) {
                btn.className = "p-2.5 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600 shadow-sm transition flex items-center gap-2";
                icon.className = 'fa-solid fa-circle-check text-base';
                label.innerText = 'Déjà cuisinée';
                btn.title = 'Marquer comme non cuisinée';
            } else {
                btn.className = "p-2.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-500 shadow-sm transition flex items-center gap-2";
                icon.className = 'fa-regular fa-circle-check text-base';
                label.innerText = "J'ai déjà cuisiné";
                btn.title = "J'ai déjà cuisiné cette recette";
            }
        }
    } catch (e) {
        console.error('Erreur toggle déjà cuisiné', e);
    }
}

async function deleteRecipe(recipeId) {
    if (!confirm("Voulez-vous vraiment supprimer définitivement cette recette ?")) {
        return;
    }
    try {
        const res = await fetch('/api/delete-recipe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recipe_id: recipeId })
        });
        const data = await res.json();
        if (data.success) {
            window.location.href = '/pages/dashboard.php';
        } else {
            alert("Erreur : " + (data.error || 'Impossible de supprimer'));
        }
    } catch (e) {
        alert("Erreur réseau lors de la suppression.");
    }
}

async function shareRecipe(btn) {
    const url = window.location.href;
    const title = document.title;
    if (navigator.share) {
        try {
            await navigator.share({ title, url });
            return;
        } catch (e) {
            // Annulé ou non supporté, on tente le fallback presse-papier
        }
    }
    try {
        await navigator.clipboard.writeText(url);
        const icon = btn.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'fa-solid fa-check text-base';
        btn.classList.add('text-emerald-600', 'border-emerald-200', 'bg-emerald-50');
        setTimeout(() => {
            icon.className = originalClass;
            btn.classList.remove('text-emerald-600', 'border-emerald-200', 'bg-emerald-50');
        }, 1800);
    } catch (e) {
        alert("Lien de la recette : " + url);
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
