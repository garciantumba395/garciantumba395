using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Écran « Mon chauffeur » (logique). Travaille sur une COPIE de l'avatar : « Enregistrer » la valide,
    /// « Annuler » la jette. L'interface (onglets, boutons) sera ajoutée à l'Étape 4.
    /// </summary>
    public class CharacterCreatorManager : MonoBehaviour
    {
        [SerializeField] private AvatarCustomizer previewAvatar;

        public AvatarData Draft { get; private set; }

        public void Begin()
        {
            var profile = ProfileManager.Instance;
            Draft = profile != null ? profile.Avatar.Clone() : new AvatarData();
            previewAvatar?.Apply(Draft);
        }

        public void SetSkinColor(Color c) { Draft.skinColor = c; previewAvatar?.Apply(Draft); }

        public bool TrySetName(string raw, out string error)
        {
            if (!NameFilter.IsValid(raw, out error)) return false;
            Draft.driverName = NameFilter.Clean(raw);
            return true;
        }

        /// <summary>Enregistre le brouillon dans le profil (la sauvegarde disque suit à l'Étape 6).</summary>
        public bool Save(out string error)
        {
            error = null;
            if (!NameFilter.IsValid(Draft.driverName, out error)) return false;
            ProfileManager.Instance?.SetAvatar(Draft);
            return true;
        }

        public void Cancel() => Begin();
    }
}
