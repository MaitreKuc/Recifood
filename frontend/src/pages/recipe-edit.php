<?php
/**
 * Page de modification d'une recette existante (Schema.org/Recipe)
 * Accessible au créateur de la recette ou à l'administrateur du serveur.
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAuth();

$user_id = getCurrentUserId();
$recipe_id = (int)($_GET['id'] ?? $_POST['recipe_id'] ?? 0);
$error = '';

if ($recipe_id <= 0) {
    header('Location: /pages/dashboard.php');
    exit;
}

$db = getDB();
$stmt = $db->prepare("SELECT * FROM recipes WHERE id = :id");
$stmt->execute([':id' => $recipe_id]);
$recipe = $stmt->fetch();

if (!$recipe) {
    header('Location: /pages/dashboard.php?error=not_found');
    exit;
}

// Autorisation : uniquement le créateur de la recette ou l'administrateur du serveur.
if ((int)$recipe['user_id'] !== (int)$user_id && !isAdmin()) {
    header('Location: /pages/recipe-detail.php?id=' . $recipe_id . '&error=forbidden');
    exit;
}

$schema = json_decode($recipe['schema_data'], true) ?: [];

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

    $ingredients_raw = $_POST['ingredients'] ?? '';
    $ingredients = array_values(array_filter(array_map('trim', explode("\n", $ingredients_raw))));

    $instructions_raw = $_POST['instructions'] ?? '';
    $instruction_lines = array_values(array_filter(array_map('trim', explode("\n", $instructions_raw))));
    $instructions = [];
    foreach ($instruction_lines as $step_text) {
        $instructions[] = [
            '@type' => 'HowToStep',
            'text' => $step_text
        ];
    }

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

        // On repart du schema existant pour préserver les champs non gérés par ce formulaire
        // (vidéo source, notation, url d'origine...) et on met à jour uniquement les champs modifiés.
        $schema_data = $schema;
        $schema_data['@context'] = $schema_data['@context'] ?? 'https://schema.org';
        $schema_data['@type'] = 'Recipe';
        $schema_data['name'] = $name;
        $schema_data['description'] = $description;
        $schema_data['image'] = !empty($image_url) ? [$image_url] : [];
        $schema_data['author'] = [
            '@type' => 'Person',
            'name' => $author
        ];
        $schema_data['prepTime'] = $prep_iso;
        $schema_data['cookTime'] = $cook_iso;
        $schema_data['totalTime'] = $total_iso;
        $schema_data['recipeYield'] = $recipe_yield;
        $schema_data['recipeCategory'] = $recipe_category;
        $schema_data['recipeCuisine'] = $recipe_cuisine;
        $schema_data['keywords'] = $keywords;
        $schema_data['recipeIngredient'] = $ingredients;
        $schema_data['recipeInstructions'] = $instructions;

        if ($nutrition) {
            $schema_data['nutrition'] = $nutrition;
        } else {
            unset($schema_data['nutrition']);
        }

        try {
            $stmt = $db->prepare("
                UPDATE recipes SET
                    name = :name, description = :desc, image_url = :img,
                    prep_time = :prep, cook_time = :cook, total_time = :total,
                    recipe_yield = :yield, recipe_category = :cat, recipe_cuisine = :cui,
                    schema_data = :schema, updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':name' => $name,
                ':desc' => $description,
                ':img' => $image_url,
                ':prep' => $prep_iso,
                ':cook' => $cook_iso,
                ':total' => $total_iso,
                ':yield' => $recipe_yield,
                ':cat' => $recipe_category,
                ':cui' => $recipe_cuisine,
                ':schema' => json_encode($schema_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':id' => $recipe_id
            ]);

            header("Location: /pages/recipe-detail.php?id=" . $recipe_id);
            exit;
        } catch (Exception $e) {
            $error = "Erreur d'enregistrement : " . $e->getMessage();
        }
    }

    // En cas d'erreur, on réaffiche le formulaire avec les valeurs soumises.
    $form_name = $name;
    $form_description = $description;
    $form_image_url = $image_url;
    $form_prep_minutes = $prep_minutes;
    $form_cook_minutes = $cook_minutes;
    $form_recipe_yield = $recipe_yield;
    $form_recipe_category = $recipe_category;
    $form_recipe_cuisine = $recipe_cuisine;
    $form_author = $author;
    $form_keywords = $keywords;
    $form_calories = $calories;
    $form_ingredients = $ingredients_raw;
    $form_instructions = $instructions_raw;
} else {
    // Pré-remplissage à partir des données existantes (schema.org)
    $image = $schema['image'] ?? '';
    $form_image_url = is_array($image) ? ($image[0] ?? '') : $image;

    $author_val = $schema['author']['name'] ?? ($schema['author'] ?? getCurrentUsername());
    if (is_array($author_val)) $author_val = $author_val['name'] ?? getCurrentUsername();

    $keywords_val = $schema['keywords'] ?? '';
    $form_keywords = is_array($keywords_val) ? implode(', ', $keywords_val) : $keywords_val;

    $ingredients_list = $schema['recipeIngredient'] ?? [];
    $instructions_list = $schema['recipeInstructions'] ?? [];
    $steps_text = [];
    foreach ($instructions_list as $step) {
        if (is_array($step)) {
            $steps_text[] = $step['text'] ?? '';
        } else {
            $steps_text[] = $step;
        }
    }

    $calories_val = $schema['nutrition']['calories'] ?? '';
    $calories_val = preg_replace('/\s*calories?\s*$/i', '', $calories_val);

    $form_name = $recipe['name'];
    $form_description = $recipe['description'];
    $form_prep_minutes = isoDurationToMinutes($recipe['prep_time']);
    $form_cook_minutes = isoDurationToMinutes($recipe['cook_time']);
    $form_recipe_yield = $recipe['recipe_yield'] ?: '4 personnes';
    $form_recipe_category = $recipe['recipe_category'] ?: 'Plat principal';
    $form_recipe_cuisine = $recipe['recipe_cuisine'];
    $form_author = $author_val;
    $form_calories = $calories_val;
    $form_ingredients = implode("\n", $ingredients_list);
    $form_instructions = implode("\n", $steps_text);
}

$category_options = ['Plat principal', 'Entrée', 'Dessert', 'Apéritif', 'Boisson', 'Petit-déjeuner', 'Autre'];

$page_title = "Modifier " . htmlspecialchars($recipe['name']) . " - Recifood";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="flex-1 max-w-4xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-900 font-['Plus_Jakarta_Sans']">
                Modifier la Recette
            </h1>
            <p class="text-sm text-slate-500 mt-1">Mettez à jour les informations de cette recette conforme au format schema.org/Recipe</p>
        </div>
        <a href="/pages/recipe-detail.php?id=<?= $recipe_id ?>" class="text-sm text-slate-500 hover:text-slate-800 font-medium">
            <i class="fa-solid fa-xmark mr-1"></i> Annuler
        </a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm flex items-start space-x-3">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-red-500"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="/pages/recipe-edit.php?id=<?= $recipe_id ?>" class="space-y-6">
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
                    <input type="text" name="name" required value="<?= htmlspecialchars($form_name) ?>" placeholder="Ex: Gratin Dauphinois Traditionnel"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Description / Histoire de la recette
                    </label>
                    <textarea name="description" rows="3" placeholder="Un classique réconfortant de la gastronomie française..."
                              class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"><?= htmlspecialchars($form_description) ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        URL de l'image
                    </label>
                    <input type="text" name="image_url" value="<?= htmlspecialchars($form_image_url) ?>" placeholder="https://images.unsplash.com/photo-... ou /uploads/recipes/..."
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                    <?php if (!empty($form_image_url)): ?>
                        <img src="<?= htmlspecialchars($form_image_url) ?>" alt="Vignette actuelle" class="mt-3 w-40 h-28 object-cover rounded-xl border border-slate-200">
                    <?php endif; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Catégorie
                    </label>
                    <select name="recipe_category" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                        <?php foreach ($category_options as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>" <?= $form_recipe_category === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Cuisine / Origine
                    </label>
                    <input type="text" name="recipe_cuisine" value="<?= htmlspecialchars($form_recipe_cuisine) ?>" placeholder="Ex: Française, Italienne, Asiat..."
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Portions / Rendement
                    </label>
                    <input type="text" name="recipe_yield" value="<?= htmlspecialchars($form_recipe_yield) ?>"
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Calories estimées
                    </label>
                    <input type="text" name="calories" value="<?= htmlspecialchars($form_calories) ?>" placeholder="Ex: 450 kcal"
                           class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
            </div>

            <!-- Temps de préparation et cuisson -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Temps de préparation (en minutes)
                    </label>
                    <input type="number" name="prep_minutes" min="0" value="<?= (int)$form_prep_minutes ?>"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Temps de cuisson (en minutes)
                    </label>
                    <input type="number" name="cook_minutes" min="0" value="<?= (int)$form_cook_minutes ?>"
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
                          class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"><?= htmlspecialchars($form_ingredients) ?></textarea>
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
                          class="w-full p-4 bg-slate-50 border border-slate-200 rounded-xl text-sm font-sans focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition"><?= htmlspecialchars($form_instructions) ?></textarea>
            </div>
        </div>

        <!-- Mots-clés & Auteur -->
        <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Mots-clés / Tags (séparés par des virgules)
                    </label>
                    <input type="text" name="keywords" value="<?= htmlspecialchars($form_keywords) ?>" placeholder="gratin, pommes de terre, réconfortant, hiver"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">
                        Auteur de la recette
                    </label>
                    <input type="text" name="author" value="<?= htmlspecialchars($form_author) ?>"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 focus:bg-white transition">
                </div>
            </div>
        </div>

        <!-- Bouton Enregistrer -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <a href="/pages/recipe-detail.php?id=<?= $recipe_id ?>" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                Annuler
            </a>
            <button type="submit" class="px-6 py-2.5 bg-gradient-to-r from-brand-500 to-amber-500 hover:from-brand-600 hover:to-amber-600 text-white text-sm font-semibold rounded-xl shadow-md hover:shadow-lg transition flex items-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Enregistrer les Modifications</span>
            </button>
        </div>
    </form>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
