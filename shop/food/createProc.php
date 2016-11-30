<?php
include_once 'includes/config_openfood.php';
session_start();

$procname = 'usp_bulk_product_update';

$output = 'Dropping stored procedure ' . $procname . '<br/><br/>';

$query = 'DROP PROCEDURE IF EXISTS ' . $procname . ';';

$result = mysql_query($query);

$output .= $result ? 'OK' : 'ERROR : ' . mysql_error();
$output .= '<br/><br/>Creating stored procedure ' . $procname . '<br/><br/>';        

$query = 'CREATE PROCEDURE ' . $procname . ' (
    prm_sku VARCHAR(20),
    prm_name VARCHAR(75),
    prm_description LONGTEXT,
    prm_category VARCHAR(50),
    prm_unit_price DECIMAL(9, 3),
    prm_pricing_unit VARCHAR(50),
    prm_modified DATETIME,
    prm_is_unlisted BOOLEAN
)
BEGIN
    DECLARE var_category_id INT(11);
    DECLARE var_subcategory_id INT(11);
    DECLARE var_product_id INT(11);
    DECLARE var_version MEDIUMINT(9);
    
    DECLARE var_om_product_id VARCHAR(10);
    DECLARE var_om_variant_id VARCHAR(10);

    DECLARE var_check_product_insert BOOLEAN DEFAULT 0;   -- Used to prevent concurrency issues during insert

    -- These variables are used to compare parameters to existing entries
    -- in order to determine if we need to create a new version of the product
    DECLARE var_existing_pvid INT(11);
    DECLARE var_existing_name VARCHAR(75);
    DECLARE var_existing_description LONGTEXT;
    DECLARE var_existing_subcategory_id INT(11);
    DECLARE var_existing_unit_price DECIMAL(9, 3);
    DECLARE var_existing_pricing_unit VARCHAR(50);
    DECLARE var_existing_version MEDIUMINT(9);
    DECLARE var_existing_auth_type VARCHAR(20);

    -- First let\'s deal with the product category. 
    -- Let\'s get the parent category ID. 
    SELECT category_id INTO var_category_id
    FROM kvfc_categories
    WHERE category_name = \'Bulk Items\'
    LIMIT 1;
    
    -- The Bulk Items category really should exist. If it doesn\'t for some unholy reason, let\'s create it.
    IF var_category_id IS NULL
    THEN  BEGIN
        INSERT INTO `kvfc_categories` (
            `category_name`, `category_desc`, `taxable`, `parent_id`, `sort_order`, `is_bulk`
        ) VALUES (
            \'Bulk Items\', \'Bulk items from KVFC wholesale partners.\', 0, 0, (SELECT MAX(sort_order) + 1 FROM kvfc_categories), 1
        );

        SET var_category_id = LAST_INSERT_ID();
    END;
    END IF;
    
    -- Let\'s see if the category already exists
    SELECT subcategory_id INTO var_subcategory_id
    FROM kvfc_subcategories
    WHERE category_id = var_category_id
    AND subcategory_name = prm_category
    LIMIT 1;
    
    -- If subcategory doesn\'t exist, let\'s create it.
    -- Make sure to set the subcategory_fee_percent to 0.000 since the bulk products will already have their values marked up.
    IF var_subcategory_id IS NULL
    THEN BEGIN
        INSERT INTO `kvfc_subcategories` (
            `subcategory_name`, `category_id`, `taxable`, `subcategory_fee_percent`
        ) VALUES (
            prm_category, var_category_id, 0, 0.000
        );

        SET var_subcategory_id = LAST_INSERT_ID();
    END;
    END IF;

    -- Now let\'s see if the SKU already exists. Try to retrieve the latest version, regardless of whether it\'s confirmed.
    SELECT
        pvid,
        product_name,
        product_description,
        subcategory_id,
        unit_price,
        pricing_unit,
        listing_auth_type,
        product_version
    INTO
        var_existing_pvid,
	var_existing_name,
	var_existing_description,
	var_existing_subcategory_id,
	var_existing_unit_price,
	var_existing_pricing_unit,
	var_existing_auth_type,
	var_existing_version
    FROM kvfc_products
    WHERE bulk_sku = prm_sku
    ORDER BY product_version DESC
    LIMIT 1;

    IF var_existing_pvid IS NOT NULL
    AND var_existing_name = prm_name
    AND var_existing_description = prm_description
    AND var_existing_subcategory_id = var_subcategory_id
    AND var_existing_unit_price = prm_unit_price
    AND var_existing_pricing_unit = prm_pricing_unit
    AND CASE var_existing_auth_type WHEN \'unlisted\' THEN 1 ELSE 0 END = prm_is_unlisted
    THEN BEGIN
        -- If all relevant values are the same, we don\'t need to update anything, but let\'s set modified date 
        -- to indicate that the product was reviewed.
        UPDATE kvfc_products
	SET modified = prm_modified
	WHERE pvid = var_existing_pvid;
    END;
    ELSE BEGIN   -- Product does not exist or something has changed
        IF var_existing_pvid IS NOT NULL    -- Product exists, so something has changed and we\'ll need to add a new version.
        THEN BEGIN
            -- Mark the existing version as unconfirmed.
            UPDATE kvfc_products
            SET modified = prm_modified,
                confirmed = 0
            WHERE pvid = var_existing_pvid;
            
            -- Retrieve the product ID
            SELECT product_id INTO var_product_id
            FROM kvfc_products
            WHERE pvid = var_existing_pvid;
        END;
        ELSE BEGIN   -- Product doesn\'t yet exist
            -- The OM SKU used to be a unique identifier, but has been replaced by a combination of OM poduct ID and 
            -- OM variant ID, which distinguishes different size options of the same product. Let\'s try to get the 
            -- OM product ID from the SKU parameter prm_sku, which should contain {om_product_id}_{om_variant_id}
            SET var_om_product_id = SUBSTRING_INDEX(prm_sku, \'_\', 1);

            -- Get KVFC product_id matching or starting with the OM product ID
            SELECT DISTINCT product_id INTO var_product_id
            FROM kvfc_products
            WHERE bulk_sku = var_om_product_id
            OR LEFT(bulk_sku, CHAR_LENGTH(var_om_product_id) + 1) = CONCAT(var_om_product_id, \'_\')
            LIMIT 1;
            
            -- If there is no KVFC product for this OM product ID, get the next available product ID
            IF var_product_id IS NULL
            THEN
                SELECT MAX(product_id) + 1 
		INTO var_product_id
		FROM kvfc_products;
            END IF;
        END;
        END IF;

        -- Get the next available product version
        SELECT IFNULL(MAX(product_version) + 1, 1) 
        INTO var_version
	FROM kvfc_products 
	WHERE product_id = var_product_id;

	WHILE var_check_product_insert != 1 DO
            START TRANSACTION;

            INSERT INTO `kvfc_products`(
		`product_id`, `product_version`, `producer_id`, `product_name`, `account_number`,
		`inventory_pull`, `inventory_id`, `product_description`, `subcategory_id`, `future_delivery`,
		`future_delivery_type`, `production_type_id`, `unit_price`, `pricing_unit`, `ordering_unit`,
		`random_weight`, `meat_weight_type`, `minimum_weight`, `maximum_weight`, `extra_charge`,
		`product_fee_percent`, `image_id`, `listing_auth_type`, `taxable`, `confirmed`,
		`retail_staple`, `staple_type`, `created`, `modified`, `tangible`,
		`sticky`, `hide_from_invoice`, `storage_id`, `bulk_sku`
            ) VALUES (
                var_product_id, var_version, (SELECT producer_id FROM kvfc_producers WHERE is_bulk = 1 LIMIT 1), prm_name, \'\',
		0, 0, prm_description, var_subcategory_id, 0,
		\'\', IFNULL((SELECT production_type_id FROM kvfc_production_types WHERE prodtype = \'Certified Organic\'), 0), prm_unit_price, prm_pricing_unit, prm_pricing_unit,
                0, NULL, 0.00, 0.00, 0.00,
		0.00, 0, CASE WHEN prm_is_unlisted = 1 THEN \'unlisted\' ELSE \'member\' END, 0, 1,
                0, \'\', prm_modified, prm_modified, 0,
		0, 0, IFNULL((SELECT storage_id FROM kvfc_product_storage_types WHERE storage_code = \'NON\'), 0), prm_sku);

            -- If we\'re inserting the first version for a new product, let\'s make sure there is only a single record for this product ID.
            -- If there isn\'t, we ran into a concurrency issue where another new product was created at the same time, since product_id is just
            -- an integer field but needs to be unique for a product. This is highly unlikely, but needs to be prevented in any case.
            IF var_version = 1
            THEN
                SELECT CASE WHEN COUNT(1) = 1 THEN 1 ELSE 0 END 
		INTO var_check_product_insert
		FROM kvfc_products
		WHERE product_id = var_product_id;
            ELSE
                SET var_check_product_insert = 1;   -- No need to verify product insert if we\'re just inserting a higher version for an existing product
            END IF;
            
            -- If we\'re inserting an updated version there shouldn\'t be a product ID conflict so we can commit
            IF var_check_product_insert = 1
            THEN 
                COMMIT;
            ELSE
            BEGIN
                ROLLBACK;
                SET var_product_id = var_product_id + 1;
            END;
            END IF;
        END WHILE;
    END;
    END IF;
END';

$result = mysql_query($query);
$output .= $result ? 'OK' : 'ERROR : ' . mysql_error();

include("template_header.php");
echo '
  <!-- CONTENT BEGINS HERE -->
'.$output.'
  <!-- CONTENT ENDS HERE -->';
include("template_footer.php");

    