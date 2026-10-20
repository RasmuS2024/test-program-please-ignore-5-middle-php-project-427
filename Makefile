update:
	composer update

install:
	composer install
	npm ci

validate:
	composer validate

build:
	rm -rf public/assets public/index.html
	cp -R node_modules/@hexlet/php-flight-booking-frontend/dist/. public/

start:
	php bin/migrate.php
	php bin/seed.php
	php -S 0.0.0.0:$${PORT:-8080} -t public public/index.php

migrate:
	php bin/migrate.php

seed:
	php bin/seed.php

contract:
	npx tsp compile contract

lint:
	composer exec --verbose phpcs

test:
	composer exec --verbose phpunit tests

.PHONY: update install validate build start migrate seed contract lint test
