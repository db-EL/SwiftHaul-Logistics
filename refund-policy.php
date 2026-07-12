<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Refund Policy';
require __DIR__ . '/includes/header.php';

$lastUpdated = 'July 2026';
$companyEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'hello@swifthaul.com';
?>

<section class="page-hero">
    <div class="container">
        <h1>Refund Policy</h1>
        <p>Last updated: <?= e($lastUpdated) ?></p>
    </div>
</section>

<section>
    <div class="container">
        <div class="policy-content reveal">

            <p class="policy-intro">This policy explains when a delivery fee paid through SwiftHaul is eligible for a refund, how to request one, and how refunds are processed.</p>

            <p class="policy-notice"><i class="fa-solid fa-circle-info"></i> This is a genuine, specific policy for how this platform's payment and delivery-confirmation flow actually works — not filler text. As with the Privacy Policy, it isn't a substitute for legal review in your jurisdiction, particularly around consumer protection law.</p>

            <h2>1. How Payments Work</h2>
            <p>When you book a delivery, the full fee is charged immediately via Paystack. That fee is only ever paid out to the assigned rider (minus our platform commission) once a delivery is confirmed as complete — either by the receiver's confirmation code or their captured signature. This means there's always a specific, auditable point at which "delivered" was confirmed, which is what any refund review starts from.</p>

            <h2>2. When You're Eligible for a Refund</h2>
            <ul>
                <li><strong>Non-delivery:</strong> your shipment was paid for but never delivered, and there's no rider assigned or no realistic path to completion.</li>
                <li><strong>Item damaged in transit:</strong> your item arrived visibly damaged in a way inconsistent with normal handling.</li>
                <li><strong>Item missing or wrong item delivered:</strong> what arrived doesn't match what was sent.</li>
                <li><strong>Suspected rider misconduct:</strong> for example, evidence the rider did not actually deliver to the stated address, or other fraud.</li>
                <li><strong>Duplicate or erroneous charge:</strong> you were charged more than once for the same booking, or charged in error.</li>
            </ul>

            <h2>3. How to Request a Refund</h2>
            <p>For any of the situations above <strong>after</strong> a delivery has been marked complete, use the <strong>"Report a Problem"</strong> button on the shipment in your dashboard. This is available for <?= COMPLAINT_WINDOW_HOURS ?> hours after delivery is confirmed — after that window, please contact us directly via <a href="<?= BASE_URL ?>/contact.php">our contact form</a>, though late reports are harder for us to investigate thoroughly.</p>
            <p>If your shipment was paid for but never picked up or delivered at all, contact us any time via the contact form or your <a href="<?= BASE_URL ?>/messages.php">Messages</a> — there's no time limit for a non-delivery refund request.</p>

            <h2>4. How We Review a Complaint</h2>
            <p>Every complaint is reviewed by a person on our team — refunds are <strong>never</strong> issued automatically. We look at the shipment's full status history, the delivery confirmation method used (confirmation code or signature), any messages exchanged, and the rider's account standing. We may reach out to you or the rider for more information before deciding.</p>

            <h2>5. What's Generally Not Eligible</h2>
            <ul>
                <li>An incorrect pickup or drop-off address supplied by you at booking, where the delivery otherwise completed as instructed.</li>
                <li>A delivery correctly confirmed by OTP or signature, with no specific evidence of a problem beyond a change of mind.</li>
                <li>Delays caused by incorrect or unreachable contact details for the receiver.</li>
                <li>Items prohibited under our terms of service, or misrepresented at booking (e.g. declared weight/contents that didn't match).</li>
            </ul>

            <h2>6. How Refunds Are Paid</h2>
            <p>Approved refunds are issued back to your original Paystack payment method. Processing time after we approve a refund depends on Paystack and your bank/card issuer, typically within 5–10 business days. We do not offer cash refunds or refunds to a different account than the one used to pay.</p>

            <h2>7. Effect on Rider Payouts</h2>
            <p>If a rider has already been paid for a delivery that's later refunded due to confirmed misconduct or fraud, that is handled separately between us and the rider (which may include withholding future earnings or removing their account) — it does not delay or reduce your refund.</p>

            <h2>8. Cancellations Before Delivery</h2>
            <p>If you need to cancel a shipment that hasn't yet been picked up, contact us as soon as possible via <a href="<?= BASE_URL ?>/contact.php">Contact</a>. A shipment already accepted by a rider and en route may not be cancellable without a partial charge for the rider's time and travel.</p>

            <h2>9. Contact Us</h2>
            <p>Questions about this policy or an existing complaint? Reach us at <a href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?></a> or through <a href="<?= BASE_URL ?>/contact.php">our contact page</a>.</p>

        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
