namespace TaxiKin.Vehicles
{
    /// <summary>Bus 207 : lent, large, très rentable, transport collectif (10 à 20 passagers).</summary>
    public class Bus207Controller : VehicleController
    {
        public override VehicleType Kind => VehicleType.Bus207;

        /// <summary>Portes ouvertes (utilisé par les missions d'arrêt, Étape 2/3).</summary>
        public bool DoorsOpen { get; private set; }
        public void SetDoors(bool open) => DoorsOpen = open;

        protected override VehicleStats CreateDefaultStats() => new VehicleStats
        {
            maxSpeedKmh = 50f, acceleration = 4f, brakeForce = 10f, turnRate = 55f,
            passengerCapacity = 20, fuelCapacity = 80f, fuelPerKm = 0.2f, fragility = 0.6f
        };
    }
}
