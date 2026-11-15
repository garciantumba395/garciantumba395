using UnityEngine;
using UnityEngine.Profiling;
using UnityEngine.UI;
using TaxiKin.UI;

namespace TaxiKin.Core
{
    /// <summary>
    /// Mesure la fluidité. Si l'appareil n'arrive pas à tenir la cadence visée pendant plusieurs secondes de conduite,
    /// la qualité baisse d'un cran (une seule fois toutes les ~10 s, jamais de hausse automatique).
    /// En version de développement, affiche FPS et mémoire pour les tests sur téléphone d'entrée de gamme.
    /// </summary>
    public class PerformanceMonitor : MonoBehaviour
    {
        [SerializeField] private Text overlay;
        [SerializeField] private float windowSeconds = 2f;
        [SerializeField] private int badWindowsBeforeDowngrade = 4;     // 4 x 2 s = 8 s de lenteur
        [SerializeField, Range(0.5f, 0.95f)] private float fpsThreshold = 0.8f;   // 80 % de la cadence visée

        public float AverageFps { get; private set; }

        private float accumulated, nextOverlay, graceUntil;
        private int frames, badWindows;

        private void Start()
        {
            graceUntil = Time.unscaledTime + 8f;     // on ignore le chargement initial
            if (overlay != null) overlay.gameObject.SetActive(Debug.isDebugBuild || Application.isEditor);
        }

        /// <summary>Ignore la mesure pendant quelques secondes (chargement de zone, changement de véhicule).</summary>
        public void Hold(float seconds) => graceUntil = Mathf.Max(graceUntil, Time.unscaledTime + seconds);

        private void Update()
        {
            accumulated += Time.unscaledDeltaTime;
            frames++;
            if (accumulated >= windowSeconds)
            {
                AverageFps = frames / accumulated;
                accumulated = 0f; frames = 0;
                Evaluate();
            }

            if (overlay != null && overlay.gameObject.activeSelf && Time.unscaledTime >= nextOverlay)
            {
                nextOverlay = Time.unscaledTime + 0.5f;
                var q = QualityManager.Instance;
                overlay.text = $"{AverageFps:0} FPS  ·  {Profiler.GetTotalAllocatedMemoryLong() / 1048576L} Mo  ·  {(q != null ? QualityManager.Name(q.Level) : "?")}";
            }
        }

        private void Evaluate()
        {
            var gm = GameManager.Instance;
            bool driving = gm != null && gm.State == GameState.Playing && Time.timeScale > 0f;
            var q = QualityManager.Instance;
            if (!driving || q == null || !GameSettings.QualityAuto || Time.unscaledTime < graceUntil) { badWindows = 0; return; }

            float target = Mathf.Max(1, q.CurrentFps);
            badWindows = AverageFps < target * fpsThreshold ? badWindows + 1 : 0;
            if (badWindows < badWindowsBeforeDowngrade) return;

            badWindows = 0;
            if (q.Downgrade())
            {
                graceUntil = Time.unscaledTime + 10f;     // on laisse le temps au nouveau réglage de faire effet
                ToastManager.Show("Qualité réduite pour garder le jeu fluide.");
            }
        }
    }
}
