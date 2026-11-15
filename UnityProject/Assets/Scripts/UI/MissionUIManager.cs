using System.Text;
using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;
using TaxiKin.Missions;
using TaxiKin.Vehicles;

namespace TaxiKin.UI
{
    /// <summary>
    /// Écrans de mission : début (titre, description, véhicule, trajet, chrono, récompense, « Commencer »)
    /// et fin (réussite/échec, objectifs, récompenses, « Rejouer », « Mission suivante »).
    /// Les boutons appellent OnStart / OnRetry / OnNext / OnOpenMenu.
    /// </summary>
    public class MissionUIManager : MonoBehaviour
    {
        [SerializeField] private MissionManager missions;

        [Header("Écran de début")]
        [SerializeField] private GameObject briefingPanel;
        [SerializeField] private Text briefTitle, briefDescription, briefVehicle, briefRoute, briefTime, briefReward;

        [Header("Écran de fin")]
        [SerializeField] private GameObject endPanel;
        [SerializeField] private Text endTitle, endObjectives, endReward;
        [SerializeField] private GameObject nextButton;
        [SerializeField] private MenuUIManager menu;

        private readonly StringBuilder sb = new StringBuilder(256);

        private void OnEnable()
        {
            if (missions == null) return;
            missions.OnBriefing += ShowBriefing;
            missions.OnMissionStarted += HideAll;
            missions.OnMissionEnded += ShowEnd;
        }

        private void OnDisable()
        {
            if (missions == null) return;
            missions.OnBriefing -= ShowBriefing;
            missions.OnMissionStarted -= HideAll;
            missions.OnMissionEnded -= ShowEnd;
        }

        // ---------- Boutons ----------
        public void OnStart() => missions.Begin();
        public void OnRetry() { HideAll(null); missions.Retry(); }
        public void OnNext() { HideAll(null); missions.BriefFollowing(); }
        public void OnOpenMenu() { if (missions.State != MissionState.Running) missions.BriefNext(); }
        public void OnMenu() { HideAll(null); menu?.Open(); }

        // ---------- Affichage ----------
        private void HideAll(MissionData _)
        {
            if (briefingPanel != null) briefingPanel.SetActive(false);
            if (endPanel != null) endPanel.SetActive(false);
        }

        private void ShowBriefing(MissionData m)
        {
            HideAll(null);
            briefTitle.text = m.Title;
            briefDescription.text = m.Description;
            briefVehicle.text = $"{Loc.T("mission_vehicle")} : " + (m.anyVehicle ? Loc.T("mission_any") : VehicleName(m.requiredVehicle));
            briefRoute.text = $"{Loc.T("mission_route")} : " + Route(m);
            briefTime.text = $"{Loc.T("mission_time")} : " + (m.timeLimitSeconds > 0f ? MissionManager.FormatTime(m.timeLimitSeconds) : "—");
            briefReward.text = $"{Loc.T("mission_reward")} : " + RewardText(m.rewards, m.rewards.kinCoins, m.rewards.xp);
            briefingPanel.SetActive(true);
        }

        private void ShowEnd(MissionResult r)
        {
            HideAll(null);
            endTitle.text = r.success ? Loc.T("mission_success") : Loc.T("mission_failed");

            sb.Clear();
            foreach (var o in r.objectives) sb.Append(o.ok ? "[OK] " : "[  ] ").AppendLine(o.label);
            sb.Append(Loc.T("mission_time")).Append(" : ").Append(MissionManager.FormatTime(r.time));
            endObjectives.text = sb.ToString();

            endReward.text = r.success
                ? RewardText(r.mission.rewards, r.coins, r.xp) + (r.firstCompletion ? "" : "  (25 %)")
                : "—";

            if (nextButton != null) nextButton.SetActive(r.success && missions.FindNext(r.mission) != null);
            endPanel.SetActive(true);
        }

        private static string Route(MissionData m)
        {
            bool hasStart = m.start != null && !m.start.IsEmpty, hasDest = m.destination != null && !m.destination.IsEmpty;
            if (hasStart && hasDest) return m.start.label + " → " + m.destination.label;
            if (hasDest) return m.destination.label;
            return hasStart ? m.start.label : "—";
        }

        private static string VehicleName(VehicleType t) =>
            t == VehicleType.Wewa ? "Wewa" : t == VehicleType.Taxi ? "Taxi-voiture" : "Bus 207";

        private static string RewardText(MissionRewards r, int coins, int xp)
        {
            var s = new StringBuilder();
            if (coins > 0) s.Append(coins).Append(" Kin Coins");
            if (xp > 0) s.Append(s.Length > 0 ? " · " : "").Append(xp).Append(" XP");
            if (!string.IsNullOrEmpty(r.trophyId)) s.Append(" · Trophée");
            if (!string.IsNullOrEmpty(r.rareVehicleId)) s.Append(" · Véhicule rare");
            if (!string.IsNullOrEmpty(r.stickerId)) s.Append(" · Autocollant exclusif");
            if (!string.IsNullOrEmpty(r.clothingId)) s.Append(" · Tenue");
            if (!string.IsNullOrEmpty(r.unlocksVehicle)) s.Append(" · Débloque : ").Append(r.unlocksVehicle == "Bus207" ? "Bus 207" : "Taxi-voiture");
            return s.Length > 0 ? s.ToString() : "—";
        }
    }
}
