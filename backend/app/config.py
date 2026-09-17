import os
from pydantic_settings import BaseSettings

class Settings(BaseSettings):
    APP_NAME: str = "Recifood Backend API"
    APP_ENV: str = os.getenv("APP_ENV", "development")
    DATABASE_URL: str = os.getenv("DATABASE_URL", "postgresql://recifood_user:recifood_secret_2026@postgres:5432/recifood")
    CORS_ORIGINS: list = ["*"]

settings = Settings()
