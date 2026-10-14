CREATE TABLE bookings (
    code          char(6) PRIMARY KEY,
    flight_id     bigint NOT NULL REFERENCES flights (id),
    status        text NOT NULL DEFAULT 'confirmed' CHECK (status IN ('confirmed', 'cancelled')),
    contact_email text NOT NULL,
    contact_phone text NOT NULL,
    total_price   integer NOT NULL,
    created_at    timestamptz NOT NULL DEFAULT now()
);