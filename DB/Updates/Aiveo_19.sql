# Aiveo issue #19: new members don't have the Coop fee set.

-- Update existing members to make sure they all have the right Co-op fee
UPDATE `kvfc_members`
SET `customer_fee_percent` = 10.000
WHERE IFNULL(`customer_fee_percent`, 0.000) = 0.000;

-- Update the membership types table to make sure new members have the right Co-op fee
UPDATE `kvfc_membership_types`
SET `customer_fee_percent` = 10.000;
