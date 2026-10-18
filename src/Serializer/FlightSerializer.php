<?php

namespace Hexlet\Code\Serializer;

final class FlightSerializer
{
    /**
     * Turn a joined flight row into the API shape described by the contract.
     *
     * Numeric columns are cast explicitly: PostgreSQL returns integers as
     * strings through PDO, which would break the contract.
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function serialize(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'flightNumber' => (string) $row['flight_number'],
            'airline' => [
                'code' => (string) $row['airline_code'],
                'name' => (string) $row['airline_name'],
            ],
            'origin' => [
                'code' => (string) $row['origin_code'],
                'name' => (string) $row['origin_name'],
                'country' => (string) $row['origin_country'],
            ],
            'destination' => [
                'code' => (string) $row['destination_code'],
                'name' => (string) $row['destination_name'],
                'country' => (string) $row['destination_country'],
            ],
            'departureAt' => DateTimeSerializer::toUtcIso($row['departure_at']),
            'arrivalAt' => DateTimeSerializer::toUtcIso($row['arrival_at']),
            'durationMinutes' => (int) $row['duration_minutes'],
            'price' => [
                'amount' => (int) $row['price_amount'],
                'currency' => 'RUB',
            ],
            'seatsAvailable' => (int) $row['seats_available'],
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    public static function many(array $rows): array
    {
        return array_map([self::class, 'serialize'], $rows);
    }
}
