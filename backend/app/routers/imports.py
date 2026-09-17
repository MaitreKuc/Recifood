import os
import json
import tempfile
import shutil
from typing import Optional, Dict, Any, List

import requests
from bs4 import BeautifulSoup
from fastapi import APIRouter, HTTPException, UploadFile, File, Form
from pydantic import BaseModel

from app.services.ai_client import (
    extract_recipe_from_text,
    extract_recipe_from_images,
    extract_recipe_from_social_post,
    transcribe_audio,
    imagine_recipe_from_prompt,
    AiConfigError,
)

router = APIRouter(prefix="/api/import", tags=["Importations"])

COOKIES_FILE_PATH = os.getenv("COOKIES_FILE_PATH", "/data/cookies/cookies.txt")


class UrlImportRequest(BaseModel):
    url: str


class VideoImportRequest(BaseModel):
    url: str
    settings: Optional[Dict[str, Any]] = None


class SocialImportRequest(BaseModel):
    url: str
    settings: Optional[Dict[str, Any]] = None


class TextImportRequest(BaseModel):
    text: str
    settings: Optional[Dict[str, Any]] = None


class ImagineRequest(BaseModel):
    prompt: str
    settings: Optional[Dict[str, Any]] = None


def normalize_recipe_jsonld(data: Dict[str, Any]) -> Dict[str, Any]:
    """Nettoie et normalise l'objet pour être un standard Schema.org Recipe propre"""
    data["@context"] = "https://schema.org"
    data["@type"] = "Recipe"

    if "recipeIngredient" in data and isinstance(data["recipeIngredient"], list):
        cleaned_ing = []
        for ing in data["recipeIngredient"]:
            if isinstance(ing, str):
                cleaned_ing.append(ing.strip())
            elif isinstance(ing, dict) and "name" in ing:
                cleaned_ing.append(str(ing["name"]).strip())
        data["recipeIngredient"] = cleaned_ing

    if "recipeInstructions" in data and isinstance(data["recipeInstructions"], list):
        cleaned_inst = []
        for step in data["recipeInstructions"]:
            if isinstance(step, str):
                cleaned_inst.append({"@type": "HowToStep", "text": step.strip()})
            elif isinstance(step, dict):
                txt = step.get("text") or step.get("description") or step.get("name") or ""
                cleaned_inst.append({
                    "@type": "HowToStep",
                    "name": step.get("name", ""),
                    "text": str(txt).strip()
                })
        data["recipeInstructions"] = cleaned_inst

    return data


def find_recipe_in_json(data: Any) -> Optional[Dict[str, Any]]:
    if not isinstance(data, (dict, list)):
        return None

    if isinstance(data, dict):
        t = data.get("@type") or data.get("type")
        if t == "Recipe" or (isinstance(t, list) and "Recipe" in t):
            return data

        if "@graph" in data and isinstance(data["@graph"], list):
            for item in data["@graph"]:
                found = find_recipe_in_json(item)
                if found:
                    return found

        for v in data.values():
            if isinstance(v, (dict, list)):
                found = find_recipe_in_json(v)
                if found:
                    return found

    elif isinstance(data, list):
        for item in data:
            found = find_recipe_in_json(item)
            if found:
                return found

    return None


@router.post("/url")
def import_from_url(req: UrlImportRequest):
    """
    Extrait automatiquement la balise JSON-LD schema.org/Recipe d'une page Web
    """
    url = req.url.strip()
    headers = {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept-Language": "fr-FR,fr;q=0.9,en;q=0.8"
    }

    try:
        resp = requests.get(url, headers=headers, timeout=12)
        resp.raise_for_status()
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Erreur lors de la récupération de l'URL : {str(e)}")

    soup = BeautifulSoup(resp.text, "html.parser")
    scripts = soup.find_all("script", type="application/ld+json")

    for s in scripts:
        try:
            content = s.string or s.text
            if not content:
                continue
            parsed = json.loads(content)
            recipe_obj = find_recipe_in_json(parsed)
            if recipe_obj:
                recipe_obj = normalize_recipe_jsonld(recipe_obj)
                if not recipe_obj.get("url"):
                    recipe_obj["url"] = url
                return {"success": True, "recipe": recipe_obj}
        except Exception:
            continue

    raise HTTPException(status_code=404, detail="Aucune balise schema.org/Recipe trouvée sur cette page.")


@router.post("/text")
def import_from_text(req: TextImportRequest):
    """
    Utilise le provider IA 'Texte' configuré par l'utilisateur pour structurer le texte en schema.org/Recipe
    """
    text = req.text.strip()
    settings = req.settings or {}

    api_url = settings.get("ai_text_api_url", "")
    api_key = settings.get("ai_text_api_key", "")
    model = settings.get("ai_text_model", "")

    if not api_url or not model:
        raise HTTPException(
            status_code=400,
            detail="Le provider IA 'Texte' n'est pas configuré. Renseignez l'URL API et le modèle dans les Paramètres."
        )

    try:
        recipe_obj = extract_recipe_from_text(text, api_url, api_key, model)
        recipe_obj = normalize_recipe_jsonld(recipe_obj)
        return {"success": True, "recipe": recipe_obj}
    except AiConfigError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors de la structuration IA (Texte) : {str(e)}")


@router.post("/imagine")
def imagine_recipe(req: ImagineRequest):
    """
    Utilise le provider IA 'Texte' pour IMAGINER/INVENTER une recette à partir d'un prompt libre
    """
    prompt = req.prompt.strip()
    settings = req.settings or {}

    if not prompt:
        raise HTTPException(status_code=400, detail="Merci de décrire l'idée de recette que vous souhaitez imaginer.")

    api_url = settings.get("ai_text_api_url", "")
    api_key = settings.get("ai_text_api_key", "")
    model = settings.get("ai_text_model", "")

    if not api_url or not model:
        raise HTTPException(
            status_code=400,
            detail="Le provider IA 'Texte' n'est pas configuré. Renseignez l'URL API et le modèle dans les Paramètres."
        )

    try:
        recipe_obj = imagine_recipe_from_prompt(prompt, api_url, api_key, model)
        recipe_obj = normalize_recipe_jsonld(recipe_obj)
        return {"success": True, "recipe": recipe_obj}
    except AiConfigError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors de la génération IA de la recette : {str(e)}")


@router.post("/video")
def import_from_video(req: VideoImportRequest):
    """
    Télécharge l'audio via yt-dlp, transcrit via le provider IA 'Transcription',
    puis structure le texte obtenu via le provider IA 'Texte'.
    """
    url = req.url.strip()
    settings = req.settings or {}

    trans_api_url = settings.get("ai_transcription_api_url", "")
    trans_api_key = settings.get("ai_transcription_api_key", "")
    trans_model = settings.get("ai_transcription_model", "")

    text_api_url = settings.get("ai_text_api_url", "")
    text_api_key = settings.get("ai_text_api_key", "")
    text_model = settings.get("ai_text_model", "")

    if not text_api_url or not text_model:
        raise HTTPException(
            status_code=400,
            detail="Le provider IA 'Texte' n'est pas configuré. Renseignez l'URL API et le modèle dans les Paramètres."
        )

    tmp_dir = tempfile.mkdtemp(prefix="recifood_video_")
    title = ""
    description = ""
    thumbnail = ""
    tags: List[str] = []
    transcript_text = ""

    try:
        import yt_dlp

        ydl_opts_info = {
            "skip_download": True,
            "quiet": True,
            "no_warnings": True,
        }
        if os.path.exists(COOKIES_FILE_PATH):
            ydl_opts_info["cookiefile"] = COOKIES_FILE_PATH

        with yt_dlp.YoutubeDL(ydl_opts_info) as ydl:
            info = ydl.extract_info(url, download=False)
            title = info.get("title", "")
            description = info.get("description", "")
            thumbnail = info.get("thumbnail", "")
            tags = info.get("tags", []) or []

        # Téléchargement et transcription de l'audio si un provider de transcription est configuré
        if trans_api_url and trans_model:
            audio_path_template = os.path.join(tmp_dir, "audio.%(ext)s")
            ydl_opts_audio = {
                "format": "bestaudio/best",
                "outtmpl": audio_path_template,
                "quiet": True,
                "no_warnings": True,
                "postprocessors": [{
                    "key": "FFmpegExtractAudio",
                    "preferredcodec": "mp3",
                    "preferredquality": "128",
                }],
            }
            if os.path.exists(COOKIES_FILE_PATH):
                ydl_opts_audio["cookiefile"] = COOKIES_FILE_PATH

            with yt_dlp.YoutubeDL(ydl_opts_audio) as ydl:
                ydl.download([url])

            audio_file = os.path.join(tmp_dir, "audio.mp3")
            if os.path.exists(audio_file):
                try:
                    transcript_text = transcribe_audio(audio_file, trans_api_url, trans_api_key, trans_model)
                except Exception:
                    transcript_text = ""  # On continue avec titre/description seulement

        text_to_parse = f"Titre de la vidéo : {title}\n\nDescription :\n{description}\n\nTags : {', '.join(tags)}"
        if transcript_text:
            text_to_parse += f"\n\nTranscription audio de la vidéo :\n{transcript_text}"

        recipe_obj = extract_recipe_from_text(text_to_parse, text_api_url, text_api_key, text_model)
        recipe_obj = normalize_recipe_jsonld(recipe_obj)

        if thumbnail and not recipe_obj.get("image"):
            recipe_obj["image"] = [thumbnail]
        recipe_obj["video"] = {
            "@type": "VideoObject",
            "name": title,
            "embedUrl": url,
            "thumbnailUrl": thumbnail
        }
        recipe_obj["url"] = url

        return {"success": True, "recipe": recipe_obj}

    except AiConfigError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur yt-dlp / IA : {str(e)}")
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)


def _scrape_social_meta_via_html(url: str) -> Dict[str, Any]:
    """
    Filet de sécurité quand yt-dlp échoue (ex: posts photo-only Instagram/Facebook sans piste vidéo).
    Récupère les balises Open Graph (og:title, og:description, og:image) de la page, en se faisant passer
    pour le robot d'exploration de Facebook (technique standard pour obtenir les balises og: sans authentification).
    """
    headers_variants = [
        {"User-Agent": "facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)"},
        {
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            "Accept-Language": "fr-FR,fr;q=0.9,en;q=0.8"
        },
    ]

    for headers in headers_variants:
        try:
            resp = requests.get(url, headers=headers, timeout=12)
            resp.raise_for_status()
        except Exception:
            continue

        soup = BeautifulSoup(resp.text, "html.parser")
        og_title_tag = soup.find("meta", property="og:title")
        og_desc_tag = soup.find("meta", property="og:description")
        og_image_tags = soup.find_all("meta", property="og:image")

        title = og_title_tag.get("content", "") if og_title_tag else ""
        description = og_desc_tag.get("content", "") if og_desc_tag else ""
        image_urls = [t.get("content", "") for t in og_image_tags if t.get("content")]

        if title or description or image_urls:
            return {"title": title, "description": description, "image_urls": image_urls}

    return {"title": "", "description": "", "image_urls": []}


@router.post("/social")
def import_from_social(req: SocialImportRequest):
    """
    Importe une recette depuis un post de réseau social (Instagram, TikTok, Facebook, Threads, Pinterest...).
    Contrairement à /video, la recette peut être écrite dans la légende (texte) ET/OU directement
    visible sur les photos du post (carrousel d'images, texte incrusté sur l'image...).
    - Si le post contient une vraie vidéo : l'audio est transcrit (comme pour /video).
    - Les photos du post (miniature, carrousel) sont récupérées et analysées par le provider IA 'Vision'
      conjointement avec la légende texte, pour ne rien manquer même si la recette n'est que sur les images.
    - Si aucun provider 'Vision' n'est configuré, on se rabat sur le provider 'Texte' (légende seule).
    - Si yt-dlp échoue (fréquent sur les posts "photo-only" sans piste vidéo), on se rabat automatiquement
      sur une extraction des balises Open Graph (og:title/og:description/og:image) de la page du post.
    """
    url = req.url.strip()
    settings = req.settings or {}

    trans_api_url = settings.get("ai_transcription_api_url", "")
    trans_api_key = settings.get("ai_transcription_api_key", "")
    trans_model = settings.get("ai_transcription_model", "")

    text_api_url = settings.get("ai_text_api_url", "")
    text_api_key = settings.get("ai_text_api_key", "")
    text_model = settings.get("ai_text_model", "")

    vision_api_url = settings.get("ai_vision_api_url", "")
    vision_api_key = settings.get("ai_vision_api_key", "")
    vision_model = settings.get("ai_vision_model", "")

    if (not vision_api_url or not vision_model) and (not text_api_url or not text_model):
        raise HTTPException(
            status_code=400,
            detail="Aucun provider IA n'est configuré. Renseignez au moins le provider 'Vision' (recommandé pour les posts) "
                   "ou 'Texte' dans les Paramètres."
        )

    tmp_dir = tempfile.mkdtemp(prefix="recifood_social_")
    title = ""
    description = ""
    tags: List[str] = []
    transcript_text = ""
    has_real_video = False
    candidate_image_urls: List[str] = []

    def collect_images(node: Dict[str, Any]):
        # Une "entry" (photo ou vidéo du carrousel) expose souvent la même image en plusieurs résolutions
        # (miniature, moyenne, pleine taille) : on ne garde que la meilleure pour éviter d'envoyer
        # plusieurs fois la même photo à l'IA Vision (gaspillage de temps/coût).
        best_url = node.get("thumbnail")
        if not best_url:
            thumbs = node.get("thumbnails") or []
            if thumbs:
                best_url = thumbs[-1].get("url")
        if best_url and best_url not in candidate_image_urls:
            candidate_image_urls.append(best_url)
        # Certains posts photo exposent l'image en pleine résolution via 'url' directement (ext image)
        node_ext = (node.get("ext") or "").lower()
        node_url = node.get("url")
        if node_url and node_ext in ("jpg", "jpeg", "png", "webp") and node_url not in candidate_image_urls:
            candidate_image_urls.append(node_url)

    def node_has_video(node: Dict[str, Any]) -> bool:
        if (node.get("duration") or 0) > 0:
            return True
        for fmt in (node.get("formats") or []):
            if fmt.get("vcodec") and fmt.get("vcodec") != "none":
                return True
        return False

    try:
        import yt_dlp

        ydl_opts_info = {
            "skip_download": True,
            "quiet": True,
            "no_warnings": True,
        }
        if os.path.exists(COOKIES_FILE_PATH):
            ydl_opts_info["cookiefile"] = COOKIES_FILE_PATH

        info = None
        try:
            with yt_dlp.YoutubeDL(ydl_opts_info) as ydl:
                info = ydl.extract_info(url, download=False)
        except Exception:
            # Fréquent sur les posts "photo-only" ou carrousels (Instagram/Facebook) : l'extracteur
            # attend une piste vidéo et échoue durement dès qu'une entrée du carrousel n'en a pas.
            # On retente alors en mode "tolérant" : les erreurs de format/téléchargement sont ignorées
            # et chaque entrée du carrousel est traitée "à plat", ce qui permet de quand même récupérer
            # le titre, la légende et surtout LA MINIATURE DE CHAQUE PHOTO du carrousel (pas uniquement
            # la première), sans jamais tenter de résoudre un flux vidéo inexistant.
            try:
                ydl_opts_tolerant = dict(ydl_opts_info)
                ydl_opts_tolerant["ignoreerrors"] = "only_download"
                ydl_opts_tolerant["extract_flat"] = "discard_in_playlist"
                with yt_dlp.YoutubeDL(ydl_opts_tolerant) as ydl:
                    info = ydl.extract_info(url, download=False)
            except Exception:
                info = None

        if info is not None:
            title = info.get("title", "") or ""
            description = info.get("description", "") or ""
            tags = info.get("tags", []) or []

            # Post simple OU carrousel (plusieurs entrées "entries" pour les posts multi-photos/vidéos)
            entries = info.get("entries") or [info]
            for entry in entries:
                if not isinstance(entry, dict):
                    continue
                collect_images(entry)
                if node_has_video(entry):
                    has_real_video = True

        if not candidate_image_urls and not description:
            # Filet de sécurité : yt-dlp n'a rien retourné d'exploitable (ex: post photo-only).
            fallback = _scrape_social_meta_via_html(url)
            title = title or fallback["title"]
            description = description or fallback["description"]
            for img_url in fallback["image_urls"]:
                if img_url not in candidate_image_urls:
                    candidate_image_urls.append(img_url)

        if not candidate_image_urls and not description and not title:
            raise HTTPException(
                status_code=422,
                detail="Impossible de récupérer le contenu de ce post (légende et photos introuvables). "
                       "Le post est peut-être privé ou nécessite une connexion."
            )

        # Limite raisonnable du nombre d'images envoyées à l'IA Vision (coût / temps de traitement)
        candidate_image_urls = candidate_image_urls[:6]

        # Téléchargement de l'audio + transcription si le post contient une vraie vidéo
        if has_real_video and trans_api_url and trans_model:
            audio_path_template = os.path.join(tmp_dir, "audio.%(ext)s")
            ydl_opts_audio = {
                "format": "bestaudio/best",
                "outtmpl": audio_path_template,
                "quiet": True,
                "no_warnings": True,
                "postprocessors": [{
                    "key": "FFmpegExtractAudio",
                    "preferredcodec": "mp3",
                    "preferredquality": "128",
                }],
            }
            if os.path.exists(COOKIES_FILE_PATH):
                ydl_opts_audio["cookiefile"] = COOKIES_FILE_PATH

            try:
                with yt_dlp.YoutubeDL(ydl_opts_audio) as ydl:
                    ydl.download([url])
                audio_file = os.path.join(tmp_dir, "audio.mp3")
                if os.path.exists(audio_file):
                    transcript_text = transcribe_audio(audio_file, trans_api_url, trans_api_key, trans_model)
            except Exception:
                transcript_text = ""  # On continue avec légende/photos seulement

        # Téléchargement local des photos du post (jusqu'à 4) pour analyse par l'IA Vision
        image_paths: List[str] = []
        if vision_api_url and vision_model:
            headers = {
                "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
            }
            for idx, img_url in enumerate(candidate_image_urls[:4]):
                try:
                    r = requests.get(img_url, headers=headers, timeout=15)
                    r.raise_for_status()
                    img_path = os.path.join(tmp_dir, f"social_{idx}.jpg")
                    with open(img_path, "wb") as f:
                        f.write(r.content)
                    image_paths.append(img_path)
                except Exception:
                    continue  # On ignore les images inaccessibles et on continue avec les autres

        caption_text = f"Titre du post : {title}\n\nLégende / Description :\n{description}\n\nTags : {', '.join(tags)}"
        if transcript_text:
            caption_text += f"\n\nTranscription audio de la vidéo du post :\n{transcript_text}"

        if vision_api_url and vision_model and image_paths:
            recipe_obj = extract_recipe_from_social_post(caption_text, image_paths, vision_api_url, vision_api_key, vision_model)
        elif text_api_url and text_model:
            recipe_obj = extract_recipe_from_text(caption_text, text_api_url, text_api_key, text_model)
        else:
            raise HTTPException(
                status_code=400,
                detail="Le provider IA 'Vision' n'est pas configuré et les photos du post n'ont pas pu être analysées. "
                       "Configurez le provider 'Vision' dans les Paramètres pour analyser les recettes visibles sur les photos."
            )

        recipe_obj = normalize_recipe_jsonld(recipe_obj)

        if not recipe_obj.get("image") and candidate_image_urls:
            recipe_obj["image"] = [candidate_image_urls[0]]

        if has_real_video:
            recipe_obj["video"] = {
                "@type": "VideoObject",
                "name": title,
                "embedUrl": url,
                "thumbnailUrl": candidate_image_urls[0] if candidate_image_urls else ""
            }
        recipe_obj["url"] = url

        return {"success": True, "recipe": recipe_obj}

    except HTTPException:
        raise
    except AiConfigError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors de l'analyse du post : {str(e)}")
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)


@router.post("/auto")
def import_from_auto(req: SocialImportRequest):
    """
    Point d'entrée unique "Site Web" : détecte automatiquement la meilleure stratégie d'extraction
    pour une adresse quelconque, en essayant successivement (du plus fiable/rapide au plus coûteux) :
    1. Balisage Schema.org/microdata (page de recette classique : Marmiton, 750g, blogs culinaires...)
    2. Vidéo via yt-dlp + transcription audio + IA Texte (YouTube, Vimeo, vidéo TikTok/Reels simple...)
    3. Post de réseau social (carrousel photo + légende) via IA Vision/Texte (Instagram, Facebook, Threads,
       Pinterest... y compris les posts où la recette n'est écrite que sur les photos).
    Chaque étape n'est tentée que si la précédente échoue, afin de ne consommer de l'IA/du temps
    que lorsque c'est nécessaire.
    """
    url = req.url.strip()
    settings = req.settings or {}
    step_errors: List[str] = []

    # 1. Schema.org / microdata (gratuit, rapide, aucune IA nécessaire)
    try:
        result = import_from_url(UrlImportRequest(url=url))
        result["detected_source"] = "schema"
        return result
    except HTTPException as e:
        step_errors.append(f"Page Web standard : {e.detail}")

    # 2. Vidéo (yt-dlp + transcription + IA Texte)
    try:
        result = import_from_video(VideoImportRequest(url=url, settings=settings))
        result["detected_source"] = "video"
        return result
    except HTTPException as e:
        step_errors.append(f"Vidéo : {e.detail}")

    # 3. Post de réseau social (carrousel photo + IA Vision, avec repli Open Graph)
    try:
        result = import_from_social(SocialImportRequest(url=url, settings=settings))
        result["detected_source"] = "social"
        return result
    except HTTPException as e:
        step_errors.append(f"Réseau social : {e.detail}")

    raise HTTPException(
        status_code=422,
        detail="Impossible d'extraire une recette de cette adresse, quelle que soit la méthode essayée. "
               + " — ".join(step_errors)
    )


@router.post("/file")
async def import_from_file(file: UploadFile = File(...), settings: str = Form("{}")):
    """
    Importe une recette à partir d'une image (photo/capture) ou d'un PDF.
    - Image : envoyée directement au provider IA 'Vision'.
    - PDF texte : texte extrait puis envoyé au provider IA 'Texte'.
    - PDF scanné (sans texte) : pages converties en images puis envoyées au provider IA 'Vision'.
    """
    try:
        settings_dict = json.loads(settings) if settings else {}
    except Exception:
        settings_dict = {}

    vision_api_url = settings_dict.get("ai_vision_api_url", "")
    vision_api_key = settings_dict.get("ai_vision_api_key", "")
    vision_model = settings_dict.get("ai_vision_model", "")

    text_api_url = settings_dict.get("ai_text_api_url", "")
    text_api_key = settings_dict.get("ai_text_api_key", "")
    text_model = settings_dict.get("ai_text_model", "")

    filename = file.filename or "upload"
    ext = os.path.splitext(filename)[1].lower()
    tmp_dir = tempfile.mkdtemp(prefix="recifood_file_")

    try:
        content = await file.read()
        input_path = os.path.join(tmp_dir, filename)
        with open(input_path, "wb") as f:
            f.write(content)

        recipe_obj: Optional[Dict[str, Any]] = None

        if ext == ".pdf":
            # 1. Tentative d'extraction de texte natif du PDF
            extracted_text = ""
            try:
                from pypdf import PdfReader
                reader = PdfReader(input_path)
                for page in reader.pages:
                    extracted_text += (page.extract_text() or "") + "\n"
            except Exception:
                extracted_text = ""

            if len(extracted_text.strip()) > 200:
                if not text_api_url or not text_model:
                    raise HTTPException(status_code=400, detail="Le provider IA 'Texte' n'est pas configuré (nécessaire pour ce PDF).")
                recipe_obj = extract_recipe_from_text(extracted_text, text_api_url, text_api_key, text_model)
            else:
                # 2. PDF scanné : conversion des premières pages en images pour le provider Vision
                if not vision_api_url or not vision_model:
                    raise HTTPException(status_code=400, detail="Le provider IA 'Vision' n'est pas configuré (nécessaire pour ce PDF scanné).")
                from pdf2image import convert_from_path
                images = convert_from_path(input_path, dpi=200, first_page=1, last_page=5)
                image_paths = []
                for idx, img in enumerate(images):
                    img_path = os.path.join(tmp_dir, f"page_{idx}.jpg")
                    img.convert("RGB").save(img_path, "JPEG")
                    image_paths.append(img_path)
                recipe_obj = extract_recipe_from_images(image_paths, vision_api_url, vision_api_key, vision_model)

        elif ext in [".jpg", ".jpeg", ".png", ".webp", ".gif", ".bmp"]:
            if not vision_api_url or not vision_model:
                raise HTTPException(status_code=400, detail="Le provider IA 'Vision' n'est pas configuré. Renseignez l'URL API et le modèle dans les Paramètres.")
            recipe_obj = extract_recipe_from_images([input_path], vision_api_url, vision_api_key, vision_model)

        else:
            raise HTTPException(status_code=400, detail=f"Format de fichier non supporté : {ext}")

        if not recipe_obj:
            raise HTTPException(status_code=422, detail="Aucune recette n'a pu être extraite du fichier fourni.")

        recipe_obj = normalize_recipe_jsonld(recipe_obj)
        return {"success": True, "recipe": recipe_obj}

    except HTTPException:
        raise
    except AiConfigError as e:
        raise HTTPException(status_code=400, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur lors du traitement du fichier : {str(e)}")
    finally:
        shutil.rmtree(tmp_dir, ignore_errors=True)
