using System;
using UnityEngine;

namespace TaxiKin.Weather
{
    public enum WeatherType { Clear, LightRain, HeavyRain }

    /// <summary>
    /// Météo : soleil, pluie légère, pluie forte. Une transition douce (4 s) fait passer d'un temps à l'autre.
    ///  - Adhérence : 1,0 (sec) → 0,8 (pluie légère) → 0,6 (pluie forte), utilisée par VehicleController.
    ///  - Visibilité : brouillard exponentiel et lumière du soleil atténuée (pas de lumière dynamique supplémentaire).
    ///  - L'IA du trafic et des coureurs ralentit (AiSpeedFactor).
    /// Les formules sont celles validées par Tools/verify/sim_ai.py. La pluie visible est dessinée par RainEffect.
    /// </summary>
    public class WeatherManager : MonoBehaviour
    {
        public static WeatherManager Instance { get; private set; }

        [SerializeField] private Light sun;
        [SerializeField] private float transitionSeconds = 4f;

        private static readonly Color RainFogColor = new Color(0.55f, 0.58f, 0.62f);
        private const float HeavyFogDensity = 0.010f;     // exponentiel² : à 150 m environ 90 % de brouillard
        private const float HeavySunFactor = 0.55f;

        public WeatherType Current { get; private set; } = WeatherType.Clear;
        public event Action<WeatherType> OnWeatherChanged;

        /// <summary>0 = sec, 0,5 = pluie légère, 1 = pluie forte (lissé dans le temps).</summary>
        public float Wetness { get; private set; }

        /// <summary>Coefficient d'adhérence de la route : 1 = sec, 0,6 = pluie forte.</summary>
        public float RoadGrip => Mathf.Lerp(1f, 0.6f, Wetness);

        /// <summary>Facteur de vitesse de l'IA sous la pluie (1 sec, 0,8 pluie forte).</summary>
        public float AiSpeedFactor => 0.5f + 0.5f * RoadGrip;

        private float target;
        private float applied = -1f;
        private bool baseFog;
        private float baseDensity, baseSun;
        private Color baseFogColor;
        private FogMode baseFogMode;

        private void Awake()
        {
            if (Instance != null && Instance != this) { Destroy(this); return; }
            Instance = this;
            baseFog = RenderSettings.fog; baseDensity = RenderSettings.fogDensity;
            baseFogColor = RenderSettings.fogColor; baseFogMode = RenderSettings.fogMode;
            baseSun = sun != null ? sun.intensity : 1f;
        }

        private void OnDestroy()
        {
            if (Instance != this) return;
            RenderSettings.fog = baseFog; RenderSettings.fogDensity = baseDensity;
            RenderSettings.fogColor = baseFogColor; RenderSettings.fogMode = baseFogMode;
            if (sun != null) sun.intensity = baseSun;
            Instance = null;
        }

        public void SetWeather(WeatherType type)
        {
            if (Current == type) return;
            Current = type;
            target = type == WeatherType.HeavyRain ? 1f : type == WeatherType.LightRain ? 0.5f : 0f;
            OnWeatherChanged?.Invoke(type);
        }

        /// <summary>Applique le temps tout de suite, sans transition (chargement d'une mission).</summary>
        public void SetWeatherImmediate(WeatherType type)
        {
            SetWeather(type);
            Wetness = target;
        }

        private void Update()
        {
            if (!Mathf.Approximately(Wetness, target))
                Wetness = Mathf.MoveTowards(Wetness, target, Time.unscaledDeltaTime / Mathf.Max(0.1f, transitionSeconds));
            if (Mathf.Abs(Wetness - applied) > 0.002f) ApplyVisuals();
        }

        private void ApplyVisuals()
        {
            applied = Wetness;
            bool wet = Wetness > 0.01f;
            RenderSettings.fog = wet || baseFog;
            if (wet)
            {
                RenderSettings.fogMode = FogMode.ExponentialSquared;
                RenderSettings.fogDensity = Mathf.Lerp(baseFog ? baseDensity : 0f, HeavyFogDensity, Wetness);
                RenderSettings.fogColor = Color.Lerp(baseFogColor, RainFogColor, Wetness);
            }
            else
            {
                RenderSettings.fogMode = baseFogMode; RenderSettings.fogDensity = baseDensity; RenderSettings.fogColor = baseFogColor;
            }
            if (sun != null) sun.intensity = baseSun * Mathf.Lerp(1f, HeavySunFactor, Wetness);
        }
    }
}
