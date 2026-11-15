using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Donnée d'un vêtement/accessoire (ScriptableObject) : on ajoute un vêtement en créant un asset,
    /// sans toucher au code. Le prefab n'est instancié que lorsque l'objet est équipé.
    /// </summary>
    [CreateAssetMenu(menuName = "Taxi Kin/Vêtement", fileName = "Clothing_")]
    public class ClothingItem : ScriptableObject
    {
        public string id;
        public string displayName;
        public ClothingSlot slot;
        public GameObject prefab;
        public bool tintable = true;     // La couleur du vêtement peut-elle être changée ?
        public int priceKinCoins;
        public int priceKinGold;         // > 0 : article premium
        public bool unlockedByDefault = true;
    }
}
