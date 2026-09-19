INSERT INTO settings (setting_key, setting_value) VALUES ('site_name', 'Sri Lanka Expert')
ON DUPLICATE KEY UPDATE setting_value = IF(setting_value IN ('', 'Ceylon Travel Atelier'), VALUES(setting_value), setting_value);

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('brand_name', 'Sri Lanka Expert');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('brand_byline', 'by Raonex GmbH');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_name', 'Raonex GmbH');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_address', 'August-Bebel-Strasse 26c');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_postcode_city', '97297 Waldbüttelbrunn');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_phone', '+49 931 80472297');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_email', 'info@raonex.de');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('company_web', 'https://www.srilankaexpert.de');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('social_instagram', '');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('social_facebook', '');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('social_tiktok', '');
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('social_youtube', '');
