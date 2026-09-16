ALTER TABLE bookings
    ADD COLUMN pickup_latitude DECIMAL(10, 7) NULL AFTER dropoff_address,
    ADD COLUMN pickup_longitude DECIMAL(10, 7) NULL AFTER pickup_latitude,
    ADD COLUMN dropoff_latitude DECIMAL(10, 7) NULL AFTER pickup_longitude,
    ADD COLUMN dropoff_longitude DECIMAL(10, 7) NULL AFTER dropoff_latitude;
