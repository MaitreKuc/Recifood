# Recifood 🍳 &mdash; Gestionnaire de Recettes (Standard Schema.org)

**Recifood est un projet personnel en vibe coding (car j'avais la flemme, je code déjà assez au taff), car les solutions qui existent, ne me convenaient pas, mais l'inspiration vient clairement de [https://github.com/mealie-recipes/mealie/] et [https://github.com/GerardPolloRebozado/social-to-mealie]**

Recifood est une application web complète de gestion de recettes culinaires, conforme au standard international **[schema.org/Recipe](https://schema.org/Recipe)**.


**Forkez le, c'est gratuit et fonctionnel** (à part avec les recettes vraiment obscure, où même nous, humain, on a dû mal à savoir les quantités/ingrédient)

---

## 🏗️ Architecture & Technologies

- **Frontend** : PHP 8.2 (Apache), HTML5, JavaScript moderne, CSS (Tailwind CSS CDN + FontAwesome 6).
- **PWA** : Manifest + Service Worker (installable sur mobile/desktop, Web Share Target pour importer une recette directement depuis le menu « Partager » d'une autre application).
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

La base de données démarre entièrement vide, sans aucune recette d'exemple : à vous d'importer ou de créer vos premières recettes une fois connecté.

---

## ✨ Fonctionnalités Incluses

1. **Authentification & rôles** :
   - Inscription sécurisée (hachage bcrypt), connexion, déconnexion.
   - Le premier compte créé devient automatiquement administrateur du serveur (peut modifier/supprimer n'importe quelle recette).
   - Les recettes sont publiques par défaut (consultables sans compte) ; la création, l'import, les paramètres, les favoris et le statut « déjà cuisiné » nécessitent un compte.

2. **Tableau de bord des recettes (`/pages/dashboard.php`)** :
   - Affichage sous forme de cartes élégantes, entièrement cliquables (image et contenu), avec images, temps de cuisson et portions.
   - Recherche en direct par mot-clé, titre ou ingrédient.
   - Filtres par catégorie (Plat principal, Dessert, etc.) et cuisine (Française, Italienne, etc.).
   - Filtres « Favoris » et « J'ai déjà cuisiné » (statut propre à chaque utilisateur, avec bascule interactive en AJAX, accessible aussi bien depuis le tableau de bord que depuis la fiche recette).

3. **Vue détaillée de recette (`/pages/recipe-detail.php`)** :
   - Rendu fidèle et dynamique conforme au standard **schema.org/Recipe**.
   - Balise `<script type="application/ld+json">` injectée pour l'interopérabilité et le SEO.
   - Image affichée en entier (sans recadrage/zoom agressif).
   - Nombre de portions ajustable (+/-) avec **recalcul automatique des quantités** de chaque ingrédient.
   - Liste des ingrédients avec cases à cocher pour préparation/courses.
   - Étapes de préparation interactives (cases à cocher de progression).
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
   - **Gestion du compte** : modification du nom d'utilisateur et changement de mot de passe (avec vérification du mot de passe actuel).
   - Configuration de **yt-dlp** (cookies au format Netscape `cookies.txt`, collés directement et enregistrés côté serveur sans jamais afficher leur contenu sensible ensuite ; options audio/vidéo).
   - Configuration de **3 providers IA indépendants** (Texte, Vision, Transcription) : chacun avec sa propre URL d'API compatible OpenAI, sa clé et son modèle, au libre choix de l'utilisateur (aucun fournisseur imposé).
   - Test de connexion IA en direct.

---

## 📱 Application Web Progressive (PWA)

Recifood est installable comme une application native sur mobile et desktop (manifest + service worker), et intègre le **Web Share Target** : une fois l'application installée, elle apparaît directement dans le menu **« Partager »** du téléphone.

**Exemple d'utilisation** : depuis Instagram (ou YouTube, TikTok, Chrome...), appuyez sur *Partager* → sélectionnez **Recifood** → si vous êtes connecté, l'analyse et l'import de la recette démarrent automatiquement (avec la cascade Schema.org → vidéo → post réseau social) ; sinon, l'application vous demande de vous connecter puis reprend l'import là où il s'était arrêté.

### Installation
- **Android (Chrome)** : menu ⋮ → « Installer l'application » (ou bannière automatique proposée par le navigateur).
- **iOS (Safari)** : bouton Partager → « Sur l'écran d'accueil » (le Web Share Target n'est pas supporté par iOS/Safari, l'import manuel via la page « Importer » reste toujours disponible).
- **Desktop (Chrome/Edge)** : icône d'installation dans la barre d'adresse.

### ⚠️ Prérequis important : HTTPS
Le Web Share Target (et l'installation de la PWA en général) **nécessite HTTPS** — seul `http://localhost` est toléré par les navigateurs pour du développement local. Pour tester sur un vrai téléphone, le site Docker (servi en HTTP simple par défaut) doit être exposé derrière un reverse proxy TLS de votre choix (ex. Caddy, Traefik, Nginx + Let's Encrypt, ou un tunnel type Cloudflare Tunnel/ngrok pour les tests).

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
