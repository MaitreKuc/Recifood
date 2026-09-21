"""
Client IA générique compatible API OpenAI.
L'utilisateur configure lui-même l'URL de l'API, la clé et le modèle pour 3 usages :
- Texte (structuration / extraction de recette)
- Vision (analyse d'image / PDF scanné)
- Transcription (audio -> texte, ex: Whisper)
"""
import json
import re
import base64
from typing import List, Dict, Any


class AiConfigError(Exception):
    pass


def _get_client(api_url: str, api_key: str):
    import openai

    if not api_url:
        raise AiConfigError("Aucune URL d'API IA n'a été configurée. Rendez-vous dans les Paramètres.")

    return openai.OpenAI(
        api_key=api_key if api_key else "not-required",
        base_url=api_url.rstrip("/"),
    )


def clean_json_response(raw: str) -> Any:
    cleaned = re.sub(r'^```json\s*|^```\s*|\s*```$', '', raw.strip(), flags=re.MULTILINE).strip()
    return json.loads(cleaned)


RECIPE_SYSTEM_PROMPT = (
    "Tu es un expert culinaire et structurateur de données Schema.org. "
    "À partir du contenu fourni par l'utilisateur, génère un JSON STRICTEMENT conforme au standard schema.org/Recipe.\n"
    "RÈGLE PRIORITAIRE : traduis et rédige TOUJOURS toute la recette en français, quelle que soit la langue de la source. "
    "Le titre, la description, les ingrédients, les étapes, la catégorie et tous les textes destinés à l'utilisateur doivent être en français.\n"
    "Champs requis dans le JSON :\n"
    "- @context: 'https://schema.org'\n"
    "- @type: 'Recipe'\n"
    "- name: titre de la recette\n"
    "- description: court résumé\n"
    "- prepTime: format ISO 8601 (ex: PT15M)\n"
    "- cookTime: format ISO 8601 (ex: PT30M)\n"
    "- totalTime: format ISO 8601 (ex: PT45M)\n"
    "- recipeYield: nombre de portions (ex: '4 personnes')\n"
    "- recipeCategory: catégorie (Plat principal, Entrée, Dessert, etc.)\n"
    "- recipeCuisine: origine géographique\n"
    "- recipeIngredient: tableau de chaînes avec quantités et ingrédients\n"
    "- recipeInstructions: tableau d'objets HowToStep avec propriété 'text'\n"
    "IMPORTANT : n'invente JAMAIS prepTime, cookTime ou totalTime. Si ces durées ne sont pas explicitement indiquées "
    "dans le contenu fourni, laisse le champ correspondant à null (ne mets aucune estimation ou valeur par défaut).\n"
    "Ne réponds QUE par le JSON brut, sans backticks markdown ni texte d'accompagnement."
)


def extract_recipe_from_text(text: str, api_url: str, api_key: str, model: str) -> Dict[str, Any]:
    """Utilise le provider IA 'Texte' pour structurer un texte brut en JSON Schema.org/Recipe"""
    client = _get_client(api_url, api_key)
    res = client.chat.completions.create(
        model=model,
        messages=[
            {"role": "system", "content": RECIPE_SYSTEM_PROMPT},
            {"role": "user", "content": text},
        ],
        temperature=0.2,
    )
    raw_content = res.choices[0].message.content
    return clean_json_response(raw_content)


IMAGINE_SYSTEM_PROMPT = (
    "Tu es un chef cuisinier créatif et structurateur de données Schema.org. "
    "À partir de l'envie / l'idée / la contrainte décrite par l'utilisateur, IMAGINE et INVENTE une recette de cuisine originale et réalisable "
    "qui correspond à sa demande, puis génère un JSON STRICTEMENT conforme au standard schema.org/Recipe.\n"
    "Champs requis dans le JSON :\n"
    "- @context: 'https://schema.org'\n"
    "- @type: 'Recipe'\n"
    "- name: titre de la recette imaginée\n"
    "- description: court résumé appétissant\n"
    "- prepTime: format ISO 8601 (ex: PT15M)\n"
    "- cookTime: format ISO 8601 (ex: PT30M)\n"
    "- totalTime: format ISO 8601 (ex: PT45M)\n"
    "- recipeYield: nombre de portions (ex: '4 personnes')\n"
    "- recipeCategory: catégorie (Plat principal, Entrée, Dessert, etc.)\n"
    "- recipeCuisine: origine géographique\n"
    "- recipeIngredient: tableau de chaînes avec quantités précises et ingrédients\n"
    "- recipeInstructions: tableau d'objets HowToStep avec propriété 'text', étapes claires et détaillées\n"
    "Ne réponds QUE par le JSON brut, sans backticks markdown ni texte d'accompagnement."
)


def imagine_recipe_from_prompt(prompt: str, api_url: str, api_key: str, model: str) -> Dict[str, Any]:
    """Utilise le provider IA 'Texte' pour générer/inventer une recette à partir d'un prompt libre"""
    client = _get_client(api_url, api_key)
    res = client.chat.completions.create(
        model=model,
        messages=[
            {"role": "system", "content": IMAGINE_SYSTEM_PROMPT},
            {"role": "user", "content": prompt},
        ],
        temperature=0.8,
    )
    raw_content = res.choices[0].message.content
    return clean_json_response(raw_content)


def extract_recipe_from_images(image_paths: List[str], api_url: str, api_key: str, model: str) -> Dict[str, Any]:
    """Utilise le provider IA 'Vision' pour structurer une ou plusieurs images en JSON Schema.org/Recipe"""
    client = _get_client(api_url, api_key)

    content: List[Dict[str, Any]] = [
        {"type": "text", "text": RECIPE_SYSTEM_PROMPT + "\n\nAnalyse le(s) image(s) suivante(s) contenant une recette de cuisine (photo, capture, page scannée) et structure-la."}
    ]

    for path in image_paths:
        with open(path, "rb") as f:
            b64 = base64.b64encode(f.read()).decode("utf-8")
        content.append({
            "type": "image_url",
            "image_url": {"url": f"data:image/jpeg;base64,{b64}"}
        })

    res = client.chat.completions.create(
        model=model,
        messages=[{"role": "user", "content": content}],
        temperature=0.2,
    )
    raw_content = res.choices[0].message.content
    return clean_json_response(raw_content)


SOCIAL_SYSTEM_PROMPT = (
    "Tu es un expert culinaire et structurateur de données Schema.org, spécialisé dans l'analyse de posts de réseaux sociaux "
    "(Instagram, TikTok, Facebook, Threads, Pinterest...). Une recette peut être décrite dans la légende (caption) du post, "
    "MAIS elle peut aussi être partiellement ou entièrement écrite/visible directement DANS les images/photos fournies "
    "(carrousel de photos, texte incrusté sur l'image, liste d'ingrédients ou étapes écrites à la main ou en légende sur la photo). "
    "Analyse ATTENTIVEMENT la légende texte ET chaque image fournie. Un carrousel peut contenir une seule recette répartie "
    "sur plusieurs images, OU plusieurs recettes distinctes (par exemple une recette par image). Identifie chaque recette complète "
    "sans découper artificiellement une même recette, puis génère un objet JSON contenant une clé 'recipes' avec un tableau d'un "
    "ou plusieurs objets STRICTEMENT conformes au standard schema.org/Recipe.\n"
    "RÈGLE PRIORITAIRE : traduis et rédige TOUJOURS toutes les recettes en français, quelle que soit la langue de la légende ou des images. "
    "Le titre, la description, les ingrédients, les étapes, la catégorie et tous les textes destinés à l'utilisateur doivent être en français.\n"
    "Champs requis dans chaque recette du tableau :\n"
    "- @context: 'https://schema.org'\n"
    "- @type: 'Recipe'\n"
    "- name: titre de la recette\n"
    "- description: court résumé\n"
    "- prepTime: format ISO 8601 (ex: PT15M)\n"
    "- cookTime: format ISO 8601 (ex: PT30M)\n"
    "- totalTime: format ISO 8601 (ex: PT45M)\n"
    "- recipeYield: nombre de portions (ex: '4 personnes')\n"
    "- recipeCategory: catégorie (Plat principal, Entrée, Dessert, etc.)\n"
    "- recipeCuisine: origine géographique\n"
    "- recipeIngredient: tableau de chaînes avec quantités et ingrédients\n"
    "- recipeInstructions: tableau d'objets HowToStep avec propriété 'text'\n"
    "- sourceImageIndex: numéro (à partir de 1) de l'image représentant le mieux cette recette, uniquement si des images sont fournies\n"
    "IMPORTANT : n'invente JAMAIS prepTime, cookTime ou totalTime. Si ces durées ne sont pas explicitement indiquées "
    "dans la légende ou visibles sur les images, laisse le champ correspondant à null. Ne fais aucune estimation.\n"
    "Format de réponse obligatoire : {\"recipes\": [{...}, {...}]}. "
    "Ne réponds QUE par le JSON brut, sans backticks markdown ni texte d'accompagnement."
)


def extract_recipe_from_social_post(
    caption_text: str,
    image_paths: List[str],
    api_url: str,
    api_key: str,
    model: str,
) -> List[Dict[str, Any]]:
    """
    Utilise le provider IA 'Vision' pour analyser conjointement la légende texte d'un post de réseau social
    et ses photos (carrousel inclus), la recette pouvant être répartie entre le texte et les images.
    """
    client = _get_client(api_url, api_key)

    intro = "Voici la légende (caption) du post :\n\n" + (caption_text or "(légende vide ou non disponible)")
    if image_paths:
        intro += f"\n\nVoici également {len(image_paths)} photo(s) issue(s) du post à analyser attentivement (la recette peut y être écrite)."

    content: List[Dict[str, Any]] = [
        {"type": "text", "text": SOCIAL_SYSTEM_PROMPT + "\n\n" + intro}
    ]

    for path in image_paths:
        with open(path, "rb") as f:
            b64 = base64.b64encode(f.read()).decode("utf-8")
        content.append({
            "type": "image_url",
            "image_url": {"url": f"data:image/jpeg;base64,{b64}"}
        })

    res = client.chat.completions.create(
        model=model,
        messages=[{"role": "user", "content": content}],
        temperature=0.2,
    )
    raw_content = res.choices[0].message.content
    parsed = clean_json_response(raw_content)
    if isinstance(parsed, dict) and isinstance(parsed.get("recipes"), list):
        recipes = parsed["recipes"]
    elif isinstance(parsed, list):
        recipes = parsed
    elif isinstance(parsed, dict):
        recipes = [parsed]
    else:
        raise ValueError("La réponse IA ne contient aucune recette exploitable.")

    valid_recipes = [recipe for recipe in recipes if isinstance(recipe, dict) and recipe.get("name")]
    if not valid_recipes:
        raise ValueError("La réponse IA ne contient aucune recette nommée.")
    return valid_recipes


def transcribe_audio(audio_path: str, api_url: str, api_key: str, model: str) -> str:
    """Utilise le provider IA 'Transcription' (compatible Whisper) pour convertir un fichier audio en texte"""
    client = _get_client(api_url, api_key)
    with open(audio_path, "rb") as f:
        transcript = client.audio.transcriptions.create(
            model=model,
            file=f,
        )
    return transcript.text if hasattr(transcript, "text") else str(transcript)


def ping_ai_endpoint(api_url: str, api_key: str) -> Dict[str, Any]:
    """Vérifie qu'un endpoint compatible OpenAI est joignable (liste des modèles disponibles)"""
    import requests as req

    if not api_url:
        raise AiConfigError("URL de l'API non renseignée.")

    headers = {}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"

    resp = req.get(f"{api_url.rstrip('/')}/models", headers=headers, timeout=10)
    resp.raise_for_status()
    return resp.json()
