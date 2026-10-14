CREATE TABLE booking_passengers (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    booking_code    char(6) NOT NULL REFERENCES bookings (code) ON DELETE CASCADE,
    first_name      text NOT NULL,
    last_name       text NOT NULL,
    date_of_birth   date NOT NULL,
    document_number text NOT NULL
);

CREATE INDEX booking_passengers_booking_idx ON booking_passengers (booking_code);
CREATE INDEX booking_passengers_last_name_idx ON booking_passengers (lower(last_name));