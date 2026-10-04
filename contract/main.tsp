import "@typespec/http";

using TypeSpec.Http;

@service(#{ title: "Flight Booking API" })
namespace FlightBookingApi;

model City {
  code: string;
  name: string;
  country?: string;
}

@route("/api/cities")
interface Cities {
  @get list(): City[];
}
