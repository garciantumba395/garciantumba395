using System;

namespace TaxiKin.Map
{
    /// <summary>Route d'une zone : tracé en mètres (x,z alternés), largeur et type.</summary>
    [Serializable] public class ZoneRoad { public int kind; public float width; public float[] pts; }

    /// <summary>Immeuble : emprise anti-horaire en mètres (x,z alternés) et hauteur.</summary>
    [Serializable] public class ZoneBuilding { public float h; public float[] pts; }

    /// <summary>Point d'intérêt (gare, marché, hôpital, aéroport…) utilisable comme départ/destination de mission.</summary>
    [Serializable] public class ZonePoi { public string name; public string kind; public float x; public float z; }

    /// <summary>
    /// Contenu d'une zone, produit par Tools/osm_to_zone.py (données © contributeurs OpenStreetMap, ODbL).
    /// Repère local : +x = est, +z = nord, origine = centre de la zone.
    /// </summary>
    [Serializable]
    public class ZoneData
    {
        public string id, name, attribution;
        public double lat, lon;
        public float radius, originX, originZ;   // origine = position réelle de la zone par rapport à la Gare centrale
        public ZoneRoad[] roads;
        public ZoneBuilding[] buildings;
        public ZonePoi[] pois;
    }
}
