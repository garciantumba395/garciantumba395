using System;
using System.Reflection;
using UnityEngine;
using UnityEngine.Rendering;
using TaxiKin.Map;
using TaxiKin.SaveSystem;
using TaxiKin.Traffic;
using UnityShadowQuality = UnityEngine.ShadowQuality;

namespace TaxiKin.Core
{
    /// <summary>
    /// Préréglages graphiques Basse / Moyenne / Haute pour Android modeste : cadence, résolution de rendu, ombres, MSAA,
    /// lumières dynamiques, LOD, distance d'affichage, trafic et fréquence de la physique.
    /// Au premier lancement la qualité est choisie selon la mémoire et le processeur de l'appareil.
    /// Les paramètres propres au pipeline URP (échelle de rendu, MSAA, distance des ombres) sont appliqués par réflexion
    /// pour ne pas dépendre du paquet ; ils ne sont PAS modifiés dans l'éditeur (l'asset URP serait modifié pour de bon).
    /// </summary>
    public class QualityManager : MonoBehaviour
    {
        public static QualityManager Instance { get; private set; }

        [SerializeField] private Light sun;
        [SerializeField] private Camera mainCamera;
        [SerializeField] private TrafficManager traffic;

        private struct Preset
        {
            public int fps; public float renderScale; public int msaa;
            public ShadowSetting shadows; public float shadowDistance; public int pixelLights; public float lodBias;
            public int textureLimit; public float farClip; public int trafficCap; public float fixedDelta;
            public float drawDistance; public int solverIterations;
        }

        // Basse : 30 FPS, sans ombres, résolution 70 %, physique à 30 Hz. Moyenne/Haute : 60 FPS.
        private static readonly Preset[] Presets =
        {
            new Preset { fps = 30, renderScale = 0.70f, msaa = 1, shadows = ShadowSetting.Off,  shadowDistance = 0f,  pixelLights = 1, lodBias = 0.6f, textureLimit = 1, farClip = 180f, trafficCap = 6,  fixedDelta = 1f / 30f, drawDistance = 220f, solverIterations = 4 },
            new Preset { fps = 60, renderScale = 0.85f, msaa = 1, shadows = ShadowSetting.Low,  shadowDistance = 25f, pixelLights = 1, lodBias = 0.8f, textureLimit = 0, farClip = 300f, trafficCap = 12, fixedDelta = 0.02f,     drawDistance = 350f, solverIterations = 6 },
            new Preset { fps = 60, renderScale = 1.00f, msaa = 2, shadows = ShadowSetting.High, shadowDistance = 40f, pixelLights = 2, lodBias = 1.0f, textureLimit = 0, farClip = 450f, trafficCap = 20, fixedDelta = 0.02f,     drawDistance = 550f, solverIterations = 6 },
        };

        public QualityLevel Level { get; private set; } = QualityLevel.Medium;
        public int CurrentFps => cappedForBattery ? Mathf.Min(30, Presets[(int)Level].fps) : Presets[(int)Level].fps;
        public event Action OnApplied;

        private QualityLevel detected = QualityLevel.Medium;
        private bool cappedForBattery;
        private float nextBatteryCheck;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
            detected = DetectLevel();
        }

        private void OnEnable()
        {
            GameSettings.QualityChanged += Apply;
            SaveManager.ProgressReset += Apply;     // après une réinitialisation : qualité à nouveau détectée automatiquement
        }

        private void OnDisable()
        {
            GameSettings.QualityChanged -= Apply;
            SaveManager.ProgressReset -= Apply;
        }

        private void Start() => Apply();

        /// <summary>Choix de la qualité selon la mémoire et le nombre de cœurs de l'appareil.</summary>
        public static QualityLevel DetectLevel()
        {
            int ram = SystemInfo.systemMemorySize;       // en Mo
            int cores = SystemInfo.processorCount;
            if (ram > 0 && ram < 3000) return QualityLevel.Low;
            if (cores > 0 && cores <= 4) return QualityLevel.Low;
            if (ram < 6000) return QualityLevel.Medium;
            return QualityLevel.High;
        }

        public string Describe() =>
            $"{SystemInfo.systemMemorySize / 1024f:0.#} Go de RAM · {SystemInfo.processorCount} cœurs · détecté : {Name(detected)}";

        public static string Name(QualityLevel l) => l == QualityLevel.Low ? "Basse" : l == QualityLevel.Medium ? "Moyenne" : "Haute";

        /// <summary>Baisse d'un cran (appelée par PerformanceMonitor). Retourne faux si on est déjà au plus bas.</summary>
        public bool Downgrade()
        {
            if (Level == QualityLevel.Low) return false;
            GameSettings.Quality = (int)Level - 1;      // mémorisé : au prochain lancement on repart de ce niveau
            return true;
        }

        public void Apply()
        {
            int q = GameSettings.Quality;
            if (q < 0) detected = DetectLevel();
            Level = q < 0 ? detected : (QualityLevel)Mathf.Clamp(q, 0, 2);
            var p = Presets[(int)Level];

            // Ombres : le réglage du joueur l'emporte sur le préréglage
            int sh = GameSettings.Shadows;
            ShadowSetting shadow = sh < 0 ? p.shadows : (ShadowSetting)sh;
            float shadowDist = shadow == ShadowSetting.Off ? 0f : (shadow == ShadowSetting.Low ? Mathf.Min(25f, Mathf.Max(p.shadowDistance, 20f)) : Mathf.Max(p.shadowDistance, 40f));

            QualitySettings.vSyncCount = 0;
            Application.targetFrameRate = CurrentFps;
            QualitySettings.pixelLightCount = p.pixelLights;
            QualitySettings.lodBias = p.lodBias;
            QualitySettings.antiAliasing = p.msaa > 1 ? p.msaa : 0;
            QualitySettings.anisotropicFiltering = AnisotropicFiltering.Disable;
            QualitySettings.realtimeReflectionProbes = false;
            QualitySettings.softParticles = false;
#if UNITY_6000_0_OR_NEWER
            QualitySettings.globalTextureMipmapLimit = p.textureLimit;
#else
            QualitySettings.masterTextureLimit = p.textureLimit;
#endif
            QualitySettings.shadows = shadow == ShadowSetting.Off ? UnityShadowQuality.Disable
                                    : shadow == ShadowSetting.Low ? UnityShadowQuality.HardOnly : UnityShadowQuality.All;
            QualitySettings.shadowResolution = shadow == ShadowSetting.High ? ShadowResolution.Medium : ShadowResolution.Low;
            QualitySettings.shadowDistance = shadowDist;
            QualitySettings.shadowCascades = shadow == ShadowSetting.High ? 2 : 1;

            Time.fixedDeltaTime = p.fixedDelta;
            Physics.defaultSolverIterations = p.solverIterations;

            if (sun != null) sun.shadows = shadow == ShadowSetting.Off ? LightShadows.None : (shadow == ShadowSetting.Low ? LightShadows.Hard : LightShadows.Soft);
            if (mainCamera != null) { mainCamera.farClipPlane = p.farClip; mainCamera.allowHDR = false; mainCamera.allowMSAA = p.msaa > 1; }
            traffic?.SetQualityCap(p.trafficCap);
            ZoneLoader.DrawDistance = p.drawDistance;

#if !UNITY_EDITOR
            SetPipelineProperty("renderScale", p.renderScale);
            SetPipelineProperty("msaaSampleCount", p.msaa);
            SetPipelineProperty("shadowDistance", shadowDist);
            SetPipelineProperty("supportsHDR", false);
#endif
            OnApplied?.Invoke();
        }

        /// <summary>Applique une propriété de l'asset de pipeline (URP) si elle existe, sans dépendre du paquet.</summary>
        private static void SetPipelineProperty(string property, object value)
        {
            var asset = GraphicsSettings.currentRenderPipeline;
            if (asset == null) return;
            var pi = asset.GetType().GetProperty(property, BindingFlags.Public | BindingFlags.Instance);
            if (pi == null || !pi.CanWrite) return;
            try { pi.SetValue(asset, Convert.ChangeType(value, pi.PropertyType)); }
            catch (Exception e) { Debug.LogWarning($"[Qualité] {property} : {e.Message}"); }
        }

        // ---------- Économie de batterie ----------

        private void Update()
        {
            if (Time.unscaledTime < nextBatteryCheck) return;
            nextBatteryCheck = Time.unscaledTime + 15f;

            // Batterie faible et pas en charge : on plafonne à 30 images par seconde
            float level = SystemInfo.batteryLevel;     // -1 si inconnu
            bool low = level >= 0f && level <= 0.15f && SystemInfo.batteryStatus == BatteryStatus.Discharging;
            if (low == cappedForBattery) return;
            cappedForBattery = low;
            Application.targetFrameRate = CurrentFps;
        }
    }
}
