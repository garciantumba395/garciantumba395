using System;

namespace TaxiKin.Map
{
    /// <summary>Une zone dans le repère GLOBAL du monde compressé (voir Tools/build_world.py).</summary>
    [Serializable]
    public class WorldZone
    {
        public string id, name;
        public float originX, originZ;     // position du centre de la zone dans le monde
        public float radius, half;         // rayon de la carte ; demi-côté du sol (rayon + 100 m)
        public ZonePoi[] pois;             // repères en coordonnées LOCALES de la zone
    }

    /// <summary>Corridor : route droite entre l'extrémité d'une route principale de la zone A et celle de la zone B (coordonnées globales).</summary>
    [Serializable]
    public class WorldCorridor
    {
        public string a, b;
        public float ax, az, bx, bz;
        public float length, width, stripHalfWidth;
        public float wallStart, wallEnd;   // le long du corridor : là où les murs commencent et finissent (hors des carrés de zone)
    }

    [Serializable]
    public class WorldData
    {
        public string attribution;
        public WorldZone[] zones;
        public WorldCorridor[] corridors;
    }
}
