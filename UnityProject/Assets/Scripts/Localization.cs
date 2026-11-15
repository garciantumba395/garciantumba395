using System;
using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Core
{
    public enum Language { Fr = 0, Ln = 1 }

    /// <summary>
    /// Textes du jeu en français et en lingala. Une clé absente en lingala retombe sur le français.
    /// IMPORTANT : les traductions lingala doivent être validées par un locuteur natif avant la sortie.
    /// </summary>
    public static class Loc
    {
        private const string KLang = "lang";
        private static bool loaded;
        private static Language current = Language.Fr;
        public static event Action Changed;

        public static Language Current
        {
            get { Ensure(); return current; }
            set { Ensure(); current = value; PlayerPrefs.SetInt(KLang, (int)value); Changed?.Invoke(); }
        }

        private static void Ensure()
        {
            if (loaded) return;
            loaded = true;
            current = (Language)Mathf.Clamp(PlayerPrefs.GetInt(KLang, 0), 0, 1);
        }

        private static readonly Dictionary<string, string> Fr = new Dictionary<string, string>
        {
            ["gas"] = "Accélérer", ["brake"] = "Frein", ["handbrake"] = "Frein à main", ["horn"] = "Klaxon",
            ["headlights"] = "Phares", ["wipers"] = "Essuie-glaces", ["camera"] = "Caméra",
            ["passenger"] = "Passager", ["pickup"] = "Prendre le client", ["dropoff"] = "Déposer le client",
            ["pickup_all"] = "Prendre les passagers", ["drop_all"] = "Déposer les passagers",
            ["doors"] = "Portes", ["stop"] = "Arrêt",
            ["hud_money"] = "Kin Coins", ["hud_passengers"] = "Passagers", ["hud_fuel"] = "Carburant",
            ["hud_damage"] = "Dommages", ["hud_time"] = "Temps", ["hud_distance"] = "Distance",
            ["mission_start"] = "Commencer", ["mission_retry"] = "Rejouer", ["mission_next"] = "Mission suivante",
            ["mission_success"] = "Mission réussie !", ["mission_failed"] = "Mission échouée",
            ["mission_vehicle"] = "Véhicule", ["mission_route"] = "Trajet", ["mission_reward"] = "Récompense",
            ["mission_time"] = "Temps", ["mission_any"] = "Au choix", ["mission_menu"] = "Mission",
        };

        // Volontairement minimal : beaucoup de termes techniques sont des emprunts au français en lingala.
        private static readonly Dictionary<string, string> Ln = new Dictionary<string, string>
        {
            ["hud_money"] = "Mbongo",
        };

        public static string T(string key)
        {
            Ensure();
            if (current == Language.Ln && Ln.TryGetValue(key, out var ln)) return ln;
            return Fr.TryGetValue(key, out var fr) ? fr : key;
        }
    }
}
