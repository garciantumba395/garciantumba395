using System;
using UnityEngine;

namespace TaxiKin.Weather
{
    public enum WeatherType { Clear, LightRain, HeavyRain }

    /// <summary>Météo : influence l'adhérence de la route (missions « Course sous la pluie »).</summary>
    public class WeatherManager : MonoBehaviour
    {
        public WeatherType Current { get; private set; } = WeatherType.Clear;
        public event Action<WeatherType> OnWeatherChanged;

        /// <summary>Coefficient d'adhérence : 1 = sec, plus bas = glissant.</summary>
        public float RoadGrip => Current switch
        {
            WeatherType.LightRain => 0.8f,
            WeatherType.HeavyRain => 0.6f,
            _ => 1f
        };

        public void SetWeather(WeatherType type)
        {
            if (Current == type) return;
            Current = type;
            OnWeatherChanged?.Invoke(type);   // Les effets visuels (pluie) s'abonnent ici
        }
    }
}
