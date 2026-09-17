<?php
/**
 * Page d'importation de recettes
 * Supporte : URL Web (JSON-LD), Vidéo (yt-dlp + IA), Texte brut (IA), JSON Schema.org direct
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();

$user_id = getCurrentUserId();
$message = '';
$error = '';

// Web Share Target (PWA) : une URL a été partagée depuis une autre application (ex. Instagram)
// et redirigée ici pour être pré-remplie / importée automatiquement.
$shared_url = trim($_GET['shared_url'] ?? '');
$autorun = !empty($_GET['autorun']) && !empty($shared_url);
$share_error = !empty($_GET['share_error']);

$page_title = "Importer une Recette - Recifood";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-slate-900 font-['Plus_Jakarta_Sans']">
            Importer une Recette
        </h1>
        <p class="text-sm text-slate-500 mt-1">
            Convertissez et enregistrez n'importe quelle recette au standard officiel <a href="https://schema.org/Recipe" target="_blank" class="text-brand-600 font-semibold underline">schema.org/Recipe</a>.
        </p>
    </div>

    <?php if ($share_error): ?>
        <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <div>Aucun lien n'a été trouvé dans le contenu partagé. Collez l'adresse manuellement ci-dessous.</div>
        </div>
    <?php endif; ?>

    <?php if ($shared_url): ?>
        <div class="mb-6 p-4 rounded-xl bg-brand-50 border border-brand-200 text-brand-800 text-sm flex items-start gap-3">
            <i class="fa-solid fa-share-nodes mt-0.5"></i>
            <div>Lien reçu via le partage : <strong class="break-all"><?= htmlspecialchars($shared_url) ?></strong> — importation automatique en cours...</div>
        </div>
    <?php endif; ?>

    <!-- Onglets de modes d'importation -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
        <div class="border-b border-slate-200 bg-slate-50/70 p-2 flex flex-wrap gap-2 text-sm" id="import-tabs">
            <button onclick="switchTab('site')" id="tab-site" class="tab-btn px-4 py-2.5 rounded-xl font-semibold transition flex items-center gap-2 bg-white text-brand-600 shadow-sm border border-slate-200">
                <i class="fa-solid fa-globe text-brand-500"></i> Site Web
            </button>
            <button onclick="switchTab('ai')" id="tab-ai" class="tab-btn px-4 py-2.5 rounded-xl font-semibold transition flex items-center gap-2 text-slate-600 hover:bg-white/60">
                <i class="fa-solid fa-wand-magic-sparkles text-amber-500"></i> Texte Libre (IA)
            </button>
            <button onclick="switchTab('json')" id="tab-json" class="tab-btn px-4 py-2.5 rounded-xl font-semibold transition flex items-center gap-2 text-slate-600 hover:bg-white/60">
                <i class="fa-solid fa-code text-emerald-500"></i> JSON Schema.org
            </button>
            <button onclick="switchTab('file')" id="tab-file" class="tab-btn px-4 py-2.5 rounded-xl font-semibold transition flex items-center gap-2 text-slate-600 hover:bg-white/60">
                <i class="fa-solid fa-file-image text-sky-500"></i> Image / PDF
            </button>
        </div>

        <div class="p-6 sm:p-8">
            
            <!-- 1. Onglet Site Web (détection automatique : Schema.org -> Vidéo -> Réseau social) -->
            <div id="panel-site" class="import-panel space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 mb-1 flex items-center gap-2">
                            <span>Importer depuis une adresse Web</span>
                            <span class="text-[10px] bg-brand-100 text-brand-700 px-2 py-0.5 rounded-full font-bold">Détection automatique</span>
                        </h2>
                        <p class="text-xs text-slate-500">
                            Collez n'importe quelle adresse : site de recette (Marmiton, 750g, Allrecipes, blogs...), vidéo
                            (YouTube, TikTok, Vimeo...) ou post de réseau social (Instagram, Facebook, Threads, Pinterest...).
                            Le système détecte automatiquement la meilleure méthode d'extraction : balisage Schema.org en
                            premier (le plus fiable), puis vidéo via yt-dlp, puis analyse du post (légende + photos, y compris
                            quand la recette n'est écrite que sur les images) via l'IA.
                        </p>
                    </div>
                    <a href="/pages/settings.php" class="text-xs text-brand-600 hover:underline font-semibold flex items-center gap-1 shrink-0">
                        <i class="fa-solid fa-gear"></i> Paramètres
                    </a>
                </div>

                <form id="form-site" onsubmit="handleSiteImport(event)" class="space-y-4">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-link text-sm"></i>
                        </div>
                        <input type="url" id="import-site-input" required
                               value="<?= htmlspecialchars($shared_url) ?>"
                               placeholder="https://www.marmiton.org/... ou https://www.youtube.com/... ou https://www.instagram.com/p/..."
                               class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 flex items-center gap-2">
                        <i class="fa-solid fa-info-circle text-brand-500"></i>
                        <span><strong>Attention : Concernant les vidéos de réseaux sociaux, sans description, les ingrédients ne peuvent être détectés correctement et l'IA va halluciner. Il est donc recommandé de la modifier après import pour inclure une description complète.</strong></span>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 flex items-center gap-2">
                        <i class="fa-solid fa-info-circle text-brand-500"></i>
                        <span>Pour les vidéos et les posts de réseaux sociaux, configurez au moins un provider IA (<strong>Texte</strong> et/ou <strong>Vision</strong>) dans les <a href="/pages/settings.php" class="text-brand-600 font-semibold underline">Paramètres</a>.</span>
                    </div>

                    <button type="submit" id="btn-import-site"
                            class="px-6 py-3 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-download text-sm"></i>
                        <span>Analyser & Importer la recette</span>
                    </button>
                </form>
            </div>

            <!-- 2. Onglet Texte Libre (IA) -->
            <div id="panel-ai" class="import-panel hidden space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Extraction IA depuis texte libre</h2>
                    <p class="text-xs text-slate-500">
                        Copiez-collez le texte brut d'un livre de cuisine, d'un email, ou d'une note. L'IA va structurer les ingrédients, temps et étapes au format Schema.org.
                    </p>
                </div>

                <form id="form-ai" onsubmit="handleAiImport(event)" class="space-y-4">
                    <textarea id="import-ai-input" rows="8" required
                              placeholder="Collez ici le texte brut de la recette (ingrédients, préparation, conseils...)..."
                              class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"></textarea>

                    <button type="submit" id="btn-import-ai"
                            class="px-6 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-robot text-sm"></i>
                        <span>Structurer au format Schema.org</span>
                    </button>
                </form>
            </div>

            <!-- 4. Onglet JSON Schema.org direct -->
            <div id="panel-json" class="import-panel hidden space-y-4">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 mb-1">Importation directe JSON Schema.org</h2>
                    <p class="text-xs text-slate-500">
                        Collez un objet JSON-LD conforme à <code>https://schema.org/Recipe</code>.
                    </p>
                </div>

                <form id="form-json" onsubmit="handleJsonImport(event)" class="space-y-4">
                    <textarea id="import-json-input" rows="10" required
                              placeholder='{
  "@context": "https://schema.org",
  "@type": "Recipe",
  "name": "Ma Recette",
  "recipeIngredient": ["200g farine", "2 oeufs"],
  "recipeInstructions": ["Mélanger les ingrédients", "Cuire au four 20min"]
}'
                              class="w-full p-4 bg-slate-900 text-emerald-400 font-mono text-xs border border-slate-800 rounded-xl focus:outline-none focus:ring-2 focus:ring-brand-500 transition"></textarea>

                    <button type="submit" id="btn-import-json"
                            class="px-6 py-3 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-file-import text-sm"></i>
                        <span>Valider & Enregistrer le JSON-LD</span>
                    </button>
                </form>
            </div>

            <!-- 5. Onglet Image / PDF -->
            <div id="panel-file" class="import-panel hidden space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 mb-1 flex items-center gap-2">
                            <span>Importer depuis une image ou un PDF</span>
                            <span class="text-[10px] bg-sky-100 text-sky-700 px-2 py-0.5 rounded-full font-bold">Vision IA</span>
                        </h2>
                        <p class="text-xs text-slate-500">
                            Photo d'une recette, capture d'écran, ou PDF (fiche recette, livre scanné). L'IA Vision configurée dans les paramètres analyse le contenu.
                        </p>
                    </div>
                    <a href="/pages/settings.php" class="text-xs text-brand-600 hover:underline font-semibold flex items-center gap-1">
                        <i class="fa-solid fa-gear"></i> Paramètres
                    </a>
                </div>

                <form id="form-file" onsubmit="handleFileImport(event)" class="space-y-4">
                    <label for="import-file-input"
                           class="flex flex-col items-center justify-center gap-2 w-full py-10 px-4 bg-slate-50 border-2 border-dashed border-slate-300 rounded-xl text-slate-500 hover:bg-slate-100 hover:border-brand-400 transition cursor-pointer text-center">
                        <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400"></i>
                        <span class="text-sm font-semibold" id="file-input-label">Cliquez pour choisir une image ou un PDF</span>
                        <span class="text-[11px] text-slate-400">JPG, PNG, WEBP, GIF, BMP ou PDF — 20 Mo max</span>
                        <input type="file" id="import-file-input" accept="image/*,.pdf" required class="hidden"
                               onchange="document.getElementById('file-input-label').innerText = this.files[0] ? this.files[0].name : 'Cliquez pour choisir une image ou un PDF'">
                    </label>

                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs text-slate-600 flex items-center gap-2">
                        <i class="fa-solid fa-info-circle text-brand-500"></i>
                        <span>Assurez-vous d'avoir configuré l'IA Vision dans la page <a href="/pages/settings.php" class="text-brand-600 font-semibold underline">Paramètres</a>.</span>
                    </div>

                    <button type="submit" id="btn-import-file"
                            class="px-6 py-3 bg-gradient-to-r from-sky-500 to-blue-600 hover:from-sky-600 hover:to-blue-700 text-white font-semibold rounded-xl shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                        <span>Analyser le fichier avec l'IA Vision</span>
                    </button>
                </form>
            </div>

        </div>
    </div>

    <!-- Zone de prévisualisation & Statut -->
    <div id="import-status" class="hidden rounded-2xl p-5 border text-sm"></div>

    <div id="preview-container" class="hidden bg-white rounded-2xl border border-slate-200 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex items-center justify-between pb-4 border-b border-slate-100">
            <h3 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-500"></i>
                <span>Recette extraite avec succès</span>
            </h3>
            <span class="text-xs bg-emerald-100 text-emerald-800 font-bold px-2.5 py-1 rounded-full">
                schema.org/Recipe validé
            </span>
        </div>

        <div id="preview-content" class="space-y-4">
            <!-- Rempli par JavaScript -->
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-slate-100">
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

function switchTab(tabId) {
    // Boutons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.className = "tab-btn px-4 py-2.5 rounded-xl font-semibold transition flex items-center gap-2 text-slate-600 hover:bg-white/60";
    });
    const activeBtn = document.getElementById('tab-' + tabId);
    if (activeBtn) {
        activeBtn.className = "tab-btn px-4 py-2.5 rounded-xl font-semibold transition flex items-center gap-2 bg-white text-brand-600 shadow-sm border border-slate-200";
    }

    // Panels
    document.querySelectorAll('.import-panel').forEach(p => p.classList.add('hidden'));
    const activePanel = document.getElementById('panel-' + tabId);
    if (activePanel) {
        activePanel.classList.remove('hidden');
    }
}

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

// 1. Import Site Web (détection automatique : Schema.org -> Vidéo -> Réseau social)
async function handleSiteImport(e) {
    e.preventDefault();
    const url = document.getElementById('import-site-input').value.trim();
    if (!url) return;

    showStatus('loading', 'Détection automatique en cours (balisage Schema.org, puis vidéo, puis post de réseau social si besoin)... cela peut prendre un peu de temps selon la méthode utilisée.');
    document.getElementById('preview-container').classList.add('hidden');

    const sourceLabels = {
        schema: 'Recette extraite avec succès depuis le balisage Schema.org de la page !',
        video: 'Recette extraite avec succès depuis la vidéo (yt-dlp + IA) !',
        social: 'Recette extraite avec succès depuis le post de réseau social (légende + photos) !'
    };

    try {
        const res = await fetch('/api/import-handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'site_auto', url: url })
        });
        const data = await res.json();
        if (data.success && data.recipe) {
            renderPreview(data.recipe);
            const label = sourceLabels[data.detected_source] || 'Recette extraite avec succès !';
            showStatus('success', label);
        } else {
            showStatus('error', data.error || 'Impossible d\'extraire une recette de cette adresse.');
        }
    } catch (err) {
        showStatus('error', 'Erreur de communication avec le serveur d\'importation.');
    }
}

// 3. Import Texte IA
async function handleAiImport(e) {
    e.preventDefault();
    const text = document.getElementById('import-ai-input').value.trim();
    if (!text) return;

    showStatus('loading', 'Analyse du texte et conversion Schema.org par le provider IA...');
    document.getElementById('preview-container').classList.add('hidden');

    try {
        const res = await fetch('/api/import-handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'text_ai', text: text })
        });
        const data = await res.json();
        if (data.success && data.recipe) {
            renderPreview(data.recipe);
            showStatus('success', 'Texte converti en recette Schema.org !');
        } else {
            showStatus('error', data.error || 'Erreur lors de la structuration par l\'IA.');
        }
    } catch (err) {
        showStatus('error', 'Erreur de connexion avec l\'IA.');
    }
}

// 4. Import JSON direct
async function handleJsonImport(e) {
    e.preventDefault();
    const rawJson = document.getElementById('import-json-input').value.trim();
    if (!rawJson) return;

    try {
        const parsed = JSON.parse(rawJson);
        if (parsed['@type'] !== 'Recipe' && parsed['type'] !== 'Recipe') {
            showStatus('error', 'Le JSON doit contenir "@type": "Recipe" pour être conforme à schema.org.');
            return;
        }
        renderPreview(parsed);
        showStatus('success', 'JSON Schema.org validé ! Cliquez ci-dessous pour confirmer.');
    } catch (err) {
        showStatus('error', 'Format JSON invalide : ' + err.message);
    }
}

// 5. Import Image / PDF
async function handleFileImport(e) {
    e.preventDefault();
    const fileInput = document.getElementById('import-file-input');
    const file = fileInput.files[0];
    if (!file) return;

    showStatus('loading', 'Analyse du fichier par l\'IA Vision en cours (cela peut prendre quelques secondes)...');
    document.getElementById('preview-container').classList.add('hidden');

    const formData = new FormData();
    formData.append('file', file);

    try {
        const res = await fetch('/api/import-file.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success && data.recipe) {
            renderPreview(data.recipe);
            showStatus('success', 'Recette extraite du fichier avec succès !');
        } else {
            showStatus('error', data.error || 'Impossible d\'extraire une recette de ce fichier.');
        }
    } catch (err) {
        showStatus('error', 'Erreur réseau lors de l\'analyse du fichier.');
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
            alert('Erreur : ' + (data.error || 'Impossible d\'enregistrer'));
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-bookmark"></i> Confirmer et Ajouter';
        }
    } catch (e) {
        alert('Erreur réseau lors de l\'enregistrement');
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bookmark"></i> Confirmer et Ajouter';
    }
}

// Web Share Target (PWA) : si une URL a été partagée depuis une autre application
// avec importation automatique demandée, on déclenche directement le formulaire "Site Web".
<?php if ($autorun): ?>
document.addEventListener('DOMContentLoaded', () => {
    switchTab('site');
    const form = document.getElementById('form-site');
    if (form) form.requestSubmit();
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
