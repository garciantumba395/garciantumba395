using System;
using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Applique un AvatarData sur le personnage 3D. Réutilisable partout : aperçu « Mon chauffeur », menu,
    /// garage, missions, courses, écrans de victoire, classements.
    /// Seuls les vêtements équipés sont instanciés, et leur prefab n'est chargé qu'à ce moment-là.
    /// </summary>
    public class AvatarCustomizer : MonoBehaviour
    {
        [Serializable] public struct SlotAnchor { public ClothingSlot slot; public Transform anchor; }

        [SerializeField] private Renderer[] skinRenderers;
        [SerializeField] private Transform bodyRoot;     // Contient le corps ET les ancrages : le style les met à l'échelle ensemble
        [SerializeField] private List<SlotAnchor> anchors = new List<SlotAnchor>();

        private static readonly Vector3[] StyleScales = { new Vector3(0.9f, 1f, 0.9f), Vector3.one, new Vector3(1.15f, 1f, 1.1f) };
        private static readonly int BaseColorId = Shader.PropertyToID("_BaseColor");   // URP
        private static readonly int ColorId = Shader.PropertyToID("_Color");           // Built-in

        private readonly Dictionary<ClothingSlot, GameObject> equipped = new Dictionary<ClothingSlot, GameObject>();
        private readonly Dictionary<ClothingSlot, string> equippedIds = new Dictionary<ClothingSlot, string>();
        private MaterialPropertyBlock block;

        /// <summary>Applique l'avatar complet (peau, style, vêtements).</summary>
        public void Apply(AvatarData d)
        {
            if (d == null) return;
            if (skinRenderers != null) foreach (var r in skinRenderers) Tint(r, d.skinColor);
            if (bodyRoot != null) bodyRoot.localScale = StyleScales[Mathf.Clamp(d.style, 0, StyleScales.Length - 1)];

            Equip(ClothingSlot.Face, d.faceId, Color.white);
            Equip(ClothingSlot.Hair, d.hairId, Color.white);
            Equip(ClothingSlot.Top, d.topId, d.clothingColor);
            Equip(ClothingSlot.Bottom, d.bottomId, d.clothingColor);
            Equip(ClothingSlot.Shoes, d.shoesId, Color.white);
            string acc = d.accessoryIds != null && d.accessoryIds.Count > 0 ? d.accessoryIds[0] : null;
            Equip(ClothingSlot.Accessory, acc, Color.white);
        }

        /// <summary>Équipe un article (remplace celui de l'emplacement) ; id vide = retirer.</summary>
        public void Equip(ClothingSlot slot, string id, Color tint)
        {
            var item = AvatarCatalog.Get(id);
            if (item == null) { Unequip(slot); return; }

            bool alreadyThere = equippedIds.TryGetValue(slot, out var current) && current == id
                                && equipped.TryGetValue(slot, out var go0) && go0 != null;
            if (!alreadyThere)
            {
                Unequip(slot);
                Transform anchor = FindAnchor(slot);
                if (anchor == null) return;
                var prefab = Resources.Load<GameObject>(item.prefabPath);   // chargé à la demande
                if (prefab == null) { Debug.LogWarning($"[Avatar] Prefab introuvable : {item.prefabPath}"); return; }
                var go = Instantiate(prefab, anchor);
                go.transform.localPosition = Vector3.zero;
                go.transform.localRotation = Quaternion.identity;
                equipped[slot] = go;
                equippedIds[slot] = id;
            }

            if (item.tintable)
                foreach (var r in equipped[slot].GetComponentsInChildren<Renderer>()) Tint(r, tint);
        }

        public void Unequip(ClothingSlot slot)
        {
            if (equipped.TryGetValue(slot, out var go) && go != null) Destroy(go);
            equipped.Remove(slot);
            equippedIds.Remove(slot);
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
