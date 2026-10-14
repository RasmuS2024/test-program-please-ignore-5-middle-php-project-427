CREATE TABLE flights (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    origin_code      varchar(3) NOT NULL REFERENCES cities (code),
    destination_code varchar(3) NOT NULL REFERENCES cities (code),
    airline_code     varchar(2) NOT NULL REFERENCES airlines (code),
    flight_number    text NOT NULL,
    departure_at     timestamptz NOT NULL,
    arrival_at       timestamptz NOT NULL,
    duration_minutes integer NOT NULL,
    price_amount     integer NOT NULL,
    seats_available  integer NOT NULL,
    CONSTRAINT flights_route_unique UNIQUE (origin_code, destination_code, departure_at, flight_number),
    CONSTRAINT flights_distinct_cities CHECK (origin_code <> destination_code)
);

CREATE INDEX flights_search_idx ON flights (origin_code, destination_code, departure_at);
CREATE INDEX flights_departure_idx ON flights (departure_at);