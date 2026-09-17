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

## 🔑 Premier lancement

Aucun compte n'est créé par défaut. **Le tout premier compte inscrit sur le serveur devient automatiquement l'administrateur** (droits étendus : modification/suppression de n'importe quelle recette). Rendez-vous sur la page d'inscription pour créer ce premier compte.

Des recettes d'exemples complètes au standard Schema.org sont injectées par défaut et sont publiques (visibles par tous, y compris sans compte).

---

## ✨ Fonctionnalités Incluses

1. **Authentification & rôles** :
   - Inscription sécurisée (hachage bcrypt), connexion, déconnexion.
   - Le premier compte créé devient automatiquement administrateur du serveur (peut modifier/supprimer n'importe quelle recette).
   - Les recettes sont publiques par défaut (consultables sans compte) ; la création, l'import, les paramètres, les favoris et le statut « déjà cuisiné » nécessitent un compte.

2. **Tableau de bord des recettes (`/pages/dashboard.php`)** :
   - Affichage sous forme de cartes élégantes avec images, temps de cuisson, portions et notes.
   - Recherche en direct par mot-clé, titre ou ingrédient.
   - Filtres par catégorie (Plat principal, Dessert, etc.) et cuisine (Française, Italienne, etc.).
   - Filtres « Favoris » et « J'ai déjà cuisiné » (statut propre à chaque utilisateur, avec bascule interactive en AJAX).

3. **Vue détaillée de recette (`/pages/recipe-detail.php`)** :
   - Rendu fidèle et dynamique conforme au standard **schema.org/Recipe**.
   - Balise `<script type="application/ld+json">` injectée pour l'interopérabilité et le SEO.
   - Liste des ingrédients avec cases à cocher pour préparation/courses.
   - Étapes de préparation interactives (cases à cocher de progression).
   - Informations nutritionnelles (`NutritionInformation` : calories, protéines, lipides, glucides).
   - Actions rapides : Favoris, « J'ai déjà cuisiné », Imprimer, Télécharger le JSON-LD, Partager un lien public, Modifier (propriétaire ou administrateur), Supprimer (propriétaire ou administrateur).
   - Inspecteur JSON-LD Schema.org intégré.

4. **Création et modification manuelle de recette (`/pages/recipe-new.php`, `/pages/recipe-edit.php`)** :
   - Formulaire guidé générant automatiquement le JSON-LD Schema.org.
   - Modification réservée au propriétaire de la recette ou à l'administrateur.

5. **Page d'importation unifiée (`/pages/import.php`)** :
   - **Site Web** : détection automatique en cascade à partir d'une simple adresse — balisage Schema.org/microdata en premier (Marmiton, 750g, Allrecipes, BBC Good Food, blogs...), puis vidéo via yt-dlp + transcription IA (YouTube, TikTok...), puis post de réseau social (Instagram, Facebook, Threads, Pinterest...) analysé par IA Texte + Vision sur la légende et les photos du carrousel — utile quand la recette n'est écrite que sur les images.
   - **Texte Libre (IA)** : Structuration automatique d'un texte brut en recette Schema.org.
   - **JSON Schema.org Direct** : Import direct d'un objet JSON-LD existant avec prévisualisation.
   - **Image / PDF** : Analyse d'une photo, capture d'écran ou PDF via l'IA Vision, avec conservation de l'image importée comme vignette de la recette.

6. **IA — Imagine (`/pages/ai-imagine.php`)** :
   - Génère une recette originale au format Schema.org à partir d'une simple description libre, via le provider IA Texte configuré.

7. **Page de paramètres (`/pages/settings.php`)** :
   - Configuration de **yt-dlp** (cookies au format Netscape `cookies.txt`, collés directement et enregistrés côté serveur sans jamais afficher leur contenu sensible ensuite ; options audio/vidéo).
   - Configuration de **3 providers IA indépendants** (Texte, Vision, Transcription) : chacun avec sa propre URL d'API compatible OpenAI, sa clé et son modèle, au libre choix de l'utilisateur (aucun fournisseur imposé).
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
