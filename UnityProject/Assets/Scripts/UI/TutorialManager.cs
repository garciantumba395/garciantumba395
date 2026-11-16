using System.Collections;
using UnityEngine;
using UnityEngine.UI;
using TaxiKin.Core;
using TaxiKin.Missions;
using TaxiKin.Player;
using TaxiKin.SaveSystem;
using TaxiKin.Vehicles;

namespace TaxiKin.UI
{
    /// <summary>
    /// Tutoriel de première partie (≈ 1 minute, en conduite libre) : 8 étapes qui détectent la VRAIE action du joueur
    /// (tourner, accélérer, freiner, klaxonner, changer de caméra, prendre un client), avec un anneau pulsant autour de la commande
    /// concernée. Proposé une seule fois après la création du chauffeur ; « Passer » est définitif (rejouable dans les Réglages).
    /// S'adapte au mode de contrôle (volant, flèches, inclinaison) et au véhicule.
    /// Démarrer une mission en cours de tutoriel l'interrompt sans le répéter.
    /// </summary>
    public class TutorialManager : MonoBehaviour
    {
        [SerializeField] private PlayerController player;
        [SerializeField] private MissionManager missions;
        [SerializeField] private PassengerManager passengers;
        [SerializeField] private CameraRig cameraRig;
        [SerializeField] private ControlsLayout controls;
        [SerializeField] private MenuUIManager menu;
        [SerializeField] private RectTransform menuButton;

        [Header("Écrans")]
        [SerializeField] private GameObject offerPanel;
        [SerializeField] private GameObject banner;
        [SerializeField] private Text stepText, instructionText;
        [SerializeField] private GameObject nextButton;
        [SerializeField] private RectTransform highlight;

        private enum Cond { Steer, Throttle, Brake, Horn, Camera, Passenger, Acknowledge, OpenMenu }

        // Cible d'une étape : -1 = aucune, -2 = bouton « Menu » du HUD, sinon un ControlId
        private struct Step
        {
            public string key; public Cond cond; public int target;
            public Step(string k, Cond c, int t) { key = k; cond = c; target = t; }
        }

        private static readonly Step[] Steps =
        {
            new Step("tuto_steer", Cond.Steer, (int)ControlId.Wheel),
            new Step("tuto_gas", Cond.Throttle, (int)ControlId.Gas),
            new Step("tuto_brake", Cond.Brake, (int)ControlId.Brake),
            new Step("tuto_horn", Cond.Horn, (int)ControlId.Horn),
            new Step("tuto_camera", Cond.Camera, (int)ControlId.Camera),
            new Step("tuto_passenger", Cond.Passenger, (int)ControlId.PassengerAction),
            new Step("tuto_hud", Cond.Acknowledge, -1),
            new Step("tuto_menu", Cond.OpenMenu, -2),
        };

        private static readonly Color Gold = new Color(1f, 0.83f, 0f);
        private static readonly Color Green = new Color(0.5f, 1f, 0.5f);

        private bool running, offering, hornHeard, sawSpeed, acknowledged;
        private int index;
        private float doneAt = -1f;
        private CameraView startCamera;
        private VehicleController watched;

        public bool IsRunning => running;

        private bool Done
        {
            get => SaveManager.Instance != null && SaveManager.Instance.Data.tutorialDone;
            set { if (SaveManager.Instance != null) { SaveManager.Instance.Data.tutorialDone = value; SaveManager.Instance.Save(); } }
        }

        // ---------- Cycle de vie ----------

        private void OnEnable()
        {
            if (player != null) player.OnVehicleChanged += OnVehicleChanged;
            if (missions != null) missions.OnMissionStarted += OnMissionStarted;
            SaveManager.ProgressReset += OnProgressReset;
        }

        private void OnDisable()
        {
            if (player != null) player.OnVehicleChanged -= OnVehicleChanged;
            if (missions != null) missions.OnMissionStarted -= OnMissionStarted;
            SaveManager.ProgressReset -= OnProgressReset;
            if (watched != null) watched.OnHorn -= OnHorn;
        }

        private void Start()
        {
            var profile = ProfileManager.Instance;
            if (profile == null) return;
            profile.OnAvatarSaved += OnAvatarSaved;
            if (profile.HasProfile && !Done) StartCoroutine(OfferWhenReady());     // profil existant sans tutoriel fait
        }

        private void OnDestroy()
        {
            if (ProfileManager.Instance != null) ProfileManager.Instance.OnAvatarSaved -= OnAvatarSaved;
        }

        private void OnAvatarSaved() { if (!Done) StartCoroutine(OfferWhenReady()); }

        private void OnProgressReset()
        {
            StopAllCoroutines();
            running = false; offering = false;
            if (offerPanel != null) offerPanel.SetActive(false);
            if (banner != null) banner.SetActive(false);
            if (highlight != null) highlight.gameObject.SetActive(false);
        }

        private void OnVehicleChanged(VehicleController v)
        {
            if (watched != null) watched.OnHorn -= OnHorn;
            watched = v;
            if (watched != null) watched.OnHorn += OnHorn;
        }

        private void OnHorn() => hornHeard = true;

        private void OnMissionStarted(MissionData _)
        {
            if (running) Finish(false);          // une mission commence : on arrête sans féliciter
            else if (offerPanel != null) offerPanel.SetActive(false);
        }

        // ---------- Proposition ----------

        private IEnumerator OfferWhenReady()
        {
            if (offering || running) yield break;
            offering = true;
            yield return null;
            while (GameManager.Instance != null && GameManager.Instance.State == GameState.CharacterCreation) yield return null;   // après « Mon chauffeur »
            yield return new WaitForSecondsRealtime(0.6f);
            offering = false;
            if (Done || running || (missions != null && missions.State != MissionState.Idle)) yield break;
            offerPanel.SetActive(true);
        }

        public void OnAccept()
        {
            offerPanel.SetActive(false);
            menu?.CloseToPlay();
            StartTutorial();
        }

        public void OnSkipOffer() { offerPanel.SetActive(false); Done = true; }

        // ---------- Déroulement ----------

        /// <summary>Lance (ou relance) le tutoriel en conduite libre.</summary>
        public void StartTutorial()
        {
            running = true;
            GoTo(0);
        }

        public void OnSkip() { if (running) Finish(false); }
        public void OnNext() { acknowledged = true; }

        private void GoTo(int i)
        {
            index = i;
            if (index >= Steps.Length) { Finish(true); return; }
            hornHeard = sawSpeed = acknowledged = false;
            doneAt = -1f;
            startCamera = cameraRig != null ? cameraRig.Current : CameraView.Behind;
            stepText.text = $"{index + 1} / {Steps.Length}";
            instructionText.text = Loc.T(KeyFor(Steps[index]));
            instructionText.color = Color.white;
            nextButton.SetActive(Steps[index].cond == Cond.Acknowledge);
            banner.SetActive(true);
        }

        private void Finish(bool completed)
        {
            running = false;
            banner.SetActive(false);
            highlight.gameObject.SetActive(false);
            Done = true;
            if (completed) ToastManager.Show(Loc.T("tuto_done"));
        }

        /// <summary>Texte de l'étape ; celui de la direction dépend du mode de contrôle.</summary>
        private static string KeyFor(Step s)
        {
            if (s.cond != Cond.Steer) return s.key;
            switch (ControlSettings.Mode)
            {
                case ControlMode.Arrows: return "tuto_steer_arrows";
                case ControlMode.Tilt: return "tuto_steer_tilt";
                default: return "tuto_steer";
            }
        }

        // ---------- Détection des actions ----------

        private void Update()
        {
            if (!running) return;
            var step = Steps[index];
            var v = player != null ? player.Vehicle : null;

            // Le bandeau se masque quand le jeu est en pause (menu, réglages…), sauf pour les étapes qui concernent le menu
            bool paused = Time.timeScale <= 0f;
            bool show = !paused || step.cond == Cond.OpenMenu || step.cond == Cond.Acknowledge;
            banner.SetActive(show);
            UpdateHighlight(step, show);
            if (v == null) return;

            if (doneAt < 0f && IsCompleted(step, v)) { doneAt = Time.unscaledTime; instructionText.color = Green; }
            if (doneAt >= 0f && Time.unscaledTime - doneAt > 0.7f) GoTo(index + 1);    // courte pause pour voir le succès
        }

        private bool IsCompleted(Step step, VehicleController v)
        {
            switch (step.cond)
            {
                case Cond.Steer: return Mathf.Abs(v.Steer) >= 0.5f;
                case Cond.Throttle: return v.SpeedKmh >= 20f;
                case Cond.Brake:
                    if (v.SpeedKmh >= 15f) sawSpeed = true;
                    return sawSpeed && v.SpeedKmh <= 3f;
                case Cond.Horn: return hornHeard;
                case Cond.Camera: return cameraRig != null && cameraRig.Current != startCamera;
                case Cond.Passenger: return passengers != null && passengers.OnBoard > 0;
                case Cond.Acknowledge: return acknowledged;
                case Cond.OpenMenu: return (menu != null && menu.IsOpen) || (missions != null && missions.State != MissionState.Idle);
                default: return false;
            }
        }

        // ---------- Anneau autour de la commande ----------

        private RectTransform TargetFor(Step s)
        {
            if (s.target == -2) return menuButton;
            if (s.target < 0 || controls == null) return null;
            if (s.cond == Cond.Steer)
            {
                switch (ControlSettings.Mode)
                {
                    case ControlMode.Arrows: return controls.Get(ControlId.ArrowLeft);
                    case ControlMode.Tilt: return null;                    // l'inclinaison n'a pas de bouton
                    default: return controls.Get(ControlId.Wheel);
                }
            }
            if (s.cond == Cond.Passenger)
                return controls.Get(ControlId.PassengerAction) ?? controls.Get(ControlId.Pickup) ?? controls.Get(ControlId.PickupAll);
            if (s.target == (int)ControlId.Horn) return controls.Get(ControlId.Horn) ?? controls.Get(ControlId.WheelHorn);   // le klaxon est au centre du volant en mode volant
            return controls.Get((ControlId)s.target);
        }

        private void UpdateHighlight(Step step, bool show)
        {
            var target = TargetFor(step);
            if (!show || target == null || !target.gameObject.activeInHierarchy) { highlight.gameObject.SetActive(false); return; }
            highlight.gameObject.SetActive(true);
            highlight.position = target.TransformPoint(target.rect.center);
            float scale = target.lossyScale.x / Mathf.Max(0.0001f, highlight.lossyScale.x);
            float pulse = 1.18f + 0.08f * Mathf.Sin(Time.unscaledTime * 6f);
            highlight.sizeDelta = Vector2.Max(target.rect.size, new Vector2(120f, 120f)) * scale * pulse;
        }
    }
}
