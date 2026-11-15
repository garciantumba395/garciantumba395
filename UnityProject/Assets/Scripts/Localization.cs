using System;
using System.Collections.Generic;
using UnityEngine;
using TaxiKin.SaveSystem;

namespace TaxiKin.Core
{
    public enum Language { Fr = 0, Ln = 1 }

    /// <summary>
    /// Textes du jeu en français et en lingala. Une clé absente en lingala retombe sur le français.
    /// IMPORTANT : les traductions lingala doivent être validées par un locuteur natif avant la sortie.
    /// </summary>
    public static class Loc
    {
        public static event Action Changed;

        /// <summary>Langue choisie, stockée dans la sauvegarde (français par défaut).</summary>
        public static Language Current
        {
            get
            {
                var sm = SaveManager.Instance;
                return sm == null ? Language.Fr : (Language)Mathf.Clamp(sm.Data.settings.language, 0, 1);
            }
            set
            {
                var sm = SaveManager.Instance;
                if (sm == null) return;
                sm.Data.settings.language = (int)value;
                sm.SaveSoon();
                Changed?.Invoke();
            }
        }

        /// <summary>À appeler après un chargement ou une réinitialisation pour rafraîchir les textes.</summary>
        public static void NotifyChanged() => Changed?.Invoke();

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
            if (Current == Language.Ln && Ln.TryGetValue(key, out var ln)) return ln;
            return Fr.TryGetValue(key, out var fr) ? fr : key;
        }
    }
}
