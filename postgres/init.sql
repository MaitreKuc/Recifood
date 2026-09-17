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


