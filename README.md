# Бекенд для бронирования авиабилетов (PHP)

[![hexlet-check](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-427/actions/workflows/hexlet-check.yml/badge.svg)](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-427/actions)
[![tests](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-427/actions/workflows/tests.yml/badge.svg)](https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-427/actions/workflows/tests.yml)

Бекенд сервиса бронирования авиабилетов: справочник городов, поиск рейсов, оформление, просмотр
и отмена брони. Приложение отдаёт данные по контракту, описанному в `contract/`, и хранит их в
PostgreSQL. Фронтенд предоставляет Хекслет — готовое приложение, которое подключается к API и
работает только тогда, когда API отвечает по описанному контракту.

Учебный проект Хекслета: https://ru.hexlet.io/programs/test-program-please-ignore-5-middle-php
Как это должно работать: https://files.hexlet.app/a/5bi6gu

## Реализованное приложение

https://test-program-please-ignore-5-middle-php.onrender.com

Сервис развёрнут на [Render](https://render.com/) в контейнере, работает с PostgreSQL на Render и
отдаёт сразу и собранный фронтенд, и API. Деплой автоматический: пуш в `main` пересобирает и
перезапускает сервис.

## Стек

- PHP 8.3+ (Slim 4, PSR-7 / PSR-15)
- PostgreSQL через PDO (`pdo_pgsql`)
- TypeSpec — контракт API в `contract/`, генерация OpenAPI (`contract/openapi.yaml`)
- PHPUnit 12 — тесты
- Фронтенд — npm-пакет `@hexlet/php-flight-booking-frontend`, копируется в `public/`

## Установка

### Требования

- PHP 8.3+ с расширением `pdo_pgsql`
- Composer
- Node.js 22+ (сборка фронтенда и генерация контракта)
- PostgreSQL

### Шаги

```bash
git clone https://github.com/RasmuS2024/test-program-please-ignore-5-middle-php-project-427.git
cd test-program-please-ignore-5-middle-php-project-427
```

```bash
# зависимости PHP и Node
make install

# переменные окружения: скопируйте пример и укажите свою базу
cp .env.example .env
# DATABASE_URL=postgresql://user:password@localhost:5432/dbname   # обязательна
# PORT=8080                                                        # локально не обязательна (по умолчанию 8080)

# собранный фронтенд Хекслета в public/ (index.html + assets)
make build
```

`DATABASE_URL` — единственная обязательная переменная: строка подключения к PostgreSQL.
`PORT` в проде задаёт Render, локально используется значение по умолчанию.

## Использование

### Запуск

```bash
make start   # миграции + данные + сервер
```

Приложение: <http://localhost:8080>

`make start` применяет миграции (`make migrate`), загружает данные (`make seed`) и поднимает
встроенный сервер PHP на `0.0.0.0:${PORT:-8080}`. Миграции и загрузка данных идемпотентны,
поэтому запуск можно повторять.

### API

| Метод | Путь | Назначение |
| --- | --- | --- |
| GET | `/api/health` | проверка живости процесса (без обращения к БД) |
| GET | `/api/cities` | справочник городов |
| GET | `/api/flights?origin=MOW&destination=LED&date=YYYY-MM-DD[&passengers=N]` | поиск рейсов |
| GET | `/api/flights/{id}` | рейс по идентификатору |
| POST | `/api/bookings` | создание брони |
| GET | `/api/bookings/{code}?lastName=…` | просмотр брони по коду и фамилии |
| POST | `/api/bookings/{code}/cancel` | отмена брони |

```bash
curl -s http://localhost:8080/api/cities
curl -s "http://localhost:8080/api/flights?origin=MOW&destination=LED&date=$(date -u +%F)"

curl -s -X POST http://localhost:8080/api/bookings \
  -H 'Content-Type: application/json' \
  -d '{"flightId":"1","contact":{"email":"ivan@example.com","phone":"+7 900 000-00-00"},"passengers":[{"firstName":"Иван","lastName":"Петров","dateOfBirth":"2000-01-01","documentNumber":"1234"}]}'

curl -s "http://localhost:8080/api/bookings/ABC123?lastName=Петров"
curl -s -X POST http://localhost:8080/api/bookings/ABC123/cancel \
  -H 'Content-Type: application/json' -d '{"lastName":"Петров"}'
```

### Тесты

```bash
make test        # PHPUnit (нужна доступная база)
make contract    # пересборка contract/openapi.yaml из TypeSpec
make validate    # проверка composer.json
```

### Docker

```bash
docker build -t flight-booking .
docker run --rm -e PORT=8080 \
  -e DATABASE_URL="postgresql://user:password@host:5432/dbname" \
  -p 8080:8080 flight-booking
```

### Демо (asciinema)

[![asciicast](https://asciinema.org/a/MNhqUkiZHq5DkPdq.svg)](https://asciinema.org/a/MNhqUkiZHq5DkPdq)

---

<details>
<summary>Автоматические тесты Хекслета</summary>

Тесты запускаются на каждый коммит. За запуск отвечает файл `.github/workflows/hexlet-check.yml` — не удаляйте и не переименовывайте ни его, ни репозиторий.

</details>

## О Хекслете

[Хекслет](https://ru.hexlet.io/) — школа программирования: авторские программы обучения с практикой, поддержкой наставников и реальными проектами, которые остаются в резюме. Этот репозиторий — один из таких проектов.
