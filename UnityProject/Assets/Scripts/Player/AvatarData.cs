using System;
using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Player
{
    /// <summary>
    /// Données sérialisables de l'avatar (sauvegardées en JSON par SaveManager).
    /// Ne contient que des identifiants et des couleurs : les modèles 3D sont chargés à la demande.
    /// </summary>
    [Serializable]
    public class AvatarData
    {
        public string driverName = "";
        public int style;                       // 0 = svelte, 1 = standard, 2 = costaud
        public int skinIndex = 2;
        public int clothingColorIndex;          // 0 = jaune Taxi Kin
        public Color skinColor = new Color(0.55f, 0.37f, 0.25f);
        public Color clothingColor = new Color(1f, 0.831f, 0f);
        public string faceId = "face_default";
        public string hairId = "hair_short";
        public string topId = "top_tshirt";
        public string bottomId = "bottom_jeans";
        public string shoesId = "shoes_sneakers";
        public List<string> accessoryIds = new List<string>();

        public AvatarData Clone() => JsonUtility.FromJson<AvatarData>(JsonUtility.ToJson(this));
    }
}
