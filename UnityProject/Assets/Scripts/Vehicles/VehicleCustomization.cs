using System;
using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>Personnalisation d'UN véhicule (couleurs, jantes, autocollant, plaque, améliorations).</summary>
    [Serializable]
    public class VehicleCustomization
    {
        public Color bodyColor = new Color(1f, 0.831f, 0f);   // Jaune Taxi Kin #FFD400
        public Color roofColor = new Color(1f, 0.831f, 0f);
        public int rimIndex;                                  // 0 standard, 1 chrome, 2 or
        public string stickerId = "";
        public string plate = "KIN 001";
        public int engine, brakes, tires, armor;              // niveaux 0 à 3

        public VehicleCustomization Clone() => JsonUtility.FromJson<VehicleCustomization>(JsonUtility.ToJson(this));
    }

    /// <summary>Entrée de sauvegarde : personnalisation d'un véhicule identifié par sa clé (Wewa, Taxi, Bus207).</summary>
    [Serializable]
    public class VehicleCustomizationEntry
    {
        public string vehicleKey;
        public VehicleCustomization data;
    }
}
