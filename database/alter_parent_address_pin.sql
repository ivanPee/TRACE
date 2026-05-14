ALTER TABLE parents
    ADD COLUMN address_latitude DECIMAL(10, 7) NULL AFTER address,
    ADD COLUMN address_longitude DECIMAL(10, 7) NULL AFTER address_latitude;
