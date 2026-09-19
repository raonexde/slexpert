ALTER TABLE destinations ADD COLUMN latitude DECIMAL(10,7) NULL AFTER image_path;
ALTER TABLE destinations ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude;
ALTER TABLE destinations ADD COLUMN google_place_id VARCHAR(255) NOT NULL DEFAULT '' AFTER longitude;
ALTER TABLE tour_requests ADD COLUMN route_distance_km DECIMAL(10,1) NOT NULL DEFAULT 0 AFTER estimate;
ALTER TABLE tour_requests ADD COLUMN route_duration_minutes INT UNSIGNED NOT NULL DEFAULT 0 AFTER route_distance_km;

UPDATE destinations SET latitude = 7.2083000, longitude = 79.8358000 WHERE slug = 'negombo' AND latitude IS NULL;
UPDATE destinations SET latitude = 7.9570000, longitude = 80.7603000 WHERE slug = 'sigiriya' AND latitude IS NULL;
UPDATE destinations SET latitude = 7.2906000, longitude = 80.6337000 WHERE slug = 'kandy' AND latitude IS NULL;
UPDATE destinations SET latitude = 6.8667000, longitude = 81.0466000 WHERE slug = 'ella' AND latitude IS NULL;
UPDATE destinations SET latitude = 6.3725000, longitude = 81.5185000 WHERE slug = 'yala' AND latitude IS NULL;
UPDATE destinations SET latitude = 6.0329000, longitude = 80.2168000 WHERE slug = 'galle' AND latitude IS NULL;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('google_maps_api_key', '');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('google_maps_map_id', 'DEMO_MAP_ID');
