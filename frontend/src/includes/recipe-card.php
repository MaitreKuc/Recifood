<?php
$schema = json_decode($recipe['schema_data'], true) ?: [];
$can_toggle = isLoggedIn();
?>
<a href="/pages/recipe-detail.php?id=<?= (int)$recipe['id'] ?>" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col card-hover relative group cursor-pointer">
    <div class="relative h-48 sm:h-52 bg-slate-100 overflow-hidden">
        <?php if (!empty($recipe['image_url'])): ?>
            <img src="<?= htmlspecialchars($recipe['image_url']) ?>"
                 alt="<?= htmlspecialchars($recipe['name']) ?>"
                 loading="lazy"
                 decoding="async"
                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
        <?php else: ?>
            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-amber-50 to-orange-100 text-brand-300">
                <i class="fa-solid fa-bowl-food text-5xl"></i>
            </div>
        <?php endif; ?>

        <?php if ($can_toggle): ?>
            <button onclick="toggleFavorite(<?= (int)$recipe['id'] ?>, this, event)"
                    title="<?= $recipe['is_favorite'] ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>"
                    class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md shadow-md flex items-center justify-center text-sm transition hover:scale-110 <?= $recipe['is_favorite'] ? 'text-red-500' : 'text-slate-400 hover:text-red-500' ?>">
                <i class="<?= $recipe['is_favorite'] ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i>
            </button>
            <button onclick="toggleCooked(<?= (int)$recipe['id'] ?>, this, event)"
                    title="<?= $recipe['already_cooked'] ? 'Marquer comme non cuisinée' : "J'ai déjà cuisiné cette recette" ?>"
                    class="absolute top-3 right-14 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md shadow-md flex items-center justify-center text-sm transition hover:scale-110 <?= $recipe['already_cooked'] ? 'text-emerald-500' : 'text-slate-400 hover:text-emerald-500' ?>">
                <i class="<?= $recipe['already_cooked'] ? 'fa-solid' : 'fa-regular' ?> fa-circle-check"></i>
            </button>
        <?php endif; ?>

        <?php if (!empty($recipe['already_cooked'])): ?>
            <span class="absolute top-3 left-3 text-[10px] font-bold px-2 py-1 rounded-full bg-emerald-500/90 text-white backdrop-blur-md shadow-md flex items-center gap-1">
                <i class="fa-solid fa-utensils"></i> Déjà cuisinée
            </span>
        <?php endif; ?>

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

    <div class="p-5 flex-1 flex flex-col justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-900 group-hover:text-brand-600 transition line-clamp-1 font-['Plus_Jakarta_Sans']">
                <?= htmlspecialchars($recipe['name']) ?>
            </h2>
            <p class="text-xs text-slate-500 mt-1.5 line-clamp-2 leading-relaxed">
                <?= htmlspecialchars($recipe['description'] ?? 'Aucune description disponible.') ?>
            </p>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-600">
            <div class="flex items-center gap-3">
                <?php if (!empty($recipe['total_time']) || !empty($recipe['prep_time'])): ?>
                    <span title="Temps total de préparation et cuisson" class="flex items-center gap-1 font-medium text-slate-700">
                        <i class="fa-regular fa-clock text-brand-500"></i>
                        <?= htmlspecialchars(formatIsoDuration($recipe['total_time'] ?: $recipe['prep_time'])) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($recipe['recipe_yield'])): ?>
                    <span title="Portions" class="flex items-center gap-1 text-slate-500">
                        <i class="fa-solid fa-user-group text-slate-400"></i>
                        <?= htmlspecialchars($recipe['recipe_yield']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <span class="text-brand-600 group-hover:text-brand-700 font-semibold flex items-center gap-1">
                Voir <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </span>
        </div>
    </div>
</a>
