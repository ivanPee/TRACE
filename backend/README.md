# TRACE Backend

## Purpose
This folder contains the PHP REST API for:

- authentication
- parent/student management
- booking creation
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

## Next Backend Tasks
- connect controllers to MySQL using PDO
- add JWT authentication middleware
- add validation classes
- implement file upload for license and IDs
- add notification and messaging endpoints

