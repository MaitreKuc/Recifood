<?php
/**
 * Page "IA - Imagine" : génère une recette inédite à partir d'un prompt libre
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();

$user_id = getCurrentUserId();

$page_title = "IA - Imagine une Recette - Recifood";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-slate-900 font-['Plus_Jakarta_Sans'] flex items-center gap-3">
            <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i>
            IA — Imagine
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            Décrivez une envie, une contrainte ou une idée : l'IA invente une recette originale au format
            <a href="https://schema.org/Recipe" target="_blank" class="text-brand-600 font-semibold underline">schema.org/Recipe</a>.
        </p>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="p-6 sm:p-8 space-y-4">
            <form id="imagine-form" onsubmit="handleImagine(event)" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Votre idée de recette</label>
                    <textarea id="imagine-prompt-input" rows="4" required placeholder="Ex : Un dessert d'automne sans gluten avec de la courge et de la cannelle, pour 6 personnes..."
                              class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-brand-400 focus:border-brand-400 outline-none text-sm"></textarea>
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 flex items-center gap-2">
                    <i class="fa-solid fa-info-circle text-brand-500"></i>
                    <span>Assurez-vous d'avoir configuré l'IA Texte dans la page <a href="/pages/settings.php" class="text-brand-600 font-semibold underline">Paramètres</a>.</span>
                </div>

                <button type="submit" id="btn-imagine"
                        class="px-6 py-3 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-sparkles text-sm"></i>
                    <span>Imaginer la recette</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Zone de statut & prévisualisation -->
    <div id="import-status" class="hidden rounded-2xl p-5 border text-sm"></div>

    <div id="preview-container" class="hidden bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                <span>Recette imaginée avec succès</span>
            </h3>
            <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-2.5 py-1 rounded-full">
                schema.org/Recipe validé
            </span>
        </div>

        <div id="preview-content" class="space-y-4">
            <!-- Rempli par JavaScript -->
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <button onclick="handleImagine(null, true)" id="btn-regenerate"
                    class="px-6 py-3 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 font-semibold rounded-xl shadow-sm transition flex items-center gap-2">
                <i class="fa-solid fa-rotate"></i>
                <span>Réessayer une variante</span>
            </button>
            <button onclick="saveExtractedRecipe()" id="btn-save-extracted"
                    class="px-6 py-3 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                <i class="fa-solid fa-bookmark"></i>
                <span>Confirmer et Ajouter à mes recettes</span>
            </button>
        </div>
    </div>
</main>

<script>
let extractedRecipeData = null;

function showStatus(type, msg) {
    const box = document.getElementById('import-status');
    box.classList.remove('hidden', 'bg-red-50', 'border-red-200', 'text-red-700', 'bg-blue-50', 'border-blue-200', 'text-blue-700', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-700');

    if (type === 'error') {
        box.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
        box.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-2"></i> ${msg}`;
    } else if (type === 'loading') {
        box.classList.add('bg-blue-50', 'border-blue-200', 'text-blue-700');
        box.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-2"></i> ${msg}`;
    } else {
        box.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-700');
        box.innerHTML = `<i class="fa-solid fa-check mr-2"></i> ${msg}`;
    }
}

async function handleImagine(e, isRegenerate) {
    if (e) e.preventDefault();
    const prompt = document.getElementById('imagine-prompt-input').value.trim();
    if (!prompt) return;

    const btn = document.getElementById(isRegenerate ? 'btn-regenerate' : 'btn-imagine');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Génération...';

    showStatus('loading', "L'IA imagine votre recette, cela peut prendre quelques secondes...");
    if (!isRegenerate) document.getElementById('preview-container').classList.add('hidden');

    try {
        const res = await fetch('/api/import-handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'imagine_ai', prompt: prompt })
        });
        const data = await res.json();
        if (data.success && data.recipe) {
            renderPreview(data.recipe);
            showStatus('success', 'Recette imaginée avec succès ! Vous pouvez vérifier et enregistrer ci-dessous.');
        } else {
            showStatus('error', data.error || "Impossible d'imaginer une recette à partir de ce prompt.");
        }
    } catch (err) {
        showStatus('error', "Erreur de connexion avec l'IA.");
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

function renderPreview(recipe) {
    extractedRecipeData = recipe;
    const container = document.getElementById('preview-container');
    const content = document.getElementById('preview-content');

    const ingredients = recipe.recipeIngredient || [];
    const instructions = recipe.recipeInstructions || [];
    const image = Array.isArray(recipe.image) ? recipe.image[0] : (recipe.image || '');

    let html = `
        <div class="flex flex-col sm:flex-row gap-6 items-start">
            ${image ? `<img src="${image}" class="w-full sm:w-48 h-36 object-cover rounded-xl border border-slate-200">` : ''}
            <div class="space-y-2 flex-1">
                <h4 class="text-2xl font-bold text-slate-900">${recipe.name || 'Sans titre'}</h4>
                <p class="text-sm text-slate-600">${recipe.description || ''}</p>
                <div class="flex flex-wrap gap-2 pt-1 text-xs">
                    ${recipe.recipeCategory ? `<span class="bg-brand-50 text-brand-700 px-2.5 py-1 rounded-md font-medium">${recipe.recipeCategory}</span>` : ''}
                    ${recipe.recipeCuisine ? `<span class="bg-amber-50 text-amber-700 px-2.5 py-1 rounded-md font-medium">${recipe.recipeCuisine}</span>` : ''}
                    ${recipe.recipeYield ? `<span class="bg-slate-100 text-slate-700 px-2.5 py-1 rounded-md font-medium">${recipe.recipeYield}</span>` : ''}
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-slate-100 text-sm">
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h5 class="font-bold text-slate-800 mb-2">Ingrédients (${ingredients.length}) :</h5>
                <ul class="list-disc list-inside space-y-1 text-slate-700 text-xs">
                    ${ingredients.slice(0, 8).map(i => `<li>${typeof i === 'object' ? (i.name || JSON.stringify(i)) : i}</li>`).join('')}
                    ${ingredients.length > 8 ? `<li class="text-slate-400 italic">+ ${ingredients.length - 8} autres...</li>` : ''}
                </ul>
            </div>
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200">
                <h5 class="font-bold text-slate-800 mb-2">Étapes (${instructions.length}) :</h5>
                <ol class="list-decimal list-inside space-y-1 text-slate-700 text-xs">
                    ${instructions.slice(0, 5).map(inst => {
                        const txt = typeof inst === 'object' ? (inst.text || inst.name || '') : inst;
                        return `<li>${txt.substring(0, 80)}${txt.length > 80 ? '...' : ''}</li>`;
                    }).join('')}
                    ${instructions.length > 5 ? `<li class="text-slate-400 italic">+ ${instructions.length - 5} étapes suivantes...</li>` : ''}
                </ol>
            </div>
        </div>
    `;

    content.innerHTML = html;
    container.classList.remove('hidden');
    container.scrollIntoView({ behavior: 'smooth' });
}

async function saveExtractedRecipe() {
    if (!extractedRecipeData) return;
    const btn = document.getElementById('btn-save-extracted');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';

    try {
        const res = await fetch('/api/save-recipe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ recipe: extractedRecipeData })
        });
        const data = await res.json();
        if (data.success && data.recipe_id) {
            window.location.href = '/pages/recipe-detail.php?id=' + data.recipe_id;
        } else {
            alert('Erreur : ' + (data.error || "Impossible d'enregistrer"));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-bookmark"></i> Confirmer et Ajouter';
        }
    } catch (e) {
        alert('Erreur réseau lors de l\'enregistrement');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bookmark"></i> Confirmer et Ajouter';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
