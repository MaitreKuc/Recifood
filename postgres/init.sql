-- =========================================================
-- Recifood Database Initialization
-- Conforme au standard schema.org/Recipe
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_admin BOOLEAN DEFAULT FALSE, -- Le tout premier compte créé (admin du serveur) peut gérer toutes les recettes
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS recipes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    image_url TEXT,
    prep_time VARCHAR(50),
    cook_time VARCHAR(50),
    total_time VARCHAR(50),
    recipe_yield VARCHAR(100),
    recipe_category VARCHAR(100),
    recipe_cuisine VARCHAR(100),
    schema_data JSONB NOT NULL, -- Format complet schema.org/Recipe JSON-LD
    is_favorite BOOLEAN DEFAULT FALSE, -- Champ historique non utilisé (voir user_recipe_status pour le statut par utilisateur)
    already_cooked BOOLEAN DEFAULT FALSE, -- Champ historique non utilisé (voir user_recipe_status pour le statut par utilisateur)
    source_url TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_recipes_user_id ON recipes(user_id);
CREATE INDEX IF NOT EXISTS idx_recipes_category ON recipes(recipe_category);
CREATE INDEX IF NOT EXISTS idx_recipes_cuisine ON recipes(recipe_cuisine);
CREATE INDEX IF NOT EXISTS idx_recipes_schema_data ON recipes USING GIN (schema_data);

-- Statut personnel par utilisateur (favori / déjà cuisiné) : indépendant du créateur de la recette,
-- puisque les recettes sont désormais publiques et partagées entre tous les comptes.
CREATE TABLE IF NOT EXISTS user_recipe_status (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    recipe_id INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    is_favorite BOOLEAN DEFAULT FALSE,
    already_cooked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, recipe_id)
);

CREATE INDEX IF NOT EXISTS idx_urs_user ON user_recipe_status(user_id);
CREATE INDEX IF NOT EXISTS idx_urs_recipe ON user_recipe_status(recipe_id);

CREATE TABLE IF NOT EXISTS user_settings (
    id SERIAL PRIMARY KEY,
    user_id INTEGER UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    -- Configuration yt-dlp
    ytdlp_path VARCHAR(255) DEFAULT 'yt-dlp',
    ytdlp_download_video BOOLEAN DEFAULT FALSE,
    ytdlp_audio_only BOOLEAN DEFAULT TRUE,
    ytdlp_cookies_configured BOOLEAN DEFAULT FALSE, -- Le contenu des cookies n'est jamais stocké en base, uniquement sur disque
    ytdlp_cookies_updated_at TIMESTAMP WITH TIME ZONE,
    -- Configuration IA : Texte (structuration/extraction), Vision (image/PDF), Transcription (audio)
    -- L'utilisateur renseigne librement une URL API compatible OpenAI, une clé et un modèle pour chacun des 3 usages.
    ai_text_api_url TEXT DEFAULT '',
    ai_text_api_key TEXT DEFAULT '',
    ai_text_model VARCHAR(150) DEFAULT '',
    ai_vision_api_url TEXT DEFAULT '',
    ai_vision_api_key TEXT DEFAULT '',
    ai_vision_model VARCHAR(150) DEFAULT '',
    ai_transcription_api_url TEXT DEFAULT '',
    ai_transcription_api_key TEXT DEFAULT '',
    ai_transcription_model VARCHAR(150) DEFAULT '',
    ai_prompt_template TEXT DEFAULT 'Extrais la recette ci-dessous au format JSON schema.org/Recipe complet en français.',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Aucun compte n'est créé par défaut : le tout premier compte inscrit sur le serveur
-- devient automatiquement administrateur (voir la logique dans frontend/src/auth/register.php).

-- Insertion de recettes d'exemples strictement conformes à schema.org/Recipe.
-- Elles n'appartiennent à aucun utilisateur (user_id = NULL) puisqu'aucun compte n'existe encore
-- à l'initialisation ; elles restent publiques et visibles par tous comme n'importe quelle recette.
INSERT INTO recipes (user_id, name, description, image_url, prep_time, cook_time, total_time, recipe_yield, recipe_category, recipe_cuisine, is_favorite, schema_data)
VALUES
(
    NULL,
    'Tarte Tatin aux Pommes Caramélisées',
    'La véritable tarte Tatin traditionnelle, avec ses pommes fondantes généreusement caramélisées au beurre salé et sa pâte feuilletée croustillante.',
    'https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?auto=format&fit=crop&w=800&q=80',
    'PT25M',
    'PT35M',
    'PT1H',
    '6 personnes',
    'Dessert',
    'Française',
    TRUE,
    '{
        "@context": "https://schema.org",
        "@type": "Recipe",
        "name": "Tarte Tatin aux Pommes Caramélisées",
        "description": "La véritable tarte Tatin traditionnelle, avec ses pommes fondantes généreusement caramélisées au beurre salé et sa pâte feuilletée croustillante.",
        "image": [
            "https://images.unsplash.com/photo-1568571780765-9276ac8b75a2?auto=format&fit=crop&w=800&q=80"
        ],
        "author": {
            "@type": "Person",
            "name": "Chef Recifood"
        },
        "datePublished": "2026-03-01",
        "prepTime": "PT25M",
        "cookTime": "PT35M",
        "totalTime": "PT1H",
        "recipeYield": "6 personnes",
        "recipeCategory": "Dessert",
        "recipeCuisine": "Française",
        "keywords": "tarte, tatin, pommes, caramel, dessert traditionnel",
        "nutrition": {
            "@type": "NutritionInformation",
            "calories": "320 calories",
            "carbohydrateContent": "42 g",
            "fatContent": "16 g",
            "proteinContent": "3 g"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.9",
            "reviewCount": "18"
        },
        "recipeIngredient": [
            "1,5 kg de pommes (variété Golden ou Reine des reinettes)",
            "1 rouleau de pâte feuilletée pur beurre",
            "150 g de sucre en poudre",
            "80 g de beurre demi-sel",
            "1 sachet de sucre vanillé",
            "1 pincée de cannelle (optionnel)"
        ],
        "recipeInstructions": [
            {
                "@type": "HowToStep",
                "name": "Préparation des pommes",
                "text": "Éplucher, évider et couper les pommes en gros quartiers (en 4 ou en 6 selon la taille)."
            },
            {
                "@type": "HowToStep",
                "name": "Caramélisation",
                "text": "Dans un moule à manqué ou un moule à tatin allant sur le feu, faire fondre le beurre avec le sucre jusqu à l obtention d un caramel doré."
            },
            {
                "@type": "HowToStep",
                "name": "Disposition",
                "text": "Disposer les quartiers de pommes bien serrés debout ou sur la tranche dans le caramel chaud. Saupoudrer de sucre vanillé."
            },
            {
                "@type": "HowToStep",
                "name": "Précuisson des pommes",
                "text": "Laisser mijoter sur feu moyen pendant 10 à 15 minutes pour que les pommes s imprègnent bien du caramel."
            },
            {
                "@type": "HowToStep",
                "name": "Cuisson au four",
                "text": "Préchauffer le four à 180°C. Déposer la pâte feuilletée sur les pommes en rentrant bien les bords à l intérieur du moule. Piquer la pâte avec une fourchette."
            },
            {
                "@type": "HowToStep",
                "name": "Finalisation",
                "text": "Enfourner pendant environ 25 à 30 minutes jusqu à ce que la pâte soit bien dorée. Laisser tiédir 10 minutes puis démouler en retournant d un coup sec sur un plat de service."
            }
        ]
    }'::jsonb
),
(
    NULL,
    'Risotto crémeux aux Champignons des Bois et Parmesan',
    'Un risotto italien onctueux préparé avec du riz Carnaroli, des cèpes et girolles, du vin blanc sec et généreusement monté au beurre et Parmigiano Reggiano.',
    'https://images.unsplash.com/photo-1633964913295-ceb43826e7c9?auto=format&fit=crop&w=800&q=80',
    'PT15M',
    'PT25M',
    'PT40M',
    '4 personnes',
    'Plat principal',
    'Italienne',
    FALSE,
    '{
        "@context": "https://schema.org",
        "@type": "Recipe",
        "name": "Risotto crémeux aux Champignons des Bois et Parmesan",
        "description": "Un risotto italien onctueux préparé avec du riz Carnaroli, des cèpes et girolles, du vin blanc sec et généreusement monté au beurre et Parmigiano Reggiano.",
        "image": [
            "https://images.unsplash.com/photo-1633964913295-ceb43826e7c9?auto=format&fit=crop&w=800&q=80"
        ],
        "author": {
            "@type": "Person",
            "name": "Chef Recifood"
        },
        "datePublished": "2026-03-02",
        "prepTime": "PT15M",
        "cookTime": "PT25M",
        "totalTime": "PT40M",
        "recipeYield": "4 personnes",
        "recipeCategory": "Plat principal",
        "recipeCuisine": "Italienne",
        "keywords": "risotto, champignons, cèpes, parmesan, cuisine italienne",
        "nutrition": {
            "@type": "NutritionInformation",
            "calories": "480 calories",
            "carbohydrateContent": "65 g",
            "fatContent": "18 g",
            "proteinContent": "12 g"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "24"
        },
        "recipeIngredient": [
            "320 g de riz à risotto (Arborio ou Carnaroli)",
            "400 g de champignons mélangés (cèpes, girolles, champignons de Paris)",
            "1 litre de bouillon de volaille ou de légumes chaud",
            "1 échalote finement émincée",
            "1 gousse d ail",
            "12 cl de vin blanc sec",
            "70 g de Parmigiano Reggiano râpé fraîchement",
            "40 g de beurre doux",
            "2 cuillères à soupe d huile d olive",
            "Quelques brins de persil plat frais",
            "Sel fin et poivre noir du moulin"
        ],
        "recipeInstructions": [
            {
                "@type": "HowToStep",
                "name": "Poêlée de champignons",
                "text": "Nettoyer et couper les champignons. Les faire sauter à feu vif dans une poêle avec 1 c. à soupe d huile d olive et l ail haché pendant 5 minutes. Saler, poivrer et réserver."
            },
            {
                "@type": "HowToStep",
                "name": "Nacrer le riz",
                "text": "Dans une sauteuse, faire suer l échalote dans 1 c. à soupe d huile d olive. Ajouter le riz et remuer 2 minutes jusqu à ce qu il devienne translucide."
            },
            {
                "@type": "HowToStep",
                "name": "Déglacer",
                "text": "Verser le vin blanc sec et laisser s évaporer complètement à feu moyen en remuant continuellement."
            },
            {
                "@type": "HowToStep",
                "name": "Cuisson par absorption",
                "text": "Ajouter le bouillon chaud louche par louche, en attendant que le liquide soit absorbé avant d en rajouter. Cuire pendant environ 18 minutes."
            },
            {
                "@type": "HowToStep",
                "name": "Mantecatura (Liaison finale)",
                "text": "Retirer du feu. Incorporer les champignons cuits, le beurre froid coupé en dés et le parmesan râpé. Couvrir 2 minutes puis mélanger vigoureusement pour obtenir une texture crémeuse (all onda)."
            },
            {
                "@type": "HowToStep",
                "name": "Dressage",
                "text": "Servir immédiatement dans des assiettes creuses chaudes, parsemer de persil plat ciselé et d un tour de moulin à poivre."
            }
        ]
    }'::jsonb
),
(
    NULL,
    'Pad Thaï Traditionnel aux Crevettes',
    'Le grand classique de la street food thaïlandaise : nouilles de riz sautées, crevettes, tofu, cacahuètes concassées, germes de soja et une sauce aigre-douce au tamarin.',
    'https://images.unsplash.com/photo-1559314809-0d155014e29e?auto=format&fit=crop&w=800&q=80',
    'PT20M',
    'PT10M',
    'PT30M',
    '2 personnes',
    'Plat principal',
    'Thaïlandaise',
    TRUE,
    '{
        "@context": "https://schema.org",
        "@type": "Recipe",
        "name": "Pad Thaï Traditionnel aux Crevettes",
        "description": "Le grand classique de la street food thaïlandaise : nouilles de riz sautées, crevettes, tofu, cacahuètes concassées, germes de soja et une sauce aigre-douce au tamarin.",
        "image": [
            "https://images.unsplash.com/photo-1559314809-0d155014e29e?auto=format&fit=crop&w=800&q=80"
        ],
        "author": {
            "@type": "Person",
            "name": "Chef Recifood"
        },
        "datePublished": "2026-03-03",
        "prepTime": "PT20M",
        "cookTime": "PT10M",
        "totalTime": "PT30M",
        "recipeYield": "2 personnes",
        "recipeCategory": "Plat principal",
        "recipeCuisine": "Thaïlandaise",
        "keywords": "pad thai, crevettes, nouilles de riz, street food, thaïlande, wok",
        "nutrition": {
            "@type": "NutritionInformation",
            "calories": "520 calories",
            "carbohydrateContent": "68 g",
            "fatContent": "16 g",
            "proteinContent": "26 g"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "5.0",
            "reviewCount": "31"
        },
        "recipeIngredient": [
            "180 g de nouilles de riz plates pour Pad Thaï",
            "12 grosses crevettes décortiquées crues",
            "100 g de tofu ferme coupé en petits dés",
            "2 œufs",
            "100 g de germes de soja frais",
            "3 tiges de ciboulette thaï ou oignons nouveaux",
            "3 c. à soupe de pâte de tamarin délayée",
            "2 c. à soupe de sauce de poisson (nuoc-mâm)",
            "2 c. à soupe de sucre de palme (ou cassonade)",
            "2 gousses d ail émincées",
            "40 g de cacahuètes grillées non salées et concassées",
            "1 quartier de citron vert et flocons de piment pour servir"
        ],
        "recipeInstructions": [
            {
                "@type": "HowToStep",
                "name": "Trempage des nouilles",
                "text": "Faire tremper les nouilles de riz dans de l eau tiède pendant 30 minutes jusqu à ce qu elles soient souples mais encore fermes. Égoutter."
            },
            {
                "@type": "HowToStep",
                "name": "Sauce Pad Thaï",
                "text": "Mélanger dans un bol la pâte de tamarin, la sauce de poisson et le sucre jusqu à dissolution complète."
            },
            {
                "@type": "HowToStep",
                "name": "Saisie au wok",
                "text": "Dans un wok très chaud avec de l huile, faire revenir l ail, le tofu et les crevettes pendant 2 minutes. Pousser sur le côté du wok."
            },
            {
                "@type": "HowToStep",
                "name": "Cuisson des œufs",
                "text": "Casser les œufs dans l espace libre, les brouiller rapidement et mélanger avec les crevettes et le tofu."
            },
            {
                "@type": "HowToStep",
                "name": "Assemblage des nouilles",
                "text": "Ajouter les nouilles égouttées et verser la sauce. Faire sauter vigoureusement à feu très vif pendant 2 minutes en mélangeant bien."
            },
            {
                "@type": "HowToStep",
                "name": "Finition et service",
                "text": "Ajouter la moitié des pousses de soja et la ciboulette thaï. Couper le feu, dresser dans les assiettes, parsemer de cacahuètes concassées, du reste de pousses de soja fraîches et servir avec un quartier de citron vert."
            }
        ]
    }'::jsonb
);

-- Resynchronisation de la séquence des recettes par précaution
SELECT setval('recipes_id_seq', (SELECT COALESCE(MAX(id), 1) FROM recipes));

-- Remarque : contrairement aux versions précédentes, aucune ligne n'est insérée dans
-- user_recipe_status pour les recettes de démonstration : elles n'appartiennent à aucun
-- utilisateur (user_id = NULL) tant qu'aucun compte n'a été créé sur le serveur.
