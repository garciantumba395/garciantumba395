using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Fiche d'un vêtement/accessoire (ScriptableObject, très légère). Le modèle 3D n'est PAS référencé
    /// directement : il est chargé depuis Resources (prefabPath) seulement quand l'objet est équipé.
    /// Ajouter un vêtement = créer une fiche + un prefab, sans toucher au code.
    /// </summary>
    [CreateAssetMenu(menuName = "Taxi Kin/Vêtement", fileName = "Clothing_")]
    public class ClothingItem : ScriptableObject
    {
        public string id;
        public string displayName;
        public ClothingSlot slot;
        [Tooltip("Chemin Resources du prefab, sans extension (ex. Clothing/Prefabs/top_tshirt)")]
        public string prefabPath;
        public bool tintable = true;          // La couleur de vêtement peut-elle être changée ?
        public int priceKinCoins;
        public int priceKinGold;              // > 0 : article premium
        public bool unlockedByDefault = true;
        [Tooltip("Groupe de déblocage (ex. yellow_start) : posséder le groupe débloque tous ses articles")]
        public string unlockGroup;
    }
}
