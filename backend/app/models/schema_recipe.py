from typing import List, Optional, Union, Dict, Any
from pydantic import BaseModel, Field

class Author(BaseModel):
    type: str = Field(default="Person", alias="@type")
    name: str

class NutritionInformation(BaseModel):
    type: str = Field(default="NutritionInformation", alias="@type")
    calories: Optional[str] = None
    carbohydrateContent: Optional[str] = None
    fatContent: Optional[str] = None
    proteinContent: Optional[str] = None
    sugarContent: Optional[str] = None
    fiberContent: Optional[str] = None

class AggregateRating(BaseModel):
    type: str = Field(default="AggregateRating", alias="@type")
    ratingValue: Union[float, str]
    reviewCount: Optional[Union[int, str]] = None
    bestRating: Optional[Union[int, str]] = "5"
    worstRating: Optional[Union[int, str]] = "1"

class HowToStep(BaseModel):
    type: str = Field(default="HowToStep", alias="@type")
    name: Optional[str] = None
    text: str
    url: Optional[str] = None
    image: Optional[str] = None

class SchemaRecipe(BaseModel):
    """
    Modèle strictement conforme à https://schema.org/Recipe
    """
    context: str = Field(default="https://schema.org", alias="@context")
    type: str = Field(default="Recipe", alias="@type")
    name: str
    description: Optional[str] = None
    image: Optional[Union[List[str], str]] = None
    author: Optional[Union[Author, str, Dict[str, Any]]] = None
    datePublished: Optional[str] = None
    prepTime: Optional[str] = None # Ex: PT15M
    cookTime: Optional[str] = None # Ex: PT30M
    totalTime: Optional[str] = None # Ex: PT45M
    recipeYield: Optional[Union[str, int, List[str]]] = None
    recipeCategory: Optional[Union[str, List[str]]] = None
    recipeCuisine: Optional[Union[str, List[str]]] = None
    keywords: Optional[Union[str, List[str]]] = None
    recipeIngredient: List[str] = Field(default_factory=list)
    recipeInstructions: List[Union[HowToStep, Dict[str, Any], str]] = Field(default_factory=list)
    nutrition: Optional[Union[NutritionInformation, Dict[str, Any]]] = None
    aggregateRating: Optional[Union[AggregateRating, Dict[str, Any]]] = None
    video: Optional[Dict[str, Any]] = None
    url: Optional[str] = None

    class Config:
        populate_by_name = True
        extra = "allow"
