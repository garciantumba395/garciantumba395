using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// À placer sur un avatar affiché dans le jeu (menu, garage, missions, courses, victoire, classements) :
    /// il montre automatiquement l'avatar du joueur et se met à jour quand le profil change.
    /// </summary>
    [RequireComponent(typeof(AvatarCustomizer))]
    public class AvatarDisplay : MonoBehaviour
    {
        private AvatarCustomizer customizer;
        private ProfileManager profile;

        private void Start()
        {
            customizer = GetComponent<AvatarCustomizer>();
            profile = ProfileManager.Instance;
            if (profile == null) return;
            profile.OnProfileChanged += Refresh;
            Refresh();
        }

        private void OnDestroy() { if (profile != null) profile.OnProfileChanged -= Refresh; }

        private void Refresh() => customizer.Apply(profile.Avatar);
    }
}
