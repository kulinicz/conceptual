<?php

namespace Kulinicz\FactoryMethod\Conceptual;

/**
 * Conceptual code example for interview 
 * The calculator class calculates the distance between two points on Earth
 * to demonstrate knowledge about different design patterns
 *
 * Used patterns:
 * - Strategy Pattern
 * - Factory Method Pattern
 * - Decorator Pattern
 * - Singleton Pattern
 * - Dependency Injection Pattern (simple without DI container)
 */


/**
 * The Config class mimics the Singleton pattern
 * by providing a global point of access to configuration settings.
 */
class Config {
    private static $config = [
        'google_api_key' => 'YOUR_GOOGLE_API_KEY' // Replace with your actual API key
    ];

    /**
     * Gets the value of a configuration key.
     *
     * @param string $key The configuration key.
     * @return string The value of the configuration key.
     * @throws \Exception If the configuration key is not found.
     */
    public static function get(string $key): string {
        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }
        throw new \Exception("Configuration key $key not found.");
    }
}

// Custom Exceptions
class DistanceCalculationException extends \Exception {}

class GoogleApiException extends DistanceCalculationException {}

interface DistanceStrategy {
    /**
     * Calculates the distance between two geographical points.
     *
     * @param float $lat1 Latitude of the first point.
     * @param float $lon1 Longitude of the first point.
     * @param float $lat2 Latitude of the second point.
     * @param float $lon2 Longitude of the second point.
     * @return float The calculated distance in kilometers.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float;
}

/**
 * Implementation of DistanceStrategy using mathematical formulas.
 */
class MathematicalDistanceStrategy implements DistanceStrategy {
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        try {
            $earthRadius = 6371; // Radius of the Earth in kilometers

            $latFrom = deg2rad($lat1);
            $lonFrom = deg2rad($lon1);
            $latTo = deg2rad($lat2);
            $lonTo = deg2rad($lon2);

            $latDelta = $latTo - $latFrom;
            $lonDelta = $lonTo - $lonFrom;

            $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
                    cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

            return $angle * $earthRadius;
        } catch (\Exception $e) {
            throw new DistanceCalculationException("Mathematical calculation failed: " . $e->getMessage());
        }
    }
}

/**
 * Implementation of DistanceStrategy using Google Maps API.
 */
class GoogleApiDistanceStrategy implements DistanceStrategy {
    private $apiKey;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        try {
            $url = "https://maps.googleapis.com/maps/api/distancematrix/json?units=metric&origins=$lat1,$lon1&destinations=$lat2,$lon2&key=" . $this->apiKey;
            $response = file_get_contents($url);

            if ($response === FALSE) {
                throw new GoogleApiException("Error fetching data from Google Maps API");
            }

            $data = json_decode($response);

            if ($data->status == 'OK') {
                return $data->rows[0]->elements[0]->distance->value / 1000; // Convert meters to kilometers
            } else {
                throw new GoogleApiException("Google Maps API error: " . $data->status);
            }
        } catch (\Exception $e) {
            throw new GoogleApiException("Google Maps API calculation failed: " . $e->getMessage());
        }
    }
}

/**
 * Abstract factory for creating distance strategy instances.
 */
abstract class DistanceStrategyFactory {
    /**
     * Creates a distance strategy instance.
     *
     * @return DistanceStrategy The created distance strategy instance.
     */
    abstract public function createStrategy(): DistanceStrategy;
}

/**
 * Factory for creating mathematical distance strategy instances.
 */
class MathematicalDistanceStrategyFactory extends DistanceStrategyFactory {
    public function createStrategy(): DistanceStrategy {
        return new MathematicalDistanceStrategy();
    }
}

/**
 * Factory for creating Google API distance strategy instances.
 */
class GoogleApiDistanceStrategyFactory extends DistanceStrategyFactory {
    private $apiKey;

    public function __construct(string $apiKey) {
        $this->apiKey = $apiKey;
    }

    public function createStrategy(): DistanceStrategy {
        return new GoogleApiDistanceStrategy($this->apiKey);
    }
}

/**
 * Abstract decorator class for distance strategy.
 */
abstract class DistanceStrategyDecorator implements DistanceStrategy {
    protected $wrapped;

    public function __construct(DistanceStrategy $wrapped) {
        $this->wrapped = $wrapped;
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        return $this->wrapped->calculateDistance($lat1, $lon1, $lat2, $lon2);
    }
}

/**
 * Logging decorator for distance strategy.
 */
class LoggingDistanceStrategyDecorator extends DistanceStrategyDecorator {
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $distance = parent::calculateDistance($lat1, $lon1, $lat2, $lon2);
        echo "Calculated distance: $distance km\n"; // Example log
        return $distance;
    }
}

/**
 * The calculator class calculates the distance between two points on Earth.
 */
class DistanceCalculator {
    private $strategy;

    public function __construct(DistanceStrategyFactory $factory) {
        $this->strategy = new LoggingDistanceStrategyDecorator($factory->createStrategy());
    }

    public function setStrategy(DistanceStrategyFactory $factory): void {
        $this->strategy = new LoggingDistanceStrategyDecorator($factory->createStrategy());
    }

    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): ?float {
        try {
            return $this->strategy->calculateDistance($lat1, $lon1, $lat2, $lon2);
        } catch (DistanceCalculationException $e) {
            // Log the error or handle it accordingly
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
}

// Example Usage
$lat1 = 40.712776;
$lon1 = -74.005974;
$lat2 = 34.052235;
$lon2 = -118.243683;

// Using the mathematical strategy factory
$mathFactory = new MathematicalDistanceStrategyFactory();
$distanceCalculator = new DistanceCalculator($mathFactory);
echo "Mathematical Distance: " . $distanceCalculator->calculateDistance($lat1, $lon1, $lat2, $lon2) . " km\n";

// Using the Google API strategy factory
$googleApiKey = Config::get('google_api_key');
$googleFactory = new GoogleApiDistanceStrategyFactory($googleApiKey);
$distanceCalculator->setStrategy($googleFactory);
echo "Google API Distance: " . $distanceCalculator->calculateDistance($lat1, $lon1, $lat2, $lon2) . " km\n";
