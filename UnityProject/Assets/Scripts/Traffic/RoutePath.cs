using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Traffic
{
    /// <summary>
    /// Trajet = suite de points avec leur classe de route. Fournit : projection d'une position sur le trajet (abscisse curviligne),
    /// point et cap à une abscisse donnée. Utilisé par VehicleAI (suivi) et RaceManager (progression des coureurs).
    /// Peut grandir au fil de l'eau (trafic en promenade aléatoire).
    /// </summary>
    public class RoutePath
    {
        public readonly List<Vector3> Points = new List<Vector3>();
        public readonly List<sbyte> Kinds = new List<sbyte>();     // Kinds[i] = classe du tronçon Points[i] → Points[i+1]
        private readonly List<float> cum = new List<float>();

        public int Count => Points.Count;
        public float Length => cum.Count > 0 ? cum[cum.Count - 1] : 0f;

        public void Clear() { Points.Clear(); Kinds.Clear(); cum.Clear(); }

        public void Add(Vector3 p, sbyte kindFromPrevious)
        {
            if (Points.Count == 0) cum.Add(0f);
            else { cum.Add(cum[cum.Count - 1] + Flat(Points[Points.Count - 1], p)); Kinds.Add(kindFromPrevious); }
            Points.Add(p);
        }

        public void Set(List<Vector3> pts, List<sbyte> kinds)
        {
            Clear();
            for (int i = 0; i < pts.Count; i++) Add(pts[i], i > 0 ? kinds[i - 1] : (sbyte)0);
        }

        private static float Flat(Vector3 a, Vector3 b) { float dx = a.x - b.x, dz = a.z - b.z; return Mathf.Sqrt(dx * dx + dz * dz); }

        /// <summary>Projette 'pos' sur le trajet autour du tronçon 'hint' ; retourne l'abscisse (m) et met à jour 'segment' et 'error' (écart latéral).</summary>
        public float Project(Vector3 pos, ref int segment, out float error)
        {
            error = float.MaxValue;
            float bestS = 0f;
            int from = Mathf.Max(0, segment - 3), to = Mathf.Min(Points.Count - 2, segment + 40);
            for (int i = from; i <= to; i++)
            {
                Vector3 a = Points[i], b = Points[i + 1];
                float dx = b.x - a.x, dz = b.z - a.z;
                float l2 = dx * dx + dz * dz;
                float t = l2 < 1e-6f ? 0f : Mathf.Clamp01(((pos.x - a.x) * dx + (pos.z - a.z) * dz) / l2);
                float qx = a.x + dx * t - pos.x, qz = a.z + dz * t - pos.z;
                float d = Mathf.Sqrt(qx * qx + qz * qz);
                if (d < error) { error = d; segment = i; bestS = cum[i] + t * Mathf.Sqrt(l2); }
            }
            return bestS;
        }

        /// <summary>Point à l'abscisse s (bornée) ; 'segment' = tronçon qui le contient.</summary>
        public Vector3 PointAt(float s, out int segment)
        {
            if (Points.Count == 0) { segment = 0; return Vector3.zero; }
            if (Points.Count == 1) { segment = 0; return Points[0]; }
            s = Mathf.Clamp(s, 0f, Length);
            int lo = 0, hi = cum.Count - 1;
            while (hi - lo > 1)
            {
                int mid = (lo + hi) / 2;
                if (cum[mid] <= s) lo = mid; else hi = mid;
            }
            segment = lo;
            float len = cum[lo + 1] - cum[lo];
            float t = len < 1e-4f ? 0f : (s - cum[lo]) / len;
            return Vector3.Lerp(Points[lo], Points[lo + 1], t);
        }

        /// <summary>Cap (degrés, 0 = nord = +z) du trajet à l'abscisse s.</summary>
        public float HeadingAt(float s)
        {
            PointAt(s, out int seg);
            if (seg + 1 >= Points.Count) return 0f;
            Vector3 d = Points[seg + 1] - Points[seg];
            return Mathf.Atan2(d.x, d.z) * Mathf.Rad2Deg;
        }

        public sbyte KindAt(int segment) => Kinds.Count == 0 ? (sbyte)6 : Kinds[Mathf.Clamp(segment, 0, Kinds.Count - 1)];
    }
}
