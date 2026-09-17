from fastapi import APIRouter
from pydantic import BaseModel
from typing import Optional

from app.services.ai_client import ping_ai_endpoint, AiConfigError

router = APIRouter(prefix="/api/settings", tags=["Paramètres"])


class TestAiRequest(BaseModel):
    api_url: str
    api_key: Optional[str] = ""
    model: Optional[str] = ""


@router.post("/test-ai")
def test_ai_connection(req: TestAiRequest):
    """
    Vérifie qu'un endpoint IA compatible OpenAI (Texte, Vision ou Transcription) est joignable
    en interrogeant la liste des modèles disponibles (GET /models).
    """
    try:
        result = ping_ai_endpoint(req.api_url, req.api_key or "")
        model_count = len(result.get("data", [])) if isinstance(result, dict) else None
        return {
            "success": True,
            "message": f"Connexion réussie ({model_count} modèle(s) détecté(s))" if model_count is not None else "Connexion réussie",
        }
    except AiConfigError as e:
        return {"success": False, "error": str(e)}
    except Exception as e:
        return {"success": False, "error": f"Impossible de joindre l'API : {str(e)}"}


@router.get("/ytdlp-version")
def get_ytdlp_version():
    try:
        import yt_dlp
        return {"installed": True, "version": yt_dlp.version.__version__}
    except Exception as e:
        return {"installed": False, "error": str(e)}
