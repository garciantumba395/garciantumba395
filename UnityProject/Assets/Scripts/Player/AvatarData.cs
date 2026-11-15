using System;
using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Données sérialisables de l'avatar (sauvegardées en JSON par SaveManager).
    /// Ne stocke que des identifiants : les prefabs sont chargés à la demande.
    /// </summary>
    [Serializable]
    public class AvatarData
    {
        public string driverName = "Chauffeur";
        public int style;                       // Style du personnage (corpulence/modèle de base)
        public Color skinColor = new Color(0.55f, 0.37f, 0.25f);
        public Color clothingColor = Color.white;
        public string faceId;
        public string hairId;
        public string topId;
        public string bottomId;
        public string shoesId;
        public List<string> accessoryIds = new List<string>();

        public AvatarData Clone() => JsonUtility.FromJson<AvatarData>(JsonUtility.ToJson(this));
    }
}
