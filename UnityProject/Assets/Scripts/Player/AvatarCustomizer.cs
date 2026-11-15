using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Applique un AvatarData sur le personnage 3D (cubes/capsules pour l'instant).
    /// Place le script sur la racine de l'avatar. Il peut être réutilisé dans le menu, le garage,
    /// les missions, les courses, les écrans de victoire et les classements.
    /// Seuls les vêtements équipés sont instanciés (jamais tout le catalogue).
    /// </summary>
    public class AvatarCustomizer : MonoBehaviour
    {
        [System.Serializable]
        public struct SlotAnchor { public ClothingSlot slot; public Transform anchor; }

        [SerializeField] private Renderer skinRenderer;
        [SerializeField] private List<SlotAnchor> anchors = new List<SlotAnchor>();

        private static readonly int BaseColorId = Shader.PropertyToID("_BaseColor");
        private static readonly int ColorId = Shader.PropertyToID("_Color");
        private readonly Dictionary<ClothingSlot, GameObject> equipped = new Dictionary<ClothingSlot, GameObject>();
        private MaterialPropertyBlock block;

        /// <summary>Applique la couleur de peau (les vêtements sont équipés via Equip).</summary>
        public void Apply(AvatarData data)
        {
            if (data == null) return;
            Tint(skinRenderer, data.skinColor);
        }

        /// <summary>Équipe un vêtement (remplace celui du même emplacement).</summary>
        public void Equip(ClothingItem item, Color tint)
        {
            if (item == null || item.prefab == null) return;
            Unequip(item.slot);
            Transform anchor = FindAnchor(item.slot);
            if (anchor == null) return;

            var instance = Instantiate(item.prefab, anchor);
            equipped[item.slot] = instance;
            if (item.tintable)
                foreach (var r in instance.GetComponentsInChildren<Renderer>()) Tint(r, tint);
        }

        public void Unequip(ClothingSlot slot)
        {
            if (equipped.TryGetValue(slot, out var go) && go != null) Destroy(go);
            equipped.Remove(slot);
        }

        private Transform FindAnchor(ClothingSlot slot)
        {
            foreach (var a in anchors) if (a.slot == slot) return a.anchor;
            return null;
        }

        private void Tint(Renderer target, Color color)
        {
            if (target == null) return;
            block ??= new MaterialPropertyBlock();
            target.GetPropertyBlock(block);
            block.SetColor(BaseColorId, color);
            block.SetColor(ColorId, color);
            target.SetPropertyBlock(block);
        }
    }
}
