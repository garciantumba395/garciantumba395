using System;
using UnityEngine;

namespace TaxiKin.Vehicles
{
    /// <summary>Les trois véhicules jouables.</summary>
    public enum VehicleType { Wewa, Taxi, Bus207 }

    /// <summary>Caractéristiques d'un véhicule (modifiables dans l'Inspector).</summary>
    [Serializable]
    public class VehicleStats
    {
        public float maxSpeedKmh = 60f;
        public float acceleration = 8f;   // m/s²
        public float brakeForce = 15f;    // m/s²
        public float turnRate = 90f;      // degrés/seconde à pleine vitesse
        public int passengerCapacity = 1;
        public float fuelCapacity = 20f;
        public float fuelPerKm = 0.08f;
        public float fragility = 1f;      // multiplicateur de dégâts
    }

    /// <summary>
    /// Base commune des véhicules : conduite arcade simple basée sur un Rigidbody.
    /// Les sous-classes ne définissent que leurs stats par défaut.
    /// Les entrées (direction, gaz, frein…) viennent de PlayerController (Étape 2 : commandes tactiles).
    /// </summary>
    [RequireComponent(typeof(Rigidbody))]
    public abstract class VehicleController : MonoBehaviour
    {
        [SerializeField] protected VehicleStats stats;
        [SerializeField, HideInInspector] private bool statsInitialized;

        public abstract VehicleType Kind { get; }
        protected abstract VehicleStats CreateDefaultStats();

        // --- Entrées normalisées ---
        public float Steer { get; private set; }      // -1 (gauche) .. 1 (droite)
        public float Throttle { get; private set; }   // 0 .. 1
        public float Brake { get; private set; }      // 0 .. 1
        public bool HandBrake { get; private set; }
        public bool Reverse { get; set; }             // Marche arrière (sera piloté par la boîte D/N/R)

        // --- État ---
        public float Fuel { get; private set; }
        public float Damage { get; private set; }     // 0..100
        public float SpeedKmh { get; private set; }
        public VehicleStats Stats => stats;

        private Rigidbody rb;

        protected virtual void Awake()
        {
            rb = GetComponent<Rigidbody>();
            if (!statsInitialized) { stats = CreateDefaultStats(); statsInitialized = true; }
            rb.centerOfMass = new Vector3(0f, -0.4f, 0f); // Plus stable
            Fuel = stats.fuelCapacity;
        }

        /// <summary>Reçoit les entrées du joueur (appelé par PlayerController).</summary>
        public void SetInputs(float steer, float throttle, float brake, bool handBrake)
        {
            Steer = Mathf.Clamp(steer, -1f, 1f);
            Throttle = Mathf.Clamp01(throttle);
            Brake = Mathf.Clamp01(brake);
            HandBrake = handBrake;
        }

        private void FixedUpdate()
        {
            Vector3 velocity = rb.GetPointVelocity(rb.worldCenterOfMass);
            float forwardSpeed = Vector3.Dot(velocity, transform.forward);
            SpeedKmh = Mathf.Abs(forwardSpeed) * 3.6f;

            float dir = Reverse ? -1f : 1f;

            // Accélération (sans carburant : plus de poussée)
            if (Throttle > 0f && Fuel > 0f && SpeedKmh < stats.maxSpeedKmh)
                rb.AddForce(transform.forward * (dir * Throttle * stats.acceleration), ForceMode.Acceleration);

            // Freinage : force opposée à la vitesse
            float brakeAmount = HandBrake ? 1f : Brake;
            if (brakeAmount > 0f && velocity.sqrMagnitude > 0.01f)
                rb.AddForce(-velocity.normalized * (brakeAmount * stats.brakeForce), ForceMode.Acceleration);

            // Direction : proportionnelle à la vitesse (pas de rotation à l'arrêt)
            float speedFactor = Mathf.Clamp01(SpeedKmh / 15f);
            float yaw = Steer * stats.turnRate * speedFactor * Mathf.Sign(forwardSpeed == 0f ? 1f : forwardSpeed) * Time.fixedDeltaTime;
            rb.MoveRotation(rb.rotation * Quaternion.Euler(0f, yaw, 0f));

            ConsumeFuel(Mathf.Abs(forwardSpeed) * Time.fixedDeltaTime);
        }

        private void ConsumeFuel(float meters)
        {
            if (Throttle <= 0f) return;
            Fuel = Mathf.Max(0f, Fuel - (meters / 1000f) * stats.fuelPerKm);
        }

        private void OnCollisionEnter(Collision collision)
        {
            // Les dégâts dépendent de la force de l'impact et de la fragilité du véhicule
            float impact = collision.relativeVelocity.magnitude;
            if (impact < 3f) return;
            Damage = Mathf.Clamp(Damage + impact * stats.fragility, 0f, 100f);
        }

        public void Refuel(float amount) => Fuel = Mathf.Min(stats.fuelCapacity, Fuel + amount);
        public void Repair(float amount) => Damage = Mathf.Max(0f, Damage - amount);
    }
}
