<?php

namespace Hexlet\Code\Serializer;

final class BookingSerializer
{
    /**
     * Assemble the Booking response shape described by the contract.
     *
     * @param array<string, mixed>                    $booking    row: code, status, total_price, created_at
     * @param array<string, mixed>                    $flight     already serialized Flight
     * @param list<array<string, mixed>>              $passengers normalized passenger input
     * @param array{email: string, phone: string}     $contact
     *
     * @return array<string, mixed>
     */
    public static function serialize(array $booking, array $flight, array $passengers, array $contact): array
    {
        return [
            'code' => (string) $booking['code'],
            'status' => (string) $booking['status'],
            'flight' => $flight,
            'passengers' => array_map(
                static fn (array $passenger): array => [
                    'firstName' => (string) $passenger['firstName'],
                    'lastName' => (string) $passenger['lastName'],
                    'dateOfBirth' => (string) $passenger['dateOfBirth'],
                    'documentNumber' => (string) $passenger['documentNumber'],
                ],
                $passengers
            ),
            'contact' => [
                'email' => (string) $contact['email'],
                'phone' => (string) $contact['phone'],
            ],
            'totalPrice' => [
                'amount' => (int) $booking['total_price'],
                'currency' => 'RUB',
            ],
            'createdAt' => DateTimeSerializer::toUtcIso($booking['created_at']),
        ];
    }
}
