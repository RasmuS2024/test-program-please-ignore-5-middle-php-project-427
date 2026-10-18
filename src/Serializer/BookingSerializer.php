<?php

namespace Hexlet\Code\Serializer;

final class BookingSerializer
{
    /**
     * Assemble the Booking response shape described by the contract.
     *
     * @param array<string, mixed>       $booking    row with contact_email / contact_phone
     * @param array<string, mixed>       $flight     already serialized Flight
     * @param list<array<string, mixed>> $passengers booking_passengers rows
     *
     * @return array<string, mixed>
     */
    public static function serialize(array $booking, array $flight, array $passengers): array
    {
        return [
            'code' => (string) $booking['code'],
            'status' => (string) $booking['status'],
            'flight' => $flight,
            'passengers' => array_map(
                static fn (array $passenger): array => [
                    'firstName' => (string) $passenger['first_name'],
                    'lastName' => (string) $passenger['last_name'],
                    'dateOfBirth' => (string) $passenger['date_of_birth'],
                    'documentNumber' => (string) $passenger['document_number'],
                ],
                $passengers
            ),
            'contact' => [
                'email' => (string) $booking['contact_email'],
                'phone' => (string) $booking['contact_phone'],
            ],
            'totalPrice' => [
                'amount' => (int) $booking['total_price'],
                'currency' => 'RUB',
            ],
            'createdAt' => DateTimeSerializer::toUtcIso($booking['created_at']),
        ];
    }
}
