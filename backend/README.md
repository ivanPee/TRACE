# TRACE Backend

## Purpose
This folder contains the PHP REST API for:

- authentication
- parent/student management
- transport creation
- ride status updates
- GPS location updates
- ride tracking

## Entry Point
- `backend/index.php`

## Sample URLs
- `POST /boi/backend/api/register/parent`
- `POST /boi/backend/api/register/driver`
- `POST /boi/backend/api/login`
- `POST /boi/backend/api/parents/students`
- `POST /boi/backend/api/bookings`
- `POST /boi/backend/api/driver/rides/1/status`
- `POST /boi/backend/api/driver/rides/1/location`
- `GET /boi/backend/api/rides/1/track`

## Maintenance Notes
- API endpoints must always return JSON, including server errors. Mobile clients parse every API response as JSON.
- Schema guards that replace an index must add the replacement index before dropping an old index used by a foreign key.
- Driver transport feeds are current-day only. Do not expose past or future transport routes on the driver dashboard/transport board.
- Driver route claims must check the transport time for same-day conflicts before assigning the job.
- Current-day carpool claims may include other pending transport routes with the same scheduled time and drop-off location only after driver confirmation.
- Ride tracking responses can include `carpoolStops`; mobile maps use those stops to draw multiple child pickups before the shared drop-off.
- New monthly plans store a morning pickup time and a return-home time; transport generation creates both to-school and return-home daily routes.
- Parent monthly plan management updates child route locations, plan times, remaining daily bookings, driver feeds, and tracking context from the same saved plan data.

## Next Backend Tasks
- connect controllers to MySQL using PDO
- add JWT authentication middleware
- add validation classes
- implement file upload for license and IDs
- add notification and messaging endpoints

