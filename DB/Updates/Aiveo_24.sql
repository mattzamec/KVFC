
-- Deletes old image-related settings from the config table
DELETE FROM kvfc_configuration WHERE constant IN ('SERVE_FILE_IMAGES', 'CREATE_IMAGE_FILES');

