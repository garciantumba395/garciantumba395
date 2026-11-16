using System.Collections.Generic;
using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;
using TaxiKin.Economy;
using TaxiKin.Missions;
using TaxiKin.Player;

namespace TaxiKin.UI
{
    /// <summary>
    /// Menu principal : Jouer, Missions, Garage, Boutique, Mon chauffeur, Réglages, et « Reprendre » pendant une mission.
    /// Affiche l'avatar du joueur (aperçu 3D), son nom, son niveau et ses monnaies.
    /// S'ouvre au démarrage (après la création du chauffeur au premier lancement) et depuis le bouton « Menu » du HUD.
    /// Ouvert en pleine mission, il met le jeu en pause et restaure ensuite l'état exact d'avant.
    /// </summary>
    public class MenuUIManager : MonoBehaviour
    {
        [SerializeField] private MissionManager missions;
        [SerializeField] private CharacterCreatorUI creatorUi;
        [SerializeField] private GarageUIManager garageUi;
        [SerializeField] private ShopUIManager shopUi;
        [SerializeField] private SettingsUIManager settingsUi;
        [SerializeField] private AvatarPreviewStage avatarStage;

        [Header("Écrans")]
        [SerializeField] private GameObject menuPanel;
        [SerializeField] private GameObject missionsPanel;
        [SerializeField] private RawImage avatarImage;
        [SerializeField] private Text nameText, levelText, coinsText, goldText;
        [SerializeField] private GameObject resumeButton;
        [SerializeField] private RectTransform missionsGrid;
        [SerializeField] private Button missionTemplate;

        private static readonly Color Normal = new Color(0.2f, 0.2f, 0.24f, 0.95f);
        private static readonly Color Done = new Color(0.15f, 0.45f, 0.22f, 0.95f);
        private static readonly Color Locked = new Color(0.12f, 0.12f, 0.12f, 0.8f);

        private readonly List<GameObject> cells = new List<GameObject>();
        private GameState stateBefore = GameState.MainMenu;

        public bool IsOpen => menuPanel != null && (menuPanel.activeSelf || missionsPanel.activeSelf);

        private void OnEnable()
        {
            if (missions != null) { missions.OnBriefing += OnMissionScreen; missions.OnMissionStarted += OnMissionScreen; }
        }

        private void OnDisable()
        {
            if (missions != null) { missions.OnBriefing -= OnMissionScreen; missions.OnMissionStarted -= OnMissionScreen; }
        }

        private void Start()
        {
            var profile = ProfileManager.Instance;
            if (profile != null) profile.OnAvatarSaved += OnAvatarSaved;
            if (creatorUi != null) creatorUi.OnClosed += OnCreatorClosed;
            // Premier lancement : l'écran « Mon chauffeur » s'ouvre d'abord ; le menu viendra après l'enregistrement
            if (profile != null && profile.HasProfile) Open();
        }

        private void OnDestroy()
        {
            if (ProfileManager.Instance != null) ProfileManager.Instance.OnAvatarSaved -= OnAvatarSaved;
            if (creatorUi != null) creatorUi.OnClosed -= OnCreatorClosed;
        }

        // L'écran « Mon chauffeur » vient de se fermer : il a repris l'aperçu 3D, on le rend au menu
        private void OnCreatorClosed() { if (IsOpen) { RefreshHeader(); ShowAvatar(); } }

        private void OnAvatarSaved() { if (!IsOpen && (missions == null || !missions.IsRunning)) Open(); }

        // Un écran de mission s'ouvre : le menu s'efface
        private void OnMissionScreen(MissionData _)
        {
            HidePanels();
            avatarStage?.SetActive(false);
        }

        // ---------- Ouverture / fermeture ----------

        public void Open()
        {
            var gm = GameManager.Instance;
            if (gm != null && !IsOpen)
            {
                if (gm.State != GameState.CharacterCreation) stateBefore = gm.State;
                if (gm.State == GameState.Playing) gm.SetState(GameState.Paused);   // pause en pleine mission
            }
            missionsPanel.SetActive(false);
            menuPanel.SetActive(true);
            RefreshHeader();
            resumeButton.SetActive(missions != null && missions.IsRunning);

            ShowAvatar();
        }

        /// <summary>L'avatar du joueur dans le menu : on réutilise l'aperçu 3D du créateur de chauffeur.</summary>
        private void ShowAvatar()
        {
            if (avatarStage == null || ProfileManager.Instance == null) return;
            avatarStage.ResetRotation();
            avatarStage.Avatar.Apply(ProfileManager.Instance.Avatar);
            avatarStage.SetActive(true);
            if (avatarImage != null) avatarImage.texture = avatarStage.Texture;
        }

        private void HidePanels()
        {
            if (menuPanel != null) menuPanel.SetActive(false);
            if (missionsPanel != null) missionsPanel.SetActive(false);
        }

        // ---------- Boutons ----------

        public void OnPlay()
        {
            var next = missions != null ? missions.NextSuggested() : null;
            if (next != null) missions.Brief(next); else OnMissions();
        }

        public void OnResume()
        {
            HidePanels();
            avatarStage?.SetActive(false);
            GameManager.Instance?.SetState(stateBefore == GameState.MainMenu ? GameState.Playing : stateBefore);
        }

        /// <summary>Ferme le menu et passe en conduite libre (début du tutoriel).</summary>
        public void CloseToPlay()
        {
            HidePanels();
            avatarStage?.SetActive(false);
            GameManager.Instance?.SetState(GameState.Playing);
        }

        public void OnMissions()
        {
            menuPanel.SetActive(false);
            missionsPanel.SetActive(true);
            BuildMissionList();
        }

        public void OnBackFromMissions() { missionsPanel.SetActive(false); menuPanel.SetActive(true); }

        public void OnGarage() => garageUi?.Open();
        public void OnShop() => shopUi?.Open();
        public void OnSettings() => settingsUi?.Open();

        public void OnDriver()
        {
            // L'aperçu 3D du menu partage la scène du créateur : le créateur le reprend et le rend au menu en se refermant
            creatorUi?.OpenFromMenu();
        }

        // ---------- Affichage ----------

        private void Update()
        {
            if (menuPanel == null || !menuPanel.activeSelf) return;
            var eco = EconomyManager.Instance;
            if (eco != null) { coinsText.text = $"{eco.KinCoins:N0}"; goldText.text = $"{eco.KinGold:N0}"; }
        }

        private void RefreshHeader()
        {
            var p = ProfileManager.Instance;
            nameText.text = p != null && !string.IsNullOrEmpty(p.Avatar.driverName) ? p.Avatar.driverName : "Chauffeur";
            levelText.text = p != null ? $"Niveau {p.Level}  ·  {p.Xp} / {ProfileManager.XpForNextLevel(p.Level)} XP" : "";
        }

        /// <summary>Liste des missions de conduite : terminée, jouable ou « bientôt » (système manquant).</summary>
        private void BuildMissionList()
        {
            foreach (var c in cells) Destroy(c);
            cells.Clear();
            if (missions == null) return;

            foreach (var m in missions.Missions)
            {
                if (m.type == MissionType.Customization) continue;      // les quêtes de personnalisation se terminent toutes seules
                bool playable = missions.IsPlayable(m);
                bool done = missions.IsCompleted(m.id);

                var cell = Instantiate(missionTemplate, missionsGrid);
                cell.gameObject.SetActive(true);
                var label = cell.GetComponentInChildren<Text>();
                string status = !playable ? "Bientôt" : done ? "Terminée" : $"{m.rewards.kinCoins:N0} Kin Coins";
                label.text = $"{m.order}. {m.Title}\n{status}";
                cell.GetComponent<Image>().color = !playable ? Locked : done ? Done : Normal;
                cell.interactable = playable;
                var mission = m;
                cell.onClick.AddListener(() => missions.Brief(mission));
                cells.Add(cell.gameObject);
            }
        }
    }
}
