INSERT INTO kvfc_ledger (transaction_group_id, source_type, source_key, target_type, target_key,
	amount, text_key, effective_datetime, posted_by, timestamp, 
    basket_id, site_id, delivery_id)
SELECT 
	'', 'internal', 5, 'member', kvfc_members.member_id,
	SUM(kvfc_ledger.amount), 'payment received', kvfc_order_cycles.delivery_date, 1, current_timestamp(),
    kvfc_ledger.basket_id, 1, kvfc_order_cycles.delivery_id
FROM kvfc_ledger
JOIN kvfc_order_cycles USING (delivery_id)
JOIN kvfc_members ON kvfc_members.member_id = kvfc_ledger.source_key
WHERE kvfc_order_cycles.is_bulk = 1
AND kvfc_ledger.source_type = 'member' AND kvfc_ledger.target_type = 'producer'
GROUP BY 
    kvfc_members.member_id,
	kvfc_order_cycles.delivery_date,
	kvfc_ledger.basket_id,
    kvfc_order_cycles.delivery_id
ORDER BY kvfc_ledger.basket_id;

