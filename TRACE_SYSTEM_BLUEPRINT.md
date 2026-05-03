# TRACE System Blueprint

## Proposed Title
TRACE: A Mobile Student Transport Management System with Real-Time GPS Tracking and Messaging Module

## Project Overview
TRACE is a transport management platform for student service rides. It has:

- A mobile application for `driver`, `parent`, and `student`
- A web-based admin panel for `admin`
- A PHP backend with REST API
- Real-time GPS location sharing for active rides
- Messaging and notifications between users

The system focuses on safety, monitoring, booking, trip coordination, and student accountability.

## User Types

### 1. Admin
- Manages all users, rides, routes, vehicles, payments, and reports
- Verifies driver and parent registrations
- Views live ride monitoring and system logs
- Suspends or approves accounts
- Reviews incidents, complaints, and alerts

### 2. Driver
- Registers and submits complete profile
- Uploads driver license, vehicle details, and supporting documents
- Gets approved by admin before accepting bookings
- Goes online/offline for ride availability
- Accepts assigned rides
- Shares real-time GPS during active trips
- Updates trip status: `en route`, `arrived`, `picked up`, `dropped off`, `completed`
- Chats with parent/admin

### 3. Parent
- Registers own account
- Creates and manages linked student accounts
- Books a service ride for one or more children
- Tracks driver and student trip status in real time
- Receives alerts and notifications
- Views trip history, fares, and payment records
- Chats with driver/admin

### 4. Student
- Account is created by the parent
- Has unique `LRN`
- Can view assigned driver, route, pickup time, and live trip location
- Can send limited status messages or emergency alerts
- Cannot manage billing or create other student accounts

## Core Functional Modules

### Authentication and Verification
- Role-based registration and login
- OTP or email verification
- Admin approval workflow for driver and parent accounts
- Password reset
- JWT or token-based API authentication

### Driver Onboarding
- Personal information
- Contact details
- License upload
- Automatic extraction of basic license details if available
- Vehicle registration details
- Vehicle photo upload
- Admin verification status

Recommended extracted fields:
- Full name
- License number
- Expiry date
- Address
- Date of birth

Note: In PHP, license extraction can start as manual encoding plus image upload. OCR can be added later using Google Vision, Tesseract, or another OCR service.

### Parent and Student Management
- Parent profile creation
- Student account creation by parent
- Unique `LRN` validation
- Student profile:
  - Full name
  - LRN
  - School
  - Grade/year level
  - Photo
  - Emergency contact
  - Pickup address
  - Drop-off address
  - Medical notes

### Booking and Ride Management
- Parent books a ride
- Pickup and destination selection
- Assign preferred student
- Schedule one-time or recurring trips
- Driver assignment
- Booking status flow:
  - `pending`
  - `approved`
  - `assigned`
  - `driver_arriving`
  - `picked_up`
  - `in_transit`
  - `dropped_off`
  - `completed`
  - `cancelled`

### Real-Time GPS Tracking
- Driver app sends GPS coordinates every few seconds during active rides
- Parent and student apps view live vehicle position on a map
- Backend stores latest coordinates and trip timeline
- ETA is recalculated from mapping service data

For “like Grab or Foodpanda” tracking:
- Driver mobile app should continuously post location while trip is active
- Parent/student app should auto-refresh map markers
- Show:
  - Driver location
  - Student pickup/drop-off status
  - Estimated arrival time
  - Route polyline if map provider supports it

Important practical note:
- Exact real-time map tracing usually requires a map provider such as Google Maps, Mapbox, or OpenStreetMap-based services.
- PHP alone handles storage/API; live map rendering happens in the React Native app.

### Messaging and Notifications
- Parent to driver chat
- Driver to parent chat
- Admin announcements
- Push notifications for:
  - booking accepted
  - driver assigned
  - driver near pickup point
  - student picked up
  - student dropped off
  - emergency alert

### Admin Dashboard
- Login-protected web panel
- Bootstrap-based responsive interface
- Dashboard cards:
  - total users
  - active rides
  - completed rides
  - pending verifications
  - incidents/reports
- User management
- Driver approval
- Parent/student monitoring
- Ride logs and trip playback
- Audit trail and activity logs
- Reports export

## Recommended Technology Stack

### Mobile App
- React Native
- React Navigation
- Axios
- React Native Maps
- Firebase Cloud Messaging or OneSignal for notifications

### Backend
- PHP 8+
- MySQL / MariaDB
- REST API
- JWT authentication
- File upload handling for IDs and documents

### Admin Web
- PHP
- Bootstrap 5
- JavaScript / AJAX
- Chart.js for dashboards

### Optional Real-Time Layer
If you want stronger live updates than simple polling:
- Firebase Realtime Database or Firestore for location streaming
- Or Socket-based Node.js service later

For a capstone, a practical setup is:
- `PHP + MySQL` for main backend
- `React Native` for apps
- `Firebase Cloud Messaging` for notifications
- `Google Maps` or `Mapbox` for maps
- Optional polling every 5 to 10 seconds for live tracking

## High-Level Architecture

1. Parent creates account
2. Parent adds student with unique LRN
3. Driver registers and uploads credentials
4. Admin verifies driver and parent
5. Parent books a ride
6. Admin or system assigns driver
7. Driver starts trip and sends live GPS
8. Parent and student monitor trip in real time
9. System sends pickup and drop-off notifications
10. Admin can review all ride logs and user activity

## Suggested Database Tables

### users
- id
- role (`admin`, `driver`, `parent`, `student`)
- first_name
- middle_name
- last_name
- email
- mobile_number
- password_hash
- profile_photo
- status
- is_verified
- created_at
- updated_at

### parents
- id
- user_id
- address
- valid_id_path
- emergency_contact_name
- emergency_contact_number

### students
- id
- user_id
- parent_id
- lrn
- school_name
- grade_level
- pickup_address
- dropoff_address
- medical_notes
- status

### drivers
- id
- user_id
- license_number
- license_expiry
- license_photo_path
- vehicle_type
- vehicle_plate_number
- vehicle_model
- vehicle_color
- vehicle_photo_path
- approval_status
- is_online

### vehicles
- id
- driver_id
- plate_number
- model
- color
- capacity
- registration_path
- status

### bookings
- id
- parent_id
- student_id
- pickup_address
- dropoff_address
- scheduled_date
- scheduled_time
- trip_type
- notes
- booking_status
- assigned_driver_id
- created_at

### rides
- id
- booking_id
- driver_id
- ride_status
- started_at
- arrived_pickup_at
- picked_up_at
- dropped_off_at
- completed_at
- cancelled_at

### ride_locations
- id
- ride_id
- driver_id
- latitude
- longitude
- speed
- heading
- recorded_at

### messages
- id
- sender_user_id
- receiver_user_id
- ride_id
- message_text
- message_type
- is_read
- created_at

### notifications
- id
- user_id
- title
- body
- type
- reference_id
- is_read
- created_at

### admin_logs
- id
- admin_user_id
- action
- table_name
- record_id
- description
- created_at

## Recommended API Modules

### Auth API
- `POST /api/register/parent`
- `POST /api/register/driver`
- `POST /api/login`
- `POST /api/logout`
- `POST /api/forgot-password`

### Parent API
- `POST /api/parents/students`
- `GET /api/parents/students`
- `POST /api/bookings`
- `GET /api/bookings`
- `GET /api/rides/{id}/track`

### Driver API
- `GET /api/driver/bookings`
- `POST /api/driver/rides/{id}/accept`
- `POST /api/driver/rides/{id}/status`
- `POST /api/driver/rides/{id}/location`
- `GET /api/driver/profile`

### Student API
- `GET /api/student/rides/current`
- `GET /api/student/notifications`

### Admin API / Web Actions
- Approve driver
- Approve parent
- Manage users
- View ride reports
- View live rides
- View logs

## App Screens

### Driver App
- Splash
- Register
- Login
- Upload license and vehicle details
- Verification status
- Dashboard
- Incoming bookings
- Active trip map
- Ride history
- Chat
- Profile

### Parent App
- Splash
- Register
- Login
- Dashboard
- Add student
- Student list
- Book ride
- Active trip tracking
- Notifications
- Chat
- Payment/history
- Profile

### Student App
- Login
- Dashboard
- Current ride
- Live map
- Notifications
- Emergency alert
- Profile

### Admin Web
- Login
- Dashboard
- User management
- Driver verification
- Parent/student records
- Booking management
- Ride monitoring
- Reports
- Logs

## Booking Rules
- Parent must be verified before booking
- Student LRN must be unique
- Driver must be approved before assignment
- Driver can only have one active ride at a time unless your business rule allows pooled trips
- Completed rides become read-only except for admin corrections

## Security and Validation
- Hash passwords using `password_hash()`
- Validate file uploads and limit file types
- Use role-based access control on all endpoints
- Validate that `LRN` is unique
- Keep location posting limited to active rides only
- Record audit logs for admin actions

## Best Approach for Real-Time Tracking

### Minimum Viable Capstone Version
- Driver app gets GPS from phone
- Driver app sends location to PHP API every `5 to 10 seconds`
- Backend stores latest location
- Parent and student apps poll current ride location every `5 to 10 seconds`
- Map updates marker and ETA

This is simpler and easier to defend during capstone.

### Advanced Version
- Use Firebase for real-time location streaming
- Keep PHP for user management, bookings, and records
- Parent/student app subscribes to ride location changes instantly

This feels more like Grab, but adds more setup complexity.

## Recommended Capstone Scope

### Must Have
- 4 user roles
- Driver registration with license upload
- Parent registration
- Parent creates student account
- Student LRN uniqueness
- Booking flow
- Driver assignment
- Ride status updates
- Live GPS tracking
- Messaging
- Admin dashboard and logs

### Good to Add
- Fare computation
- Recurring booking
- SOS button
- Attendance or pickup confirmation QR
- Trip replay in admin

### Better as Future Enhancement
- OCR for automatic license field extraction
- In-app payment gateway
- AI route optimization
- Face recognition for pickup confirmation

## Revised Objective Alignment

Your current capstone idea can be refined into these technical objectives:

1. Design and develop a mobile application for drivers, parents, and students with role-based access.
2. Design and develop a web-based admin panel for centralized management of users, rides, and logs.
3. Implement a real-time GPS tracking module for monitoring active student transport rides.
4. Implement a booking and ride assignment module for parents and drivers.
5. Implement a messaging and notification module for communication among system users.
6. Evaluate the system using ISO 25010 quality characteristics.

## Recommendation
For development, the most realistic build is:

- `React Native` for one shared mobile app codebase with role-based screens
- `PHP + MySQL` as the main backend
- `Bootstrap + PHP` for the admin dashboard
- `Google Maps` or `Mapbox` for maps
- `Firebase Cloud Messaging` for push notifications

If you want, the next step can be either:

1. Create the full database schema in SQL
2. Create the API endpoint list with request/response samples
3. Scaffold the PHP backend folder structure
4. Scaffold the React Native app structure
