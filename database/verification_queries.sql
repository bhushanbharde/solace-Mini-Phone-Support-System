USE phone_support;

-- Recent orders for Amit
SELECT * FROM orders
WHERE customer_id = 1
ORDER BY created_at DESC
LIMIT 3;

-- Call audit trail
SELECT * FROM call_logs
ORDER BY id DESC;

-- Order audit trail
SELECT * FROM order_logs
ORDER BY id DESC;

-- Voicemails
SELECT * FROM voicemail_logs
ORDER BY id DESC;

-- Support routing
SELECT * FROM support_routes
ORDER BY id DESC;
