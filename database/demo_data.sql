-- DEMO DATA ONLY. Never import this file into a production database.
-- All demo accounts share the password Demo@1234.
-- Demo KYC is pre-verified and the included bank details are fictional.

INSERT INTO users (full_name, email, phone, password_hash, role, address, kyc_status) VALUES
('SwiftHaul Admin', 'admin@swifthaul.com', '08010000001', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'admin', 'SwiftHaul HQ, Port Harcourt', 'verified'),
('Chidinma Okafor', 'chidinma@example.com', '08010000002', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'customer', '12 Aba Road, Port Harcourt', 'verified'),
('Tunde Bakare', 'tunde@example.com', '08010000003', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'customer', '45 Woji Road, Port Harcourt', 'verified'),
('Amaka Chukwu', 'amaka@example.com', '08010000004', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'customer', '8 Ikwerre Road, Port Harcourt', 'verified');

INSERT INTO riders (full_name, phone, email, password_hash, vehicle_type, vehicle_plate, status, bank_name, bank_code, account_number, account_name, wallet_balance, total_earned, current_lat, current_lng, location_updated_at, kyc_status) VALUES
('Emeka Johnson', '08020000001', 'emeka.rider@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Motorbike', 'KJA-114-XY', 'available', 'Guaranty Trust Bank', '058', '0123456789', 'Emeka Johnson', 0.00, 0.00, 4.8242, 7.0336, NOW(), 'verified'),
('Grace Adeyemi', '08020000002', 'grace.adeyemi@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Van', 'PHC-772-KL', 'on_delivery', 'Access Bank', '044', '0234567890', 'Grace Adeyemi', 0.00, 0.00, 4.8156, 7.0498, NOW(), 'verified'),
('Ibrahim Musa', '08020000003', 'ibrahim.musa@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Motorbike', 'ABJ-390-QW', 'available', 'Zenith Bank', '057', '0345678901', 'Ibrahim Musa', 0.00, 0.00, NULL, NULL, NULL, 'verified'),
('Peace Nwosu', '08020000004', 'peace.nwosu@example.com', '$2b$12$ujilSHpkYBbE/P5b6qn2pOE43bLFyy5GQKaqtltSBUKywBKY6T3sG', 'Truck', 'RIV-205-TP', 'offline', 'United Bank for Africa', '033', '0456789012', 'Peace Nwosu', 0.00, 0.00, NULL, NULL, NULL, 'pending');

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