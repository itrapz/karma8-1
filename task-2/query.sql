WITH payment_data AS (
    SELECT
        o.user_id,
        COUNT(p.id) AS total_payments,
        SUM(CASE WHEN p.status <> 'success' THEN 1 ELSE 0 END) AS failed_payments
    FROM payments p JOIN orders o ON o.id = p.order_id
    GROUP BY o.user_id
),
order_data AS (
    SELECT
        o.user_id,
        SUM(CASE WHEN o.payed = 1 THEN 1 ELSE 0 END) AS paid_orders,
        SUM(CASE WHEN o.payed = 0 THEN 1 ELSE 0 END) AS unpaid_orders
    FROM orders o
    GROUP BY o.user_id
)
SELECT u.*
FROM users u
    JOIN order_data od ON od.user_id = u.id
    JOIN payment_data pd ON pd.user_id = u.id
WHERE (od.paid_orders / NULLIF(od.unpaid_orders, 0)) > 2 AND (pd.failed_payments / NULLIF(pd.total_payments, 0)) < 0.15;