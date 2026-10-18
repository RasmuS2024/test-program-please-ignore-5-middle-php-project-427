<?php

namespace Hexlet\Code\Repository;

use PDO;
use PDOException;
use RuntimeException;

final class BookingRepository
{
    private const CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const CODE_LENGTH = 6;
    private const MAX_CODE_ATTEMPTS = 5;
    private const UNIQUE_VIOLATION = '23505';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Persist a booking together with its passengers in a single transaction and
     * return the created booking row.
     *
     * The booking code is unique by primary key. Pre-checking cannot guarantee
     * uniqueness under concurrency, so a collision is caught from the database:
     * PostgreSQL aborts the transaction, we roll it back and retry with a fresh
     * code.
     *
     * @param array<string, mixed>              $flight
     * @param array{email: string, phone: string} $contact
     * @param list<array<string, mixed>>        $passengers
     *
     * @return array<string, mixed>
     */
    public function create(array $flight, array $contact, array $passengers): array
    {
        $totalPrice = (int) $flight['price_amount'] * count($passengers);

        for ($attempt = 1; $attempt <= self::MAX_CODE_ATTEMPTS; $attempt++) {
            $code = self::generateCode();

            $this->pdo->beginTransaction();

            try {
                $booking = $this->insertBooking($code, (int) $flight['id'], $contact, $totalPrice);
                $this->insertPassengers($code, $passengers);
                $this->pdo->commit();

                return $booking;
            } catch (PDOException $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }

                if ($exception->getCode() === self::UNIQUE_VIOLATION && $attempt < self::MAX_CODE_ATTEMPTS) {
                    continue;
                }

                throw $exception;
            }
        }

        throw new RuntimeException('Не удалось сгенерировать уникальный код брони');
    }

    /**
     * @param array{email: string, phone: string} $contact
     *
     * @return array<string, mixed>
     */
    private function insertBooking(string $code, int $flightId, array $contact, int $totalPrice): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bookings (code, flight_id, status, contact_email, contact_phone, total_price)
             VALUES (:code, :flight_id, \'confirmed\', :email, :phone, :total_price)
             RETURNING code, status, total_price, created_at'
        );
        $stmt->execute([
            'code' => $code,
            'flight_id' => $flightId,
            'email' => $contact['email'],
            'phone' => $contact['phone'],
            'total_price' => $totalPrice,
        ]);

        return $stmt->fetch();
    }

    /**
     * @param list<array<string, mixed>> $passengers
     */
    private function insertPassengers(string $code, array $passengers): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO booking_passengers
                 (booking_code, first_name, last_name, date_of_birth, document_number)
             VALUES (:code, :first_name, :last_name, :date_of_birth, :document_number)'
        );

        foreach ($passengers as $passenger) {
            $stmt->execute([
                'code' => $code,
                'first_name' => $passenger['firstName'],
                'last_name' => $passenger['lastName'],
                'date_of_birth' => $passenger['dateOfBirth'],
                'document_number' => $passenger['documentNumber'],
            ]);
        }
    }

    private static function generateCode(): string
    {
        $alphabet = self::CODE_ALPHABET;
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
