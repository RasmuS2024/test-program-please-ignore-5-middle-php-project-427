CREATE TABLE cities (
    code       varchar(3) PRIMARY KEY,
    name       text NOT NULL,
    country    text NOT NULL,
    sort_order integer NOT NULL DEFAULT 0
);