using UnityEngine;
using TaxiKin.Core;
using QualityLevel = TaxiKin.Core.QualityLevel;   // UnityEngine.QualityLevel existe aussi : on lève l'ambiguïté

namespace TaxiKin.Weather
{
    /// <summary>
    /// Pluie visible : un seul système de particules (traînées fines) qui suit la caméra, au-dessus d'elle.
    /// Optimisé mobile : au plus 900 particules, un seul matériau transparent sans éclairage, aucune ombre,
    /// densité réduite selon la qualité graphique (basse 40 %, moyenne 70 %, haute 100 %), arrêt complet par temps sec.
    /// Le système est créé par code : aucun asset de particules à régler.
    /// </summary>
    public class RainEffect : MonoBehaviour
    {
        [SerializeField] private Camera followCamera;
        [SerializeField] private Material rainMaterial;
        [SerializeField] private float maxRatePerSecond = 450f;

        private ParticleSystem system;
        private ParticleSystem.EmissionModule emission;
        private float nextUpdate;

        private void Awake()
        {
            var go = new GameObject("Pluie");
            go.transform.SetParent(transform, false);
            go.transform.rotation = Quaternion.Euler(90f, 0f, 0f);       // un émetteur « Box » émet vers son avant : ici, vers le bas
            system = go.AddComponent<ParticleSystem>();
            system.Stop(true, ParticleSystemStopBehavior.StopEmittingAndClear);

            var main = system.main;
            main.loop = true;
            main.playOnAwake = false;
            main.simulationSpace = ParticleSystemSimulationSpace.World;     // les traînées défilent quand on roule
            main.startLifetime = 0.9f;
            main.startSpeed = 24f;
            main.startSize = 0.05f;
            main.startColor = new Color(0.82f, 0.87f, 0.95f, 0.35f);
            main.gravityModifier = 0f;
            main.maxParticles = 900;

            emission = system.emission;
            emission.rateOverTime = 0f;

            var shape = system.shape;
            shape.shapeType = ParticleSystemShapeType.Box;
            shape.scale = new Vector3(36f, 1f, 36f);

            var r = go.GetComponent<ParticleSystemRenderer>();
            r.renderMode = ParticleSystemRenderMode.Stretch;
            r.velocityScale = 0.05f;
            r.lengthScale = 1.2f;
            r.shadowCastingMode = UnityEngine.Rendering.ShadowCastingMode.Off;
            r.receiveShadows = false;
            if (rainMaterial != null) r.sharedMaterial = rainMaterial;
        }

        private void LateUpdate()
        {
            if (followCamera == null) return;
            // Au-dessus et un peu devant la caméra, pour couvrir ce qu'on voit
            Vector3 f = followCamera.transform.forward; f.y = 0f;
            transform.position = followCamera.transform.position + f.normalized * 8f + Vector3.up * 14f;

            if (Time.unscaledTime < nextUpdate) return;
            nextUpdate = Time.unscaledTime + 0.25f;

            var weather = WeatherManager.Instance;
            float wet = weather != null ? weather.Wetness : 0f;
            var quality = QualityManager.Instance;
            float scale = quality == null ? 0.7f : quality.Level == QualityLevel.Low ? 0.4f : quality.Level == QualityLevel.Medium ? 0.7f : 1f;

            float rate = wet * maxRatePerSecond * scale;
            emission.rateOverTime = rate;
            if (rate > 1f && !system.isPlaying) system.Play();
            else if (rate <= 1f && system.isPlaying) system.Stop(true, ParticleSystemStopBehavior.StopEmitting);
        }
    }
}
