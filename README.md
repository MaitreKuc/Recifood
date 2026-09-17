# Recifood 🍳 &mdash; Gestionnaire de Recettes (Standard Schema.org)

Recifood est une application web complète de gestion de recettes culinaires, conforme au standard international **[schema.org/Recipe](https://schema.org/Recipe)**.

---

## 🏗️ Architecture & Technologies

- **Frontend** : PHP 8.2 (Apache), HTML5, JavaScript moderne, CSS (Tailwind CSS CDN + FontAwesome 6).
- **Backend API** : Python 3.11 (FastAPI, Uvicorn, Pydantic, BeautifulSoup4, yt-dlp, OpenAI).
- **Base de données** : PostgreSQL 16 (avec support natif JSONB pour les fiches Schema.org JSON-LD).
- **Conteneurisation** : Docker & Docker Compose.

---

## 🚀 Démarrage Rapide avec Docker

### 1. Cloner ou ouvrir le projet
```bash
cd Recifood
```

### 2. Configuration d'environnement (optionnel)
Le fichier `.env` est déjà préconfiguré avec les valeurs par défaut :
```bash
# Copier si nécessaire
cp .env.example .env
```

### 3. Lancer les conteneurs Docker
```bash
docker-compose up -d --build
```

### 4. Accéder à l'application
- **Frontend Web** : [http://localhost:8080](http://localhost:8080)
- **Backend API Docs (Swagger)** : [http://localhost:8000/docs](http://localhost:8000/docs)
- **PostgreSQL** : `localhost:5432`

---

## 🔑 Identifiants de Démonstration

Un compte administrateur avec des recettes d'exemples complètes au standard Schema.org est injecté par défaut :
- **Nom d'utilisateur / Email** : `admin` ou `admin@recifood.local`
- **Mot de passe** : `admin123`

*(Vous pouvez également créer un nouveau compte via la page d'inscription)*.

---

## ✨ Fonctionnalités Incluses

1. **Authentification complète** :
   - Inscription sécurisée (hachage bcrypt).
   - Connexion et gestion des sessions utilisateur.
   - Déconnexion.

2. **Tableau de bord des recettes (`/pages/dashboard.php`)** :
   - Affichage sous forme de cartes élégantes avec images, temps de cuisson, portions et notes.
   - Recherche en direct par mot-clé, titre ou ingrédient.
   - Filtres par catégorie (Plat principal, Dessert, etc.) et cuisine (Française, Italienne, etc.).
   - Filtre des recettes favorites (avec bascule interactive en AJAX).

3. **Vue détaillée de recette (`/pages/recipe-detail.php`)** :
   - Rendu fidèle et dynamique conforme au standard **schema.org/Recipe**.
   - Balise `<script type="application/ld+json">` injectée pour l'interopérabilité et le SEO.
   - Liste des ingrédients avec cases à cocher pour préparation/courses.
   - Étapes de préparation interactives (cases à cocher de progression).
   - Informations nutritionnelles (`NutritionInformation` : calories, protéines, lipides, glucides).
   - Actions rapides : Imprimer la recette (style d'impression optimisé), Télécharger le JSON-LD, Basculer en favori, Supprimer.
   - Inspecteur JSON-LD Schema.org intégré.

4. **Création manuelle de recette (`/pages/recipe-new.php`)** :
   - Formulaire guidé générant automatiquement le JSON-LD Schema.org.

5. **Page d'importation (`/pages/import.php`)** :
   - **Page Web (URL)** : Scrape automatiquement les balises Schema.org JSON-LD de Marmiton, 750g, Allrecipes, BBC Good Food, etc.
   - **Vidéo (yt-dlp)** : Prise en charge des vidéos YouTube / TikTok pour extraction audio et structuration IA.
   - **Texte Libre (IA)** : Structuration automatique d'un texte brut en recette Schema.org.
   - **JSON Schema.org Direct** : Import direct d'un objet JSON-LD existant avec prévisualisation.

6. **Page de paramètres (`/pages/settings.php`)** :
   - Configuration de **yt-dlp** (chemin du binaire, cookies, options audio/vidéo).
   - Configuration des **Providers IA** : OpenAI, Anthropic Claude, Mistral AI, Groq, Ollama (Local LLM avec base URL personnalisée).
   - Test de connexion IA en direct.

---

## 📋 Schéma de Données (schema.org/Recipe)

Les recettes sont stockées en colonnes relationnelles (pour indexation et filtres rapides) et en objet JSON-LD complet conforme :

```json
{
  "@context": "https://schema.org",
  "@type": "Recipe",
  "name": "Tarte Tatin aux Pommes Caramélisées",
  "description": "...",
  "image": ["https://..."],
  "author": { "@type": "Person", "name": "Chef" },
  "datePublished": "2026-03-01",
  "prepTime": "PT25M",
  "cookTime": "PT35M",
  "totalTime": "PT1H",
  "recipeYield": "6 personnes",
  "recipeCategory": "Dessert",
  "recipeCuisine": "Française",
  "recipeIngredient": ["..."],
  "recipeInstructions": [
    { "@type": "HowToStep", "name": "Étape 1", "text": "..." }
  ],
  "nutrition": {
    "@type": "NutritionInformation",
    "calories": "320 calories"
  }
}
```
