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
	php -S 0.0.0.0:$${PORT:-8080} -t public public/index.php

lint:
	composer exec --verbose phpcs -- --standard=PSR12 src bin tests
	composer exec -v phpstan analyse -- -c phpstan.neon --ansi

test:
	composer exec --verbose phpunit tests

test-coverage:
	XDEBUG_MODE=coverage composer exec --verbose phpunit tests -- --coverage-clover build/logs/clover.xml

test-coverage-text:
	XDEBUG_MODE=coverage composer exec --verbose phpunit tests -- --coverage-text

test-coverage-html:
	XDEBUG_MODE=coverage composer exec phpunit tests -- --coverage-html build/over