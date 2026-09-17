from fastapi import APIRouter, HTTPException
from typing import List, Dict, Any
from app.models.schema_recipe import SchemaRecipe

router = APIRouter(prefix="/api/recipes", tags=["Recettes"])

@router.post("/validate")
def validate_schema_recipe(recipe: SchemaRecipe):
    """
    Valide qu'un objet est strictement conforme au modèle Schema.org/Recipe
    """
    return {
        "valid": True,
        "message": "La recette est parfaitement conforme au standard schema.org/Recipe",
        "data": recipe.model_dump(by_alias=True, exclude_none=True)
    }
