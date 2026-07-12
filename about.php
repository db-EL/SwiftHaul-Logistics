<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About Us';
require __DIR__ . '/includes/header.php';
?>

<section class="about-hero">
    <div class="container">
        <h1>Moving Things Forward, Together</h1>
        <p style="color:rgba(255,255,255,0.75); max-width:600px; margin:14px auto 0;">SwiftHaul was built to make last-mile delivery simple, transparent, and fast for everyone — from a single parcel to a full warehouse dispatch.</p>
    </div>
</section>

<section>
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:50px;align-items:center;">
            <div class="reveal-left">
                <span class="section-tag">Our Story</span>
                <h2 style="margin-bottom:18px;">Built From a Simple Frustration</h2>
                <p style="color:var(--text-muted);margin-bottom:16px;">SwiftHaul started with a simple problem: local deliveries were slow, opaque, and hard to track. Customers had no idea where their parcel was until it showed up — or didn't.</p>
                <p style="color:var(--text-muted);margin-bottom:16px;">SwiftHaul aiims to power thousands of deliveries every month with a network of verified riders, real-time tracking, and a platform businesses can rely on.</p>
                <p style="color:var(--text-muted);">We run as an <strong>asset-light aggregator</strong> — like Uber for delivery. We don't own a single bike, van, or truck. Every rider on our platform is an independent partner who brings their own vehicle, and is paid automatically for every delivery they complete.</p>
            </div>
            <div class="reveal-right">
                <svg viewBox="0 0 400 300" width="100%">
                    <rect width="400" height="300" rx="20" fill="#0B1F3A"/>
                    <rect x="40" y="60" width="180" height="120" rx="12" fill="#FF6B35"/>
                    <rect x="230" y="100" width="120" height="80" rx="10" fill="#1b3b66"/>
                    <circle cx="90" cy="200" r="20" fill="#F7F8FB"/>
                    <circle cx="290" cy="200" r="20" fill="#F7F8FB"/>
                </svg>
            </div>
        </div>
    </div>
</section>

<section style="background:var(--white);">
    <div class="container">
        <div class="section-head reveal">
            <span class="section-tag">Our Values</span>
            <h2>Mission & Vision</h2>
        </div>
        <div class="value-grid stagger">
            <div class="value-card stagger-item reveal" style="--i:0">
                <div class="step-icon" style="margin:0 auto 18px;"><i class="fa-solid fa-bullseye"></i></div>
                <h3>Our Mission</h3>
                <p style="color:var(--text-muted);margin-top:10px;">To make every delivery fast, transparent, and stress-free — for businesses and individuals alike.</p>
            </div>
            <div class="value-card stagger-item reveal" style="--i:1">
                <div class="step-icon" style="margin:0 auto 18px;"><i class="fa-solid fa-eye"></i></div>
                <h3>Our Vision</h3>
                <p style="color:var(--text-muted);margin-top:10px;">To be the most trusted logistics network in every city we operate in.</p>
            </div>
            <div class="value-card stagger-item reveal" style="--i:2">
                <div class="step-icon" style="margin:0 auto 18px;"><i class="fa-solid fa-heart"></i></div>
                <h3>Our Values</h3>
                <p style="color:var(--text-muted);margin-top:10px;">Reliability, transparency, and genuine care for every parcel we carry.</p>
            </div>
        </div>
    </div>
</section>

<section>
    <div class="container">
        <div class="section-head reveal">
            <span class="section-tag">Meet The Founder</span>
            <h2>The Person Behind SwiftHaul</h2>
        </div>
        <div class="founder-card reveal" style="max-width:520px;margin:0 auto;">
            <div class="founder-photo">TN</div>
            <h3>Tamunobere EL-Nimim Okorite</h3>
            <p class="founder-role">Founder & CEO, SwiftHaul Logistics</p>
            <p class="founder-bio">Building SwiftHaul to make last-mile delivery in Nigeria fast, transparent, and fair — for customers and for the independent riders who power every delivery.</p>
        </div>
    </div>
</section>

<section style="padding-top:0;">
    <div class="cta-band reveal">
        <h2>Join thousands shipping with SwiftHaul</h2>
        <p>Create an account and book your first delivery today.</p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-navy">Get Started</a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
