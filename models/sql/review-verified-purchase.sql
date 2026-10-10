SELECT 1
FROM {PREFIX}shop_orders AS O
INNER JOIN {PREFIX}shop_order_items AS I ON I.order_id = O.id
WHERE O.customer_email = :email
  AND I.product_id = :product_id
  AND O.status IN ('paid', 'dispatched')
  AND O.is_test_payment = 0
LIMIT 1
