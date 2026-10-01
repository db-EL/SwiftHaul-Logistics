-- Select an empty application database before importing this file.
-- Optional demo records are in demo_data.sql and must not be imported in production.

-- USERS (customers + admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    address VARCHAR(255) DEFAULT NULL,
    -- Identity verification (KYC), collected at registration to hold
    -- users accountable in the event of a dispute. See includes/file_upload.php
    -- for how these are stored securely (outside direct web access,
    -- served only via an authenticated admin script).
    selfie_path VARCHAR(255) DEFAULT NULL,
    verification_doc_type ENUM('national_id','voters_card','international_passport','drivers_license','utility_bill') DEFAULT NULL,
    verification_doc_path VARCHAR(255) DEFAULT NULL,
    kyc_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- RIDERS / DRIVERS
-- Asset-light aggregator model: riders are independent freelance
-- partners who use their own vehicle. SwiftHaul owns no fleet.
-- Riders self-register and log in directly (password_hash) —
-- no admin approval gate. current_lat/current_lng/location_updated_at
-- back the live tracking map.
CREATE TABLE riders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    email VARCHAR(150) DEFAULT NULL UNIQUE,
    password_hash VARCHAR(255) DEFAULT NULL,
    vehicle_type VARCHAR(50) DEFAULT 'Motorbike',
    vehicle_plate VARCHAR(30) DEFAULT NULL,
    status ENUM('available','on_delivery','offline') NOT NULL DEFAULT 'offline',
    bank_name VARCHAR(100) DEFAULT NULL,
    bank_code VARCHAR(10) DEFAULT NULL,
    account_number VARCHAR(20) DEFAULT NULL,
    account_name VARCHAR(120) DEFAULT NULL,
    paystack_recipient_code VARCHAR(60) DEFAULT NULL,
    wallet_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_earned DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    current_lat DECIMAL(10,7) DEFAULT NULL,
    current_lng DECIMAL(10,7) DEFAULT NULL,
    location_updated_at TIMESTAMP NULL DEFAULT NULL,
    -- Identity + vehicle verification (KYC), required at registration.
    -- A rider cannot go online (see api/rider_toggle_status.php) until
    -- an admin has reviewed these and set kyc_status = 'verified'.
    selfie_path VARCHAR(255) DEFAULT NULL,
    vehicle_photo_path VARCHAR(255) DEFAULT NULL,
    verification_doc_type ENUM('national_id','voters_card','international_passport','drivers_license','utility_bill') DEFAULT NULL,
    verification_doc_path VARCHAR(255) DEFAULT NULL,
    kyc_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- REVIEWS
-- Public platform reviews from logged-in customers — one review
-- per customer (editable), shown site-wide (index.php + reviews.php).
-- Admin can hide reviews (spam/abuse) without deleting the record.
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    rating TINYINT NOT NULL,
    comment TEXT NOT NULL,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- RIDER PUSH SUBSCRIPTIONS
-- Web Push subscriptions (one rider can have several — phone,
-- laptop, etc). Used to notify riders of new orders even when
-- the dashboard tab isn't focused/open, unlike the in-app toast
-- feed which only works while the tab is active.
CREATE TABLE rider_push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_id INT NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_endpoint (endpoint(255)),
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- SERVICES / PRICING
CREATE TABLE services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    base_fee DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    rate_per_km DECIMAL(10,2) NOT NULL DEFAULT 50.00,
    rate_per_kg DECIMAL(10,2) NOT NULL DEFAULT 30.00,
    icon VARCHAR(50) DEFAULT 'fa-truck'
) ENGINE=InnoDB;

INSERT INTO services (name, slug, description, base_fee, rate_per_km, rate_per_kg, icon) VALUES
('Express Delivery', 'express', 'Same-day delivery within the city for urgent parcels.', 800.00, 60.00, 40.00, 'fa-bolt'),
('Same-Day Delivery', 'same-day', 'Delivered before close of business today.', 600.00, 50.00, 30.00, 'fa-clock'),
('Bulk / Logistics', 'bulk', 'For business shipments and large cargo.', 1500.00, 40.00, 20.00, 'fa-boxes-stacked'),
('Standard Delivery', 'standard', 'Economical delivery within 2-3 days.', 400.00, 30.00, 15.00, 'fa-truck');

-- SHIPMENTS
-- current_status now includes 'Rider Assigned' — the moment a
-- rider claims (accepts) an order from the open pool, before they
-- physically pick it up, mirroring Bolt's "driver assigned, en
-- route to pickup" state.
CREATE TABLE shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_id VARCHAR(24) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    rider_id INT DEFAULT NULL,
    service_id INT DEFAULT NULL,
    pickup_address VARCHAR(255) NOT NULL,
    dropoff_address VARCHAR(255) NOT NULL,
    receiver_name VARCHAR(120) NOT NULL,
    receiver_phone VARCHAR(30) NOT NULL,
    weight_kg DECIMAL(6,2) DEFAULT 1.00,
    distance_km DECIMAL(6,2) DEFAULT 1.00,
    fee DECIMAL(10,2) DEFAULT 0.00,
    payment_status ENUM('unpaid','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
    paystack_reference VARCHAR(100) DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    refunded_at TIMESTAMP NULL DEFAULT NULL,
    rider_payout_status ENUM('not_applicable','pending','paid') NOT NULL DEFAULT 'not_applicable',
    current_status ENUM('Order Placed','Rider Assigned','Picked Up','In Transit','Out for Delivery','Delivered','Cancelled') NOT NULL DEFAULT 'Order Placed',
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    -- Delivery confirmation OTP: shown to the customer, handed to the
    -- rider at drop-off, required before a shipment can be marked
    -- Delivered. Decouples "money moves to the rider" from anyone's
    -- word alone — customer, rider, or admin.
    delivery_otp VARCHAR(6) DEFAULT NULL,
    delivered_confirmed_at TIMESTAMP NULL DEFAULT NULL,
    -- Alternative to OTP: the rider can instead capture the receiver's
    -- signature on their device at drop-off. Exactly one of delivery_otp
    -- (verified) or signature_path (captured) is required to mark Delivered.
    delivery_confirmation_method ENUM('otp','signature') DEFAULT NULL,
    signature_path VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE SET NULL,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ORDER REJECTIONS
-- When a rider rejects an available order, it's removed from THEIR
-- pool but stays open for every other rider — same as Bolt.
CREATE TABLE order_rejections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    rider_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rejection (shipment_id, rider_id),
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- TRACKING STATUS HISTORY
CREATE TABLE tracking_status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    status ENUM('Order Placed','Rider Assigned','Picked Up','In Transit','Out for Delivery','Delivered','Cancelled') NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- RIDER EARNINGS LEDGER
-- Created when a shipment is marked Delivered (and paid). Platform
-- keeps a commission; the rest is transferred to the rider
-- automatically (see includes/payouts.php).
CREATE TABLE rider_earnings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_id INT NOT NULL,
    shipment_id INT NOT NULL,
    shipment_fee DECIMAL(10,2) NOT NULL,
    commission_percent DECIMAL(5,2) NOT NULL,
    platform_commission DECIMAL(10,2) NOT NULL,
    rider_earning DECIMAL(10,2) NOT NULL,
    status ENUM('pending','paid') NOT NULL DEFAULT 'pending',
    paystack_transfer_code VARCHAR(60) DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_shipment_earning (shipment_id),
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- PAYOUT ATTEMPTS (audit log)
CREATE TABLE payout_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rider_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    success TINYINT(1) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rider_id) REFERENCES riders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- DELIVERY COMPLAINTS
-- Customers can report a problem with a Delivered shipment within a
-- limited window (see COMPLAINT_WINDOW_HOURS in config.php) — e.g.
-- item missing/damaged or suspected rider misconduct. Admin reviews
-- and can process a refund via Paystack if warranted (never automatic).
CREATE TABLE delivery_complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    customer_id INT NOT NULL,
    complaint_type ENUM('item_missing','item_damaged','wrong_item','rider_misconduct','other') NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
    admin_notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- RATE LIMITING
CREATE TABLE rate_limits (
    rl_key VARCHAR(150) NOT NULL PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 1,
    first_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    blocked_until TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB;

-- PASSWORD RESETS (customers and riders share this table,
-- distinguished by account_type)
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user','rider') NOT NULL DEFAULT 'user',
    account_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- SUPPORT THREADS + MESSAGES
-- Two-way messaging: a customer (or guest) writes in, an admin
-- replies from the same thread, the customer sees the reply next
-- time they check "Messages" in their dashboard.
CREATE TABLE support_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    guest_name VARCHAR(120) DEFAULT NULL,
    guest_email VARCHAR(150) DEFAULT NULL,
    subject VARCHAR(150) DEFAULT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    read_by_admin TINYINT(1) NOT NULL DEFAULT 0,
    read_by_user TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE support_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    sender_type ENUM('user','admin') NOT NULL,
    sender_name VARCHAR(120) DEFAULT NULL,
    body TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (thread_id) REFERENCES support_threads(id) ON DELETE CASCADE
) ENGINE=InnoDB;

/* Demo records live in demo_data.sql and must never be imported in production.

-- Demo accounts. Password for ALL demo accounts (customers, admin,
-- AND riders) is: Demo@1234
-- Demo KYC status is pre-set to 'verified' (no real documents attached)
-- purely so the demo/seed data is usable out of the box — in real use,
-- every account starts 'pending' until an admin reviews the uploaded
-- documents (see admin/customers.php and admin/riders.php).
INSERT INTO users (full_name, email, phone, password_hash, role, address, kyc_status) VALUES
('SwiftHaul Admin', 'admin@swifthaul.com', '08010000001', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'admin', 'SwiftHaul HQ, Port Harcourt', 'verified'),
('Chidinma Okafor', 'chidinma@example.com', '08010000002', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'customer', '12 Aba Road, Port Harcourt', 'verified'),
('Tunde Bakare', 'tunde@example.com', '08010000003', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'customer', '45 Woji Road, Port Harcourt', 'verified'),
('Amaka Chukwu', 'amaka@example.com', '08010000004', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'customer', '8 Ikwerre Road, Port Harcourt', 'verified');

INSERT INTO riders (full_name, phone, email, password_hash, vehicle_type, vehicle_plate, status, bank_name, bank_code, account_number, account_name, wallet_balance, total_earned, current_lat, current_lng, location_updated_at, kyc_status) VALUES
('Emeka Johnson', '08020000001', 'emeka.rider@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Motorbike', 'KJA-114-XY', 'available', 'Guaranty Trust Bank', '058', '0123456789', 'Emeka Johnson', 0.00, 0.00, 4.8242, 7.0336, NOW(), 'verified'),
('Grace Adeyemi', '08020000002', 'grace.rider@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Van', 'PHC-772-KL', 'on_delivery', 'Access Bank', '044', '0234567890', 'Grace Adeyemi', 0.00, 0.00, 4.8156, 7.0498, NOW(), 'verified'),
('Ibrahim Musa', '08020000003', 'ibrahim.rider@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Motorbike', 'ABJ-390-QW', 'available', 'Zenith Bank', '057', '0345678901', 'Ibrahim Musa', 0.00, 0.00, NULL, NULL, NULL, 'verified'),
('Peace Nwosu', '08020000004', 'peace.rider@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Truck', 'RIV-205-TP', 'offline', 'United Bank for Africa', '033', '0456789012', 'Peace Nwosu', 0.00, 0.00, NULL, NULL, NULL, 'pending');

INSERT INTO services (name, slug, description, base_fee, rate_per_km, rate_per_kg, icon) VALUES
('Express Delivery', 'express', 'Same-day delivery within the city for urgent parcels.', 800.00, 60.00, 40.00, 'fa-bolt'),
('Same-Day Delivery', 'same-day', 'Delivered before close of business today.', 600.00, 50.00, 30.00, 'fa-clock'),
('Bulk / Logistics', 'bulk', 'For business shipments and large cargo.', 1500.00, 40.00, 20.00, 'fa-boxes-stacked'),
('Standard Delivery', 'standard', 'Economical delivery within 2-3 days.', 400.00, 30.00, 15.00, 'fa-truck');

INSERT INTO shipments (tracking_id, customer_id, rider_id, service_id, pickup_address, dropoff_address, receiver_name, receiver_phone, weight_kg, distance_km, fee, payment_status, paystack_reference, paid_at, rider_payout_status, current_status, accepted_at, delivery_otp, delivered_confirmed_at) VALUES
('SH-9K2M7QZX41', 2, 1, 1, '12 Aba Road, Port Harcourt', '5 Trans Amadi, Port Harcourt', 'Ngozi Eze', '08030000001', 2.5, 8.0, 1180.00, 'paid', 'DEMO-REF-00001', NOW(), 'not_applicable', 'In Transit', NOW(), '482913', NULL),
('SH-3B7T9WPL62', 2, NULL, 3, '12 Aba Road, Port Harcourt', 'Onne Port, Rivers State', 'Bright Logistics Ltd', '08030000002', 45.0, 30.0, 3600.00, 'paid', 'DEMO-REF-00002', NOW(), 'not_applicable', 'Order Placed', NULL, '017365', NULL),
('SH-5F1QXN8H23', 3, 2, 2, '45 Woji Road, Port Harcourt', '9 GRA Phase 2, Port Harcourt', 'Chuka Obi', '08030000003', 1.0, 5.0, 880.00, 'paid', 'DEMO-REF-00003', NOW(), 'paid', 'Delivered', NOW(), NULL, NOW());

INSERT INTO tracking_status_history (shipment_id, status, note) VALUES
(1, 'Order Placed', 'Shipment booked by customer.'),
(1, 'Rider Assigned', 'Emeka Johnson accepted this delivery.'),
(1, 'Picked Up', 'Rider picked up parcel from sender.'),
(1, 'In Transit', 'Parcel is on the way.'),
(2, 'Order Placed', 'Shipment booked and paid for by customer — awaiting a rider to accept.'),
(3, 'Order Placed', 'Shipment booked by customer.'),
(3, 'Rider Assigned', 'Grace Adeyemi accepted this delivery.'),
(3, 'Picked Up', 'Rider picked up parcel from sender.'),
(3, 'In Transit', 'Parcel is on the way.'),
(3, 'Out for Delivery', 'Rider is near the destination.'),
(3, 'Delivered', 'Parcel delivered and confirmed with delivery code.');

INSERT INTO rider_earnings (rider_id, shipment_id, shipment_fee, commission_percent, platform_commission, rider_earning, status, paid_at) VALUES
(2, 3, 880.00, 20.00, 176.00, 704.00, 'paid', NOW());

UPDATE riders SET total_earned = 704.00, wallet_balance = 0.00 WHERE id = 2;

INSERT INTO support_threads (user_id, guest_name, guest_email, subject, status, read_by_admin, read_by_user) VALUES
(2, NULL, NULL, 'Question about a delayed delivery', 'open', 0, 1),
(NULL, 'Femi Adewale', 'femi@example.com', 'Partnership Inquiry', 'open', 0, 1);

INSERT INTO support_messages (thread_id, sender_type, sender_name, body) VALUES
(1, 'user', 'Chidinma Okafor', 'Hi, my delivery SH-9K2M7QZX41 has been "In Transit" for a while — is everything okay?'),
(2, 'user', 'Femi Adewale', 'I run a small e-commerce store and would like to discuss a bulk delivery partnership.');

INSERT INTO reviews (user_id, rating, comment) VALUES
(2, 5, 'SwiftHaul has completely changed how we handle same-day orders for our store. Tracking is spot on and the rider showed up early.'),
(3, 5, 'Bulk logistics for our warehouse used to be a headache. Now it is one dashboard and total visibility from pickup to delivery.'),
(4, 4, 'Really solid experience overall — booking took under two minutes and I could see my rider on the map the whole way. Would like a few more payment options, but very happy.');
*/
