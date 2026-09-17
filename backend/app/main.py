from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.routers import recipes, imports, settings as settings_router

app = FastAPI(
    title=settings.APP_NAME,
    version="1.0.0",
    description="Backend API Recifood - Gestionnaire de recettes conforme à https://schema.org/Recipe"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Inclusion des routeurs
app.include_router(recipes.router)
app.include_router(imports.router)
app.include_router(settings_router.router)

@app.get("/")
def root():
    return {
        "app": settings.APP_NAME,
        "status": "online",
        "schema_standard": "https://schema.org/Recipe",
        "docs": "/docs"
    }

@app.get("/health")
def healthcheck():
    return {"status": "healthy"}
