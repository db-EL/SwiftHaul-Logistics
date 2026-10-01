<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Privacy Policy';
require __DIR__ . '/includes/header.php';

$lastUpdated = 'July 2026';
$companyEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'hello@swifthaul.com';
?>

<section class="page-hero">
    <div class="container">
        <h1>Privacy Policy</h1>
        <p>Last updated: <?= e($lastUpdated) ?></p>
    </div>
</section>

<section>
    <div class="container">
        <div class="policy-content">

            <p class="policy-intro">This Privacy Policy explains what personal data SwiftHaul Logistics ("SwiftHaul," "we," "us") collects from customers, freelance riders, and visitors to this website; why we collect it; who we share it with; and the choices and rights you have. It applies to swifthaul.com and the SwiftHaul platform (web app, customer dashboard, and rider dashboard).</p>

            <p class="policy-notice"><i class="fa-solid fa-circle-info"></i> If you're adapting this document for your own deployment: this is a genuine, specific attempt at a clear policy for exactly what this codebase does — not filler text — but it is not a substitute for review by a qualified lawyer in your jurisdiction before real launch. This is especially true given that identity documents and selfies are collected below: this is sensitive personal data under most data protection frameworks (e.g. Nigeria's NDPR, GDPR if you serve EU users), and may carry registration or disclosure obligations for your business that a lawyer should confirm.</p>

            <h2>1. Information We Collect</h2>

            <h3>1.1 Account information</h3>
            <ul>
                <li><strong>Customers:</strong> full name, email address, phone number, delivery address, and password (stored as a bcrypt hash — we never store or can see your actual password).</li>
                <li><strong>Riders:</strong> full name, email, phone number, vehicle type and plate number, and bank account details (bank name, account number, account name) used solely to pay you for completed deliveries via Paystack.</li>
                <li><strong>Receivers:</strong> when you book a delivery, we also collect the name and phone number of the person receiving the parcel, even though they don't have an account with us.</li>
            </ul>

            <h3>1.2 Identity verification documents (selfie, ID, proof of address)</h3>
            <p>Every customer and rider is required to submit, at registration: a clear selfie photograph, and one government-issued identity document or proof-of-address document (national ID card, voter's card, international passport, driver's license, or a utility bill showing your current address). Riders additionally submit a photo of their vehicle showing its plate number. We collect this specifically to hold accounts accountable in the rare event of a dispute over a delivery (for example, suspected theft or misdelivery) — not for marketing, profiling, or any purpose beyond identity verification and dispute resolution.</p>
            <p>These documents are stored in a location that is not directly accessible over the web, and are only ever viewable by an authenticated administrator reviewing your account for verification purposes — never displayed publicly, never shared with other customers or riders, and never used for any purpose beyond verification and, if necessary, a specific fraud/dispute investigation. Photos are re-processed on upload to strip embedded metadata (many phone cameras embed GPS coordinates and device information into photo files; we remove this rather than retain it).</p>

            <h3>1.3 Shipment & delivery data</h3>
            <p>Pickup and drop-off addresses, parcel weight and declared value, delivery fees, and the full status history of each shipment (booked, assigned, picked up, delivered, etc.), including timestamps.</p>

            <h3>1.4 Delivery confirmation (signature or code)</h3>
            <p>To confirm a delivery is complete, either a one-time confirmation code (shown to the customer, relayed to the rider by the receiver) or a signature captured directly on the rider's device by the receiver is required. A captured signature is stored as an image and is only ever viewable by an authenticated administrator, primarily for resolving a delivery complaint.</p>

            <h3>1.5 Live location data (riders only)</h3>
            <p>While a rider is logged in and has enabled location sharing, their device's GPS coordinates are sent to our servers periodically (roughly every 15 seconds) so that the customer tracking a specific active delivery can see the rider's live position on a map. We only display a rider's location on the tracking page for a shipment currently assigned to them, only while that delivery is active, and never once the delivery is complete. We do not track or store a rider's location while they are offline or between deliveries beyond what's needed to show the most recent position.</p>

            <h3>1.6 Payment data</h3>
            <p>We do not store your card number, CVV, or full payment credentials. Payments are processed directly by <strong>Paystack</strong>, a licensed payment processor; we receive and store only the transaction reference, amount, and status Paystack reports back to us. Please review <a href="https://paystack.com/terms" target="_blank" rel="noopener">Paystack's own privacy policy</a> for how they handle your payment details.</p>

            <h3>1.7 Reviews, messages, and complaints</h3>
            <p>If you submit a review, your name, star rating, and written comment are displayed publicly on our Reviews page and may be featured on our homepage. If you contact support or message us through the platform, we store the content of that conversation, including any replies from our team. If you file a delivery complaint, we store its details and any internal notes our team adds while investigating — complaint content is never made public.</p>

            <h3>1.8 Technical & usage data</h3>
            <p>We automatically log standard technical data such as IP address, browser type, and request timestamps — used for security purposes like rate-limiting repeated failed logins and detecting abuse of public endpoints (e.g. the package tracking lookup).</p>

            <h2>2. How We Use Your Information</h2>
            <ul>
                <li>To create and manage your account, and authenticate you when you log in.</li>
                <li>To verify identity and reduce fraud/theft risk for a peer-to-peer delivery marketplace, using the documents described in 1.2.</li>
                <li>To process, assign, track, and complete deliveries — including matching your shipment to an available rider.</li>
                <li>To process payments and, for riders, to pay out earnings automatically after a confirmed delivery.</li>
                <li>To investigate a delivery complaint and, where warranted, process a refund.</li>
                <li>To send essential account emails, such as password reset links, from our monitored support mailbox.</li>
                <li>To send riders push or in-app notifications about new available orders.</li>
                <li>To display public reviews, and to respond to support messages you send us.</li>
                <li>To detect and prevent fraud, abuse, and unauthorized access (e.g. rate-limiting login attempts, verifying delivery confirmation codes before releasing rider payouts).</li>
                <li>To comply with legal obligations, such as maintaining financial transaction records.</li>
            </ul>
            <p>We do not sell your personal data to third parties, and we do not use your data — including your identity documents — for advertising, profiling, or any purpose beyond what's described in this policy.</p>

            <h2>3. Who We Share Data With</h2>
            <ul>
                <li><strong>Paystack</strong> — to process customer payments, refunds, and rider payouts.</li>
                <li><strong>The assigned rider</strong> — sees the customer's pickup/drop-off address and the receiver's name and phone number, strictly as needed to complete the delivery. Riders do not see a customer's identity documents or selfie.</li>
                <li><strong>The customer</strong> — sees the assigned rider's name and, while a delivery is active, their live location on the map. Customers do not see a rider's identity documents, vehicle photo, or selfie.</li>
                <li><strong>Authorized SwiftHaul administrators only</strong> — identity documents, selfies, and delivery signatures are visible solely to admin accounts reviewing verification status or investigating a specific complaint, never to other customers or riders.</li>
                <li><strong>Service providers</strong> that help us run the platform: our email delivery provider (Gmail/Google Workspace SMTP) for transactional email, and browser push notification services (e.g. Google's or Mozilla's push infrastructure) to deliver push notifications to riders who opt in.</li>
                <li><strong>Law enforcement or regulators</strong>, only where we are legally required to disclose information.</li>
            </ul>
            <p>We do not otherwise share your personal data with third parties for their own marketing purposes.</p>

            <h2>4. Cookies & Local Storage</h2>
            <p>We use a single essential session cookie to keep you logged in; it is not used for advertising or cross-site tracking, and it's marked HttpOnly (inaccessible to JavaScript) and SameSite to reduce misuse. We do not use third-party advertising or analytics cookies.</p>

            <h2>5. Data Retention</h2>
            <p>We retain account and shipment records for as long as your account is active and for a reasonable period afterward to meet legal, accounting, and dispute-resolution obligations (for example, financial transaction records related to payments and payouts). Identity documents and selfies are retained for as long as your account is active, plus a limited period afterward in case of an outstanding dispute, and are deleted on request subject to section 6 below. Live location data is not retained beyond what's needed to show the most recent position during an active delivery — we do not keep a historical GPS trail of a rider's movements. Public reviews remain visible until you request removal or an admin removes them.</p>

            <h2>6. Your Rights & Choices</h2>
            <ul>
                <li><strong>Access & correction:</strong> you can view and update most of your account details directly from your dashboard (customer or rider).</li>
                <li><strong>Review removal:</strong> you may edit your review at any time from the Reviews page, or contact us to have it removed entirely.</li>
                <li><strong>Location sharing:</strong> riders can decline or revoke location permission at any time through their browser settings; doing so will limit the customer's ability to see a live map for that delivery, but will not affect other tracking information.</li>
                <li><strong>Push notifications:</strong> riders can decline the push notification permission prompt, or disable it later via browser settings, without losing access to the in-app order feed.</li>
                <li><strong>Account deletion:</strong> contact us using the details below to request deletion of your account, including your identity documents and selfie; we may retain certain records where we have a legal or legitimate business obligation to do so (e.g. completed financial transactions, or documents relevant to an open dispute).</li>
                <li><strong>Marketing communications:</strong> we do not currently send marketing email; the only emails you'll receive are essential account/transactional emails such as password resets.</li>
            </ul>

            <h2>7. Data Security</h2>
            <p>We use industry-standard practices including password hashing (bcrypt), encrypted connections for payment processing, server-side verification of all payment and delivery-confirmation events, rate-limiting to slow down automated abuse, and storing identity documents outside of direct web access so they can only be retrieved through an authenticated administrator action. No system is perfectly secure, and we encourage you to use a strong, unique password for your account.</p>

            <h2>8. Our Ethical Commitments</h2>
            <p>Beyond what's legally required, we aim to hold ourselves to a few plain commitments:</p>
            <ul>
                <li><strong>Confidentiality by design:</strong> sensitive documents (identity documents, selfies, signatures) are architecturally restricted to admin-only access — there is no page or feature anywhere in the product that shows them to another customer or rider.</li>
                <li><strong>Purpose limitation:</strong> data collected for one reason (e.g. identity verification) is not repurposed for something you wouldn't reasonably expect, like marketing or profiling.</li>
                <li><strong>Transparency in transactions:</strong> before you pay, you see a full breakdown of the delivery fee; every payment is confirmed both instantly and independently (via a payment provider webhook, not just your browser); and your dashboard keeps a permanent, accurate record of every charge, refund, and status change for each shipment.</li>
                <li><strong>Proportionate collection:</strong> we collect what's needed for safety and accountability in a peer-to-peer delivery marketplace, not more than that.</li>
                <li><strong>Human review for consequential decisions:</strong> refunds and account verification/rejection are always reviewed by a person — never an automated rule alone.</li>
            </ul>

            <h2>9. Children's Privacy</h2>
            <p>SwiftHaul is intended for use by adults capable of entering into a contract (e.g. booking and paying for deliveries, or working as a freelance rider) and legally able to hold the identity documents this platform requires. We do not knowingly collect data from children, and accounts should not be created by or on behalf of anyone under the age required by applicable law in their jurisdiction.</p>

            <h2>10. Changes to This Policy</h2>
            <p>We may update this Privacy Policy from time to time as the platform evolves. We'll update the "Last updated" date above when we do. Material changes affecting how we handle your data will be communicated where practical.</p>

            <h2>11. Contact Us</h2>
            <p>Questions about this policy or how your data is handled? Reach us at <a href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?></a>, or send a message through our <a href="<?= BASE_URL ?>/contact.php">Contact page</a>.</p>

        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
