using System.Collections.Generic;
using UnityEngine;

namespace TaxiKin.Traffic
{
    /// <summary>
    /// Trafic avec pool d'objets (pas d'Instantiate/Destroy en jeu) et plafond de véhicules actifs
    /// pour tenir 30+ FPS sur Android modeste. L'IA de circulation sera ajoutée plus tard.
    /// </summary>
    public class TrafficManager : MonoBehaviour
    {
        [SerializeField] private GameObject trafficPrefab;
        [SerializeField, Range(0, 40)] private int maxActiveVehicles = 12;

        private readonly Queue<GameObject> pool = new Queue<GameObject>();
        private readonly List<GameObject> active = new List<GameObject>();

        public int ActiveCount => active.Count;

        /// <summary>Change la densité (ex. faible en qualité basse, dense pour « La grande course »).</summary>
        public void SetMaxVehicles(int max) => maxActiveVehicles = Mathf.Max(0, max);

        public GameObject Spawn(Vector3 position, Quaternion rotation)
        {
            if (trafficPrefab == null || active.Count >= maxActiveVehicles) return null;
            GameObject go = pool.Count > 0 ? pool.Dequeue() : Instantiate(trafficPrefab, transform);
            go.transform.SetPositionAndRotation(position, rotation);
            go.SetActive(true);
            active.Add(go);
            return go;
        }

        public void Despawn(GameObject go)
        {
            if (go == null || !active.Remove(go)) return;
            go.SetActive(false);
            pool.Enqueue(go);
        }
    }
}
