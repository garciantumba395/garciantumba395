namespace TaxiKin.Vehicles
{
    /// <summary>Wewa : moto-taxi rapide, agile, fragile, un seul passager.</summary>
    public class WewaController : VehicleController
    {
        public override VehicleType Kind => VehicleType.Wewa;

        protected override VehicleStats CreateDefaultStats() => new VehicleStats
        {
            maxSpeedKmh = 90f, acceleration = 11f, brakeForce = 18f, turnRate = 130f,
            passengerCapacity = 1, fuelCapacity = 10f, fuelPerKm = 0.04f, fragility = 2f
        };
    }
}
