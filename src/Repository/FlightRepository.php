<?php

namespace Hexlet\Code\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class FlightRepository
{
    private const SELECT_COLUMNS =
        'f.id, f.flight_number, f.departure_at, f.arrival_at, f.duration_minutes, '
        . 'f.price_amount, f.seats_available, '
        . 'al.code AS airline_code, al.name AS airline_name, '
        . 'o.code AS origin_code, o.name AS origin_name, o.country AS origin_country, '
        . 'd.code AS destination_code, d.name AS destination_name, d.country AS destination_country';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Flights on a route departing within the given UTC calendar day that have
     * at least $passengers seats available.
     *
     * Cities and the airline are joined in one query so each flight is returned
     * with its full related objects (no per-flight lookups).
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $origin, string $destination, string $date, int $passengers): array
    {
        // The stored column is a timestamp, the filter is a calendar day. Build
        // the [start, end) range in the same timezone the departure is rendered
        // in (UTC), otherwise early-morning flights land in the wrong day.
        $start = new DateTimeImmutable("{$date} 00:00:00", new DateTimeZone('UTC'));
        $end = $start->modify('+1 day');

        $sql = 'SELECT ' . self::SELECT_COLUMNS . '
            FROM flights f
            JOIN airlines al ON al.code = f.airline_code
            JOIN cities o ON o.code = f.origin_code
            JOIN cities d ON d.code = f.destination_code
            WHERE f.origin_code = :origin
              AND f.destination_code = :destination
              AND f.departure_at >= :start
              AND f.departure_at < :end
              AND f.seats_available >= :passengers
            ORDER BY f.departure_at, f.id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'origin' => $origin,
            'destination' => $destination,
            'start' => $start->format('Y-m-d H:i:sP'),
            'end' => $end->format('Y-m-d H:i:sP'),
            'passengers' => $passengers,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * A single flight by identifier, or null when it does not exist.
     *
     * The id is compared as an integer; a non-numeric id can never match and is
     * rejected before it reaches PostgreSQL (which would error on the cast).
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        if ($id === '' || !ctype_digit($id)) {
            return null;
        }

        $sql = 'SELECT ' . self::SELECT_COLUMNS . '
            FROM flights f
            JOIN airlines al ON al.code = f.airline_code
            JOIN cities o ON o.code = f.origin_code
            JOIN cities d ON d.code = f.destination_code
            WHERE f.id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => (int) $id]);

        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }
}
