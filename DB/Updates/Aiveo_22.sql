-- Add is_bulk boolean to kvfc_order_cycles table
SET @kvfc_order_cycles_add_is_bulk = (SELECT IF (EXISTS(
    (SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'kvfc_order_cycles'
        AND table_schema = DATABASE()
        AND column_name = 'is_bulk'
    )),
    "SELECT 'kvfc_order_cycles already contains column is_bulk' AS Confirm",
    "ALTER TABLE `kvfc_order_cycles`
ADD COLUMN `is_bulk` BOOLEAN 
NOT NULL 
DEFAULT 0"
));

PREPARE stmt FROM @kvfc_order_cycles_add_is_bulk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The kvfc_members.auth_type column on my dev database was an enum type, not set. Not sure why.
-- I needed to run these on my dev DB to make the column a set. This shouldn't be required on the production DB
#ALTER TABLE `kvfc_members`
#DROP COLUMN `auth_type`;

#ALTER TABLE `kvfc_members`
#ADD COLUMN `auth_type`
#SET ('member', 'producer', 'institution', 'orderex', 'member_admin', 'producer_admin', 'route_admin', 'cashier', 'site_admin', 'board')
#NOT NULL 
#DEFAULT 'member'
#AFTER `password`;

-- Add a bulk_admin auth type to members table
SET @kvfc_members_auth_type_add_bulk_admin = (SELECT IF (
	(SELECT COLUMN_TYPE
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE table_name = 'kvfc_members'
	AND table_schema = DATABASE()
	AND column_name = 'auth_type') LIKE '%bulk_admin%',
    "SELECT 'kvfc_members.auth_type already contains bulk_admin' AS Confirm",
    "ALTER TABLE `kvfc_members` 
MODIFY COLUMN `auth_type` 
SET('member', 'producer', 'institution', 'orderex', 'member_admin', 'producer_admin', 'route_admin', 'cashier', 'site_admin', 'board', 'bulk_admin');"
));

PREPARE stmt FROM @kvfc_members_auth_type_add_bulk_admin;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Assign me, Kristy, Jillian and Roly to be bulk admins
UPDATE `kvfc_members`
SET `auth_type` = CONCAT(`auth_type`, ',bulk_admin')
WHERE `username` LIKE '%zamec%' or username in ('rolyrussell', 'Jmom')
AND `auth_type` NOT LIKE '%bulk_admin%';

-- Add is_bulk boolean to kvfc_producers table
SET @kvfc_producers_add_is_bulk = (SELECT IF (EXISTS(
    (SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'kvfc_producers'
        AND table_schema = DATABASE()
        AND column_name = 'is_bulk'
    )),
    "SELECT 'kvfc_producers already contains column is_bulk' AS Confirm",
    "ALTER TABLE `kvfc_producers`
ADD COLUMN `is_bulk` BOOLEAN 
NOT NULL 
DEFAULT 0"
));

PREPARE stmt FROM @kvfc_producers_add_is_bulk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Increase length of kvfc_products.pricing_unit from 20 to 50 chars to accommodate longer Organic Matters values
SET @kvfc_products_modify_pricing_unit = (SELECT IF (EXISTS(
    (SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'kvfc_products'
        AND table_schema = DATABASE()
        AND column_name = 'pricing_unit'
        AND column_type = 'varchar(50)'
	)),
    "SELECT 'kvfc_products.pricing_unit is already 50 characters long' AS Confirm",
    "ALTER TABLE `kvfc_products`
MODIFY COLUMN `pricing_unit` VARCHAR(50)
NOT NULL 
DEFAULT ''"
));

PREPARE stmt FROM @kvfc_products_modify_pricing_unit;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Increase length of kvfc_products.ordering_unit from 20 to 50 chars to accommodate longer Organic Matters values
SET @kvfc_products_modify_ordering_unit = (SELECT IF (EXISTS(
    (SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'kvfc_products'
        AND table_schema = DATABASE()
        AND column_name = 'ordering_unit'
        AND column_type = 'varchar(50)'
	)),
    "SELECT 'kvfc_products.ordering_unit is already 50 characters long' AS Confirm",
    "ALTER TABLE `kvfc_products`
MODIFY COLUMN `ordering_unit` VARCHAR(50)
NOT NULL 
DEFAULT ''"
));

PREPARE stmt FROM @kvfc_products_modify_ordering_unit;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add bulk_sku varchar to kvfc_products table
SET @kvfc_products_add_bulk_sku = (SELECT IF (EXISTS(
    (SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'kvfc_products'
        AND table_schema = DATABASE()
        AND column_name = 'bulk_sku'
    )),
    "SELECT 'kvfc_products already contains column bulk_sku' AS Confirm",
    "ALTER TABLE `kvfc_products`
ADD COLUMN `bulk_sku` VARCHAR(20) NULL"
));

PREPARE stmt FROM @kvfc_products_add_bulk_sku;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create a member for Organic Matters
SET @kvfc_members_insert_omfoods = (SELECT IF (EXISTS(
    (SELECT 1
        FROM kvfc_members
        WHERE username = 'omfoods'
    )),
    "SELECT 'A member for Organic Matters already exists in the kvfc_members table.' AS Confirm",
    "INSERT INTO `kvfc_members` (	
		`pending`, `username`, `password`, `auth_type`, `business_name`,
		`preferred_name`, `last_name`, `first_name`, `last_name_2`, `first_name_2`,
		`no_postal_mail`, `address_line1`, `address_line2`, `city`, `state`,
		`zip`, `county`, `work_address_line1`, `work_address_line2`, `work_city`,
		`work_state`, `work_zip`, `email_address`, `email_address_2`, `home_phone`,
		`work_phone`, `mobile_phone`, `fax`, `toll_free`, `home_page`,
		`membership_type_id`, `customer_fee_percent`, `membership_date`, `last_renewal_date`, `membership_discontinued`,
		`mem_taxexempt`, `mem_delch_discount`, `how_heard_id`, `notes`)
	VALUES (
		0, 'omfoods', '3b45f03f6e5b91d72632f36aebd363aa' /* 'omfoods' */, 'member,producer', 'Organic Matters Foods',
		'Organic Matters', 'Matters', 'Organic', '', '',
		0, 'P.O. Box 1221', '', 'Nelson', 'BC',
		'V1L 6H3', '', '3505 Highway 6', '', 'Nelson',
		'BC', 'V1L 6Z4', 'matt.zamec@gmail.com', '', '1-250-505-2272',
		'', '', '250-505-2274', '', 'http://www.omfoods.com',
		2, 10.000, '2016-07-17', '2016-07-17',  0,
		0, 0, 0, 'Organic Matters is our bulk food producer from Nelson')"
));

PREPARE stmt FROM @kvfc_members_insert_omfoods;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create a bulk producer for Organic Matters/KVFC Bulk
SET @kvfc_producers_insert_omfoods = (SELECT IF (EXISTS(
    (SELECT 1
        FROM kvfc_producers
        WHERE producer_link = 'OmFoods'
    )),
    "SELECT 'A producer for Organic Matters already exists in the kvfc_producers table.' AS Confirm",
    "INSERT INTO `kvfc_producers` (
		`list_order`, `producer_link`, `pending`, `member_id`, `business_name`, 
		`payee`, `producer_fee_percent`, `unlisted_producer`, `producttypes`, `about`, 
		`ingredients`, `general_practices`, `highlights`, `additional`, `liability_statement`, 
		`pub_address`, `pub_email`, `pub_email2`, `pub_phoneh`, `pub_phonew`, 
		`pub_phonec`, `pub_phonet`, `pub_fax`, `pub_web`, `is_bulk`)
	VALUES (
		0, 'OmFoods', 0, (select member_id from kvfc_members where username = 'omfoods'), 'KVFC Bulk',
		'', 0, 0, 'Bulk Organic Foods', '',
		'', '', '', '', '',
		1, 1, 1, 1, 1,
		1, 1, 1, 1, 1)"
));

PREPARE stmt FROM @kvfc_producers_insert_omfoods;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add is_bulk boolean to kvfc_categories table
SET @kvfc_categories_add_is_bulk = (SELECT IF (EXISTS(
    (SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = 'kvfc_categories'
        AND table_schema = DATABASE()
        AND column_name = 'is_bulk'
    )),
    "SELECT 'kvfc_categories already contains column is_bulk' AS Confirm",
    "ALTER TABLE `kvfc_categories`
ADD COLUMN `is_bulk` BOOLEAN 
NOT NULL 
DEFAULT 0"
));

PREPARE stmt FROM @kvfc_categories_add_is_bulk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create a Bulk Items category
SET @kvfc_categories_insert_bulk = (SELECT IF (EXISTS(
    (SELECT 1
        FROM kvfc_categories
        WHERE category_name = 'Bulk Items'
    )),
    "SELECT 'A category for bulk Items already exists in the kvfc_categories table.' AS Confirm",
    "INSERT INTO `kvfc_categories` (
		`category_name`, `category_desc`, `taxable`, `parent_id`, `sort_order`, `is_bulk`)
	SELECT 'Bulk Items', 'Bulk items from KVFC wholesale partners.', 0, 0, MAX(sort_order) + 1, 1 
    FROM kvfc_categories"
));

PREPARE stmt FROM @kvfc_categories_insert_bulk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create a stored procedure that will handle a bulk product insert or update
DELIMITER $$

DROP PROCEDURE IF EXISTS usp_bulk_product_update $$
CREATE PROCEDURE usp_bulk_product_update (
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

	-- First let's deal with the product category. 
    -- Let's get the parent category ID. 
    SELECT category_id INTO var_category_id
    FROM kvfc_categories
    WHERE category_name = 'Bulk Items'
    LIMIT 1;
    
    -- The Bulk Items category really should exist. If it doesn't for some unholy reason, let's create it.
    IF var_category_id IS NULL
    THEN  BEGIN
		INSERT INTO `kvfc_categories` (
			`category_name`, `category_desc`, `taxable`, `parent_id`, `sort_order`
		) VALUES (
			'Bulk Items', 'Bulk items from KVFC wholesale partners.', 0, 0, (SELECT MAX(sort_order) + 1 FROM kvfc_categories)
		);

		SET var_category_id = LAST_INSERT_ID();
	END;
	END IF;
    
    -- Let's see if the category already exists
    SELECT subcategory_id INTO var_subcategory_id
    FROM kvfc_subcategories
    WHERE category_id = var_category_id
    AND subcategory_name = prm_category
    LIMIT 1;
    
    -- If subcategory doesn't exist, let's create it
    IF var_subcategory_id IS NULL
    THEN BEGIN
		INSERT INTO `kvfc_subcategories` (
			`subcategory_name`, `category_id`, `taxable`, `subcategory_fee_percent`
		) VALUES (
			prm_category, var_category_id, 0, 23.000
		);

		SET var_subcategory_id = LAST_INSERT_ID();
	END;
	END IF;

	-- Now let's see if the SKU already exists. Try to retrieve the latest version, regardless of whether it's confirmed.
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
		AND CASE var_existing_auth_type WHEN 'unlisted' THEN 1 ELSE 0 END = prm_is_unlisted
	THEN BEGIN
        -- If all relevant values are the same, we don't need to update anything, but let's set modified date 
        -- to indicate that the product was reviewed.
		UPDATE kvfc_products
		SET modified = prm_modified
		WHERE pvid = var_existing_pvid;
    END;
    ELSE BEGIN   -- Product does not exist or something has changed
		IF var_existing_pvid IS NOT NULL    -- Product exists, so something has changed and we'll need to add a new version.
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
        ELSE BEGIN   -- Product doesn't yet exist
			-- The OM SKU used to be a unique identifier, but has been replaced by a combination of OM poduct ID and 
            -- OM variant ID, which distinguishes different size options of the same product. Let's try to get the 
            -- OM product ID from the SKU parameter prm_sku, which should contain {om_product_id}_{om_variant_id}
			SET var_om_product_id = SUBSTRING_INDEX(prm_sku, '_', 1);

            -- Get KVFC product_id matching or starting with the OM product ID
            SELECT DISTINCT product_id INTO var_product_id
			FROM kvfc_products
			WHERE bulk_sku = var_om_product_id
            OR LEFT(bulk_sku, CHAR_LENGTH(var_om_product_id) + 1) = CONCAT(var_om_product_id, '_')
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
				var_product_id, var_version, (SELECT producer_id FROM kvfc_producers WHERE is_bulk = 1 LIMIT 1), prm_name, '',
				0, 0, prm_description, var_subcategory_id, 0,
				'', IFNULL((SELECT production_type_id FROM kvfc_production_types WHERE prodtype = 'Certified Organic'), 0), prm_unit_price, prm_pricing_unit, prm_pricing_unit,
				0, NULL, 0.00, 0.00, 0.00,
				0.00, 0, CASE WHEN prm_is_unlisted = 1 THEN 'unlisted' ELSE 'member' END, 0, 1,
				0, '', prm_modified, prm_modified, 0,
				0, 0, IFNULL((SELECT storage_id FROM kvfc_product_storage_types WHERE storage_code = 'NON'), 0), prm_sku);

			-- If we're inserting the first version for a new product, let's make sure there is only a single record for this product ID.
            -- If there isn't, we ran into a concurrency issue where another new product was created at the same time, since product_id is just
            -- an integer field but needs to be unique for a product. This is highly unlikely, but needs to be prevented in any case.
			IF var_version = 1
            THEN
				SELECT CASE WHEN COUNT(1) = 1 THEN 1 ELSE 0 END 
				INTO var_check_product_insert
				FROM kvfc_products
				WHERE product_id = var_product_id;
			ELSE
				SET var_check_product_insert = 1;   -- No need to verify product insert if we're just inserting a higher version for an existing product
			END IF;
            
            -- If we're inserting an updated version there shouldn't be a product ID conflict so we can commit
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
END $$

DELIMITER ;

