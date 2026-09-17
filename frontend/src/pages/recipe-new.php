<?php
/**
 * Page de création manuelle d'une recette (Génération Schema.org/Recipe)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();

$user_id = getCurrentUserId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $prep_minutes = (int)($_POST['prep_minutes'] ?? 0);
    $cook_minutes = (int)($_POST['cook_minutes'] ?? 0);
    $recipe_yield = trim($_POST['recipe_yield'] ?? '4 personnes');
    $recipe_category = trim($_POST['recipe_category'] ?? 'Plat principal');
    $recipe_cuisine = trim($_POST['recipe_cuisine'] ?? '');
    $author = trim($_POST['author'] ?? getCurrentUsername());
    $keywords = trim($_POST['keywords'] ?? '');

    // Ingrédients (array de lignes)
    $ingredients_raw = $_POST['ingredients'] ?? '';
    $ingredients = array_values(array_filter(array_map('trim', explode("\n", $ingredients_raw))));

    // Instructions (array de lignes)
    $instructions_raw = $_POST['instructions'] ?? '';
    $instruction_lines = array_values(array_filter(array_map('trim', explode("\n", $instructions_raw))));
    $instructions = [];
    foreach ($instruction_lines as $step_text) {
        $instructions[] = [
            '@type' => 'HowToStep',
            'text' => $step_text
        ];
    }

    // Nutrition
    $calories = trim($_POST['calories'] ?? '');
    $nutrition = null;
    if (!empty($calories)) {
        $nutrition = [
            '@type' => 'NutritionInformation',
            'calories' => $calories . (strpos($calories, 'cal') === false ? ' calories' : '')
        ];
    }

    if (empty($name)) {
        $error = "Le titre de la recette est obligatoire.";
    } elseif (empty($ingredients)) {
        $error = "Veuillez spécifier au moins un ingrédient.";
    } elseif (empty($instructions)) {
        $error = "Veuillez spécifier au moins une étape de préparation.";
    } else {
        $prep_iso = minutesToIsoDuration($prep_minutes);
        $cook_iso = minutesToIsoDuration($cook_minutes);
        $total_iso = minutesToIsoDuration($prep_minutes + $cook_minutes);

        // Construction du JSON-LD conforme Schema.org/Recipe
        $schema_data = [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => $name,
            'description' => $description,
            'image' => !empty($image_url) ? [$image_url] : [],
            'author' => [
                '@type' => 'Person',
                'name' => $author
            ],
            'datePublished' => date('Y-m-d'),
            'prepTime' => $prep_iso,
            'cookTime' => $cook_iso,
            'totalTime' => $total_iso,
            'recipeYield' => $recipe_yield,
            'recipeCategory' => $recipe_category,
            'recipeCuisine' => $recipe_cuisine,
            'keywords' => $keywords,
            'recipeIngredient' => $ingredients,
            'recipeInstructions' => $instructions,
        ];

        if ($nutrition) {
            $schema_data['nutrition'] = $nutrition;
        }

        try {
            $db = getDB();
            $stmt = $db->prepare("
                INSERT INTO recipes (
                    user_id, name, description, image_url, prep_time, cook_time, total_time,
                    recipe_yield, recipe_category, recipe_cuisine, schema_data
                ) VALUES (
                    :uid, :name, :desc, :img, :prep, :cook, :total,
                    :yield, :cat, :cui, :schema
                ) RETURNING id
            ");
            $stmt->execute([
                ':uid' => $user_id,
                ':name' => $name,
                ':desc' => $description,
                ':img' => $image_url,
                ':prep' => $prep_iso,
                ':cook' => $cook_iso,
                ':total' => $total_iso,
                ':yield' => $recipe_yield,
                ':cat' => $recipe_category,
                ':cui' => $recipe_cuisine,
                ':schema' => json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ]);
            $new_id = $stmt->fetchColumn();

            header("Location: /pages/recipe-detail.php?id=" . $new_id);
            exit;
        } catch (Exception $e) {
            $error = "Erreur d'enregistrement : " . $e->getMessage();
        }
    }
}

$page_title = "Créer une Recette - Recifood";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 font-['Plus_Jakarta_Sans']">
                Nouvelle Recette
            </h1>
            <p class="text-sm text-slate-500 mt-1">Créez une recette conforme au format schema.org/Recipe</p>
        </div>
        <a href="/pages/dashboard.php" class="text-sm text-slate-500 hover:text-slate-800 font-medium">
            <i class="fa-solid fa-xmark mr-1"></i> Annuler
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start space-x-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-500"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="/pages/recipe-new.php" class="space-y-6">
        <!-- Informations Générales -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
            <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-circle-info text-brand-500"></i> Informations Générales
            </h2>

            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Titre de la recette *
                    </label>
                    <input type="text" name="name" required placeholder="Ex: Gratin Dauphinois Traditionnel"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Description / Histoire de la recette
                    </label>
                    <textarea name="description" rows="3" placeholder="Un classique réconfortant de la gastronomie française..."
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        URL de l'image
                    </label>
                    <input type="url" name="image_url" placeholder="https://images.unsplash.com/photo-..."
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Catégorie
                    </label>
                    <select name="recipe_category" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                        <option value="Plat principal">Plat principal</option>
                        <option value="Entrée">Entrée</option>
                        <option value="Dessert">Dessert</option>
                        <option value="Apéritif">Apéritif</option>
                        <option value="Boisson">Boisson</option>
                        <option value="Petit-déjeuner">Petit-déjeuner</option>
                        <option value="Autre">Autre</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Cuisine / Origine
                    </label>
                    <input type="text" name="recipe_cuisine" placeholder="Ex: Française, Italienne, Asiat..."
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Portions / Rendement
                    </label>
                    <input type="text" name="recipe_yield" value="4 personnes"
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Calories estimées
                    </label>
                    <input type="text" name="calories" placeholder="Ex: 450 kcal"
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
            </div>

            <!-- Temps de préparation et cuisson -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Temps de préparation (en minutes)
                    </label>
                    <input type="number" name="prep_minutes" min="0" value="15"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Temps de cuisson (en minutes)
                    </label>
                    <input type="number" name="cook_minutes" min="0" value="30"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
            </div>
        </div>

        <!-- Ingrédients & Instructions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Ingrédients -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-basket-shopping text-brand-500"></i> Ingrédients *
                    </h2>
                    <span class="text-xs text-slate-400">1 par ligne</span>
                </div>
                <textarea name="ingredients" rows="10" required
                          placeholder="1 kg de pommes de terre&#10;50 cl de crème liquide entière&#10;1 gousse d'ail&#10;50 g de beurre&#10;Sel et poivre&#10;Noix de muscade"
                          class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"></textarea>
            </div>

            <!-- Instructions -->
            <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-list-ol text-brand-500"></i> Étapes de Préparation *
                    </h2>
                    <span class="text-xs text-slate-400">1 étape par ligne</span>
                </div>
                <textarea name="instructions" rows="10" required
                          placeholder="Éplucher et couper les pommes de terre en fines lamelles.&#10;Frotter le plat à gratin avec la gousse d'ail puis beurrer généreusement.&#10;Disposer les pommes de terre en couches en salant et poivrant chaque étage.&#10;Verser la crème par-dessus et enfourner 50 min à 160°C."
                          class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"></textarea>
            </div>
        </div>

        <!-- Mots-clés & Auteur -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Mots-clés / Tags (séparés par des virgules)
                    </label>
                    <input type="text" name="keywords" placeholder="gratin, pommes de terre, réconfortant, hiver"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Auteur de la recette
                    </label>
                    <input type="text" name="author" value="<?= htmlspecialchars(getCurrentUsername()) ?>"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
            </div>
        </div>

        <!-- Bouton Enregistrer -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <a href="/pages/dashboard.php" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                Annuler
            </a>
            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Enregistrer la Recette</span>
            </button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
