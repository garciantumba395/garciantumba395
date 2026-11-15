using UnityEngine;
using TaxiKin.Player;
using TaxiKin.UI;
using TaxiKin.Vehicles;

namespace TaxiKin.Missions
{
    /// <summary>
    /// Missions de personnalisation (sans conduite), terminées automatiquement :
    ///  1. « Crée ton identité » : enregistrer son profil (avatar + tenue + nom).
    ///  2. « Le jaune de Kin » : choisir un taxi jaune dans le garage, puis réussir une course.
    /// </summary>
    public class CustomizationQuests : MonoBehaviour
    {
        public const string IdentityId = "cree_ton_identite";
        public const string YellowId = "le_jaune_de_kin";

        [SerializeField] private MissionManager missions;
        [SerializeField] private VehicleCustomizer taxiCustomizer;

        private bool yellowChosen;

        private void Start()
        {
            if (ProfileManager.Instance != null) ProfileManager.Instance.OnAvatarSaved += OnAvatarSaved;
            if (missions != null) { missions.OnMissionEnded += OnMissionEnded; missions.OnQuestCompleted += OnQuestCompleted; }
            if (taxiCustomizer != null) taxiCustomizer.OnChanged += OnTaxiChanged;
        }

        private void OnDestroy()
        {
            if (ProfileManager.Instance != null) ProfileManager.Instance.OnAvatarSaved -= OnAvatarSaved;
            if (missions != null) { missions.OnMissionEnded -= OnMissionEnded; missions.OnQuestCompleted -= OnQuestCompleted; }
            if (taxiCustomizer != null) taxiCustomizer.OnChanged -= OnTaxiChanged;
        }

        private void OnAvatarSaved() => missions?.GrantQuest(IdentityId);

        // Le joueur a personnalisé son taxi : est-il jaune ?
        private void OnTaxiChanged(VehicleCustomization c) { if (VehicleCustomizer.IsYellow(c.bodyColor)) yellowChosen = true; }

        private void OnMissionEnded(MissionResult r)
        {
            if (r.success && yellowChosen) missions.GrantQuest(YellowId);
        }

        private void OnQuestCompleted(MissionData m)
        {
            ToastManager.Show($"Mission terminée : {m.Title}  (+{m.rewards.kinCoins} Kin Coins)");
        }
    }
}
