<?php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Deterministic pseudo-random integer in [$min, $max], derived from a string seed.
 *
 * Same inputs always produce the same value, so re-seeding is reproducible and
 * identical requests return identical data.
 */
function deterministicInt(string $seed, int $min, int $max): int
{
    $hash = hexdec(substr(md5($seed), 0, 8));

    return $min + (int) ($hash % ($max - $min + 1));
}

$cities = [
    ['MOW', 'Москва', 'Россия'],
    ['LED', 'Санкт-Петербург', 'Россия'],
    ['AER', 'Сочи', 'Россия'],
    ['KZN', 'Казань', 'Россия'],
    ['SVX', 'Екатеринбург', 'Россия'],
    ['OVB', 'Новосибирск', 'Россия'],
    ['KGD', 'Калининград', 'Россия'],
];

$airlines = [
    ['SU', 'Аэрофлот'],
    ['DP', 'Победа'],
    ['S7', 'S7 Airlines'],
    ['U6', 'Уральские авиалинии'],
];

$pdo = pdo();
$pdo->beginTransaction();

try {
    $cityStmt = $pdo->prepare(
        'INSERT INTO cities (code, name, country, sort_order)
         VALUES (:code, :name, :country, :sort_order)
         ON CONFLICT (code) DO UPDATE SET
             name = EXCLUDED.name,
             country = EXCLUDED.country,
             sort_order = EXCLUDED.sort_order'
    );

    foreach ($cities as $index => $city) {
        $cityStmt->execute([
            'code' => $city[0],
            'name' => $city[1],
            'country' => $city[2],
            'sort_order' => $index + 1,
        ]);
    }

    $airlineStmt = $pdo->prepare(
        'INSERT INTO airlines (code, name)
         VALUES (:code, :name)
         ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name'
    );

    foreach ($airlines as $airline) {
        $airlineStmt->execute(['code' => $airline[0], 'name' => $airline[1]]);
    }

    $flightStmt = $pdo->prepare(
        'INSERT INTO flights (
             origin_code, destination_code, airline_code, flight_number,
             departure_at, arrival_at, duration_minutes, price_amount, seats_available
         ) VALUES (
             :origin, :destination, :airline, :flight_number,
             :departure_at, :arrival_at, :duration_minutes, :price_amount, :seats_available
         )
         ON CONFLICT (origin_code, destination_code, departure_at, flight_number) DO UPDATE SET
             airline_code = EXCLUDED.airline_code,
             arrival_at = EXCLUDED.arrival_at,
             duration_minutes = EXCLUDED.duration_minutes,
             price_amount = EXCLUDED.price_amount,
             seats_available = EXCLUDED.seats_available'
    );

    $today = new DateTimeImmutable('today', new DateTimeZone('UTC'));
    $codes = array_column($cities, 0);
    $airlineCodes = array_column($airlines, 0);
    $baseHours = [7, 12, 17];
    $days = 30;
    $seeded = 0;

    foreach ($codes as $origin) {
        foreach ($codes as $destination) {
            if ($origin === $destination) {
                continue;
            }

            for ($day = 0; $day < $days; $day++) {
                $date = $today->modify("+{$day} days");
                $count = 2 + deterministicInt("{$origin}-{$destination}-{$day}-count", 0, 1);

                for ($index = 0; $index < $count; $index++) {
                    $key = "{$origin}-{$destination}-{$day}-{$index}";

                    $airline = $airlineCodes[deterministicInt("{$key}-airline", 0, count($airlineCodes) - 1)];
                    $duration = deterministicInt("{$key}-duration", 80, 280);
                    $minute = deterministicInt("{$key}-minute", 0, 59);
                    $number = deterministicInt("{$key}-number", 100, 9999);
                    $price = deterministicInt("{$key}-price", 3000, 8500);
                    $seats = deterministicInt("{$key}-seats", 10, 90);

                    $departure = $date->setTime($baseHours[$index], $minute);
                    $arrival = $departure->add(new DateInterval("PT{$duration}M"));

                    $flightStmt->execute([
                        'origin' => $origin,
                        'destination' => $destination,
                        'airline' => $airline,
                        'flight_number' => $airline . $number,
                        'departure_at' => $departure->format('Y-m-d H:i:sP'),
                        'arrival_at' => $arrival->format('Y-m-d H:i:sP'),
                        'duration_minutes' => $duration,
                        'price_amount' => $price,
                        'seats_available' => $seats,
                    ]);

                    $seeded++;
                }
            }
        }
    }

    $pdo->commit();
    fwrite(STDOUT, "seeded {$seeded} flights\n");
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, "seed failed: {$exception->getMessage()}\n");
    exit(1);
}