CREATE TABLE users (
   id SERIAL PRIMARY KEY,
   username VARCHAR(255) NOT NULL
);

CREATE TABLE test.orders (
    id SERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payed BOOLEAN NOT NULL DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES test.users(id) ON DELETE CASCADE
);

CREATE TABLE test.payments (
    id SERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    pay_system VARCHAR(50) NOT NULL,
    status VARCHAR(50) CHECK (status IN ('success', 'failed', 'pending')) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES test.orders(id) ON DELETE CASCADE
);

CREATE INDEX idx_orders_user_id ON test.orders(user_id);
CREATE INDEX idx_payments_order_id ON test.payments(order_id);
CREATE INDEX idx_payments_status ON test.payments(status);