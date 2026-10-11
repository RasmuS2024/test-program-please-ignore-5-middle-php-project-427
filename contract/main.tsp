import "@typespec/http";
import "@typespec/openapi3";

using TypeSpec.Http;

@service(#{
  title: "Flight Booking API",
})
@server("http://localhost:8080", "Local server")
namespace FlightBooking;

/** Денежная сумма. */
model Money {
  /** Целые рубли, без копеек. */
  amount: int32;
  /** Всегда RUB. */
  currency: "RUB";
}

/** Город (аэропорт) в справочнике. */
model City {
  /** IATA-подобный код: MOW, LED. */
  code: string;
  /** Название на русском: Москва. */
  name: string;
  /** Страна, например Россия. */
  country?: string;
}

/** Авиакомпания. */
model Airline {
  /** Код авиакомпании, например SU. */
  code: string;
  /** Название, например Аэрофлот. */
  name: string;
}

/** Рейс. */
model Flight {
  /** Идентификатор рейса; попадает в адрес, без пробелов и слешей. */
  id: string;
  /** Номер рейса, например SU1234. */
  flightNumber: string;
  airline: Airline;
  /** Город вылета целиком. */
  origin: City;
  /** Город прилёта целиком. */
  destination: City;
  departureAt: utcDateTime;
  arrivalAt: utcDateTime;
  /** Длительность в минутах. */
  durationMinutes: int32;
  /** Цена за одного пассажира. */
  price: Money;
  /** Свободных мест. */
  seatsAvailable: int32;
}

/** Пассажир. */
model Passenger {
  firstName: string;
  lastName: string;
  dateOfBirth: plainDate;
  documentNumber: string;
}

/** Контакт заказчика. */
model Contact {
  email: string;
  phone: string;
}

/** Статус брони. */
enum BookingStatus {
  confirmed,
  cancelled,
}

/** Бронь. */
model Booking {
  /** Код брони: ровно 6 символов из A-Z и 0-9. */
  code: string;
  status: BookingStatus;
  /** Забронированный рейс целиком. */
  flight: Flight;
  passengers: Passenger[];
  contact: Contact;
  /** Цена рейса × число пассажиров. */
  totalPrice: Money;
  /** Момент создания брони. */
  createdAt: utcDateTime;
}

/** Тело запроса на создание брони. */
model CreateBookingRequest {
  flightId: string;
  contact: Contact;
  /** Минимум один пассажир. */
  passengers: Passenger[];
}

/** Тело запроса на отмену брони. */
model CancelBookingRequest {
  lastName: string;
}

/** Ошибка API. */
model ErrorResponse {
  code: string;
  message: string;
}

/** Успешный ответ 200. */
model Ok<T> {
  @statusCode statusCode: 200;
  @body body: T;
}

/** Успешный ответ 201. */
model Created<T> {
  @statusCode statusCode: 201;
  @body body: T;
}

/** Ошибка 400. */
model BadRequest {
  @statusCode statusCode: 400;
  @body body: ErrorResponse;
}

/** Ошибка 404. */
model NotFound {
  @statusCode statusCode: 404;
  @body body: ErrorResponse;
}

/** Ответ хелсчека. */
model HealthStatus {
  status: "ok";
}

@route("/api")
interface FlightBookingApi {
  /** Хелсчек: приложение поднялось и отвечает. */
  @route("/health")
  @get
  health(): Ok<HealthStatus>;

  /** Справочник городов. */
  @route("/cities")
  @get
  listCities(): Ok<City[]>;

  /** Поиск рейсов. */
  @route("/flights")
  @get
  searchFlights(
    @query origin: string,
    @query destination: string,
    @query date: plainDate,
    @query passengers?: int32 = 1
  ): Ok<Flight[]> | BadRequest;

  /** Рейс по идентификатору. */
  @route("/flights/{id}")
  @get
  getFlight(@path id: string): Ok<Flight> | NotFound;

  /** Создать бронь. */
  @route("/bookings")
  @post
  createBooking(
    @body booking: CreateBookingRequest
  ): Created<Booking> | BadRequest;

  /** Просмотр брони по коду и фамилии. */
  @route("/bookings/{code}")
  @get
  getBooking(
    @path code: string,
    @query lastName: string
  ): Ok<Booking> | NotFound;

  /** Отмена брони. */
  @route("/bookings/{code}/cancel")
  @post
  cancelBooking(
    @path code: string,
    @body cancel: CancelBookingRequest
  ): Ok<Booking> | NotFound;
}
