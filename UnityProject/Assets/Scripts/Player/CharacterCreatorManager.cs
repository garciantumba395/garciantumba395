using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Logique de l'écran « Mon chauffeur ». Travaille sur une COPIE de l'avatar : « Enregistrer » la valide,
    /// « Annuler » la jette. L'aperçu 3D se met à jour à chaque modification.
    /// </summary>
    public class CharacterCreatorManager : MonoBehaviour
    {
        [SerializeField] private AvatarPreviewStage stage;

        public AvatarData Draft { get; private set; }
        private int outfitIndex = -1;

        public void Begin()
        {
            var profile = ProfileManager.Instance;
            Draft = profile != null ? profile.Avatar.Clone() : new AvatarData();
            outfitIndex = -1;
            Refresh();
        }

        public void Cancel() => Begin();

        private void Refresh() { if (stage != null && stage.Avatar != null) stage.Avatar.Apply(Draft); }

        // ---------- Modifications ----------

        public void SetSkin(int index)
        {
            index = Mathf.Clamp(index, 0, AvatarCatalog.SkinTones.Length - 1);
            Draft.skinIndex = index;
            Draft.skinColor = AvatarCatalog.SkinTones[index];
            Refresh();
        }

        public void NextStyle() { Draft.style = (Draft.style + 1) % 3; Refresh(); }

        public void SetClothingColor(int index)
        {
            index = Mathf.Clamp(index, 0, AvatarCatalog.ClothingColors.Length - 1);
            Draft.clothingColorIndex = index;
            Draft.clothingColor = AvatarCatalog.ClothingColors[index];
            Refresh();
        }

        public void NextClothingColor() => SetClothingColor((Draft.clothingColorIndex + 1) % AvatarCatalog.ClothingColors.Length);

        /// <summary>Passe à la tenue suivante dont tous les articles sont possédés.</summary>
        public void NextOutfit()
        {
            int n = AvatarCatalog.Outfits.Length;
            for (int step = 1; step <= n; step++)
            {
                int i = (outfitIndex + step) % n;
                var o = AvatarCatalog.Outfits[i];
                if (!IsOwned(AvatarCatalog.Get(o.top)) || !IsOwned(AvatarCatalog.Get(o.bottom)) || !IsOwned(AvatarCatalog.Get(o.shoes))) continue;
                outfitIndex = i;
                Draft.topId = o.top; Draft.bottomId = o.bottom; Draft.shoesId = o.shoes;
                SetClothingColor(o.colorIndex);
                return;
            }
        }

        public string GetItemId(ClothingSlot slot)
        {
            switch (slot)
            {
                case ClothingSlot.Face: return Draft.faceId;
                case ClothingSlot.Hair: return Draft.hairId;
                case ClothingSlot.Top: return Draft.topId;
                case ClothingSlot.Bottom: return Draft.bottomId;
                case ClothingSlot.Shoes: return Draft.shoesId;
                default: return Draft.accessoryIds.Count > 0 ? Draft.accessoryIds[0] : "";
            }
        }

        /// <summary>Choisit un article pour un emplacement (id vide = aucun).</summary>
        public void SetItem(ClothingSlot slot, string id)
        {
            switch (slot)
            {
                case ClothingSlot.Face: Draft.faceId = id; break;
                case ClothingSlot.Hair: Draft.hairId = id; break;
                case ClothingSlot.Top: Draft.topId = id; break;
                case ClothingSlot.Bottom: Draft.bottomId = id; break;
                case ClothingSlot.Shoes: Draft.shoesId = id; break;
                default:
                    Draft.accessoryIds.Clear();
                    if (!string.IsNullOrEmpty(id)) Draft.accessoryIds.Add(id);
                    break;
            }
            outfitIndex = -1;
            Refresh();
        }

        /// <summary>L'article est-il utilisable ? (gratuit par défaut, ou débloqué par une mission/achat)</summary>
        public bool IsOwned(ClothingItem item)
        {
            if (item == null) return false;
            if (item.unlockedByDefault) return true;
            var p = ProfileManager.Instance;
            return p != null && (p.Owns(item.id) || (!string.IsNullOrEmpty(item.unlockGroup) && p.Owns(item.unlockGroup)));
        }

        public bool TrySetName(string raw, out string error)
        {
            if (!NameFilter.IsValid(raw, out error)) return false;
            Draft.driverName = NameFilter.Clean(raw);
            return true;
        }

        /// <summary>Enregistre le brouillon dans le profil (et sur disque via ProfileManager).</summary>
        public bool Save(out string error)
        {
            error = null;
            if (!NameFilter.IsValid(Draft.driverName, out error)) return false;
            var profile = ProfileManager.Instance;
            if (profile == null) { error = "Profil indisponible."; return false; }
            profile.SetAvatar(Draft.Clone());
            return true;
        }
    }
}
