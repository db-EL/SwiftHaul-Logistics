<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Fast, Trackable Delivery';
$hasHeroSection = true;

$services = $pdo->query("SELECT * FROM services ORDER BY id ASC")->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="hero" id="heroSlider">
    <div class="hero-slider">
        <div class="hero-slide slide-1 active"></div>
        <div class="hero-slide slide-2"></div>
        <div class="hero-slide slide-3"></div>
        <div class="hero-slide slide-4"></div>
    </div>
    <div class="hero-ambient"><span></span><span></span><span></span></div>
    <div class="hero-overlay"></div>
    <div class="hero-slider-dots" id="heroDots">
        <button class="active" data-slide="0" aria-label="Slide 1"></button>
        <button data-slide="1" aria-label="Slide 2"></button>
        <button data-slide="2" aria-label="Slide 3"></button>
        <button data-slide="3" aria-label="Slide 4"></button>
    </div>
    <div class="container hero-grid">
        <div>
            <span class="hero-eyebrow"><i class="fa-solid fa-bolt"></i> Same-day delivery available</span>
            <h1>Ship it fast.<br>Track it <span>live</span>.<br>Deliver with confidence.</h1>
            <p class="lead">SwiftHaul connects you to riders and logistics partners across the city so every parcel — big or small — arrives on time, every time.</p>
            <div class="hero-actions">
                <a href="<?= BASE_URL ?>/track.php" class="btn btn-primary"><i class="fa-solid fa-location-crosshairs"></i> Track a Package</a>
                <a href="<?= BASE_URL ?>/register.php" class="btn btn-outline"><i class="fa-solid fa-box"></i> Ship Now</a>
            </div>
            <div class="hero-stats">
                <div><div class="stat-num">12k+</div><div class="stat-label">Deliveries made</div></div>
                <div><div class="stat-num">98%</div><div class="stat-label">On-time rate</div></div>
                <div><div class="stat-num">4.9<i class="fa-solid fa-star" style="font-size:.9rem;color:#FF6B35"></i></div><div class="stat-label">Customer rating</div></div>
            </div>
        </div>
        <div class="hero-visual">
            <div class="hero-glow"></div>
            <!-- <svg class="hero-illustration" viewBox="0 0 480 420" fill="none" xmlns="http://www.w3.org/2000/svg">
                <ellipse cx="240" cy="380" rx="170" ry="18" fill="#000" opacity="0.15"/>
                <rect x="70" y="150" width="240" height="150" rx="14" fill="#132a4d"/>
                <rect x="70" y="150" width="240" height="40" rx="14" fill="#FF6B35"/>
                <rect x="300" y="190" width="110" height="110" rx="10" fill="#1b3b66"/>
                <circle cx="150" cy="310" r="26" fill="#0B1F3A" stroke="#FF6B35" stroke-width="6"/>
                <circle cx="150" cy="310" r="8" fill="#F7F8FB"/>
                <circle cx="330" cy="310" r="26" fill="#0B1F3A" stroke="#FF6B35" stroke-width="6"/>
                <circle cx="330" cy="310" r="8" fill="#F7F8FB"/>
                <rect x="105" y="210" width="60" height="60" rx="8" fill="#F7F8FB" opacity="0.9"/>
                <path d="M120 240h30M120 250h20" stroke="#0B1F3A" stroke-width="4" stroke-linecap="round"/>
                <path d="M330 60c-30 0-54 24-54 54 0 40 54 90 54 90s54-50 54-90c0-30-24-54-54-54z" fill="#FF6B35"/>
                <circle cx="330" cy="114" r="20" fill="#0B1F3A"/>
                <path d="M60 120l24 24M60 144l24-24" stroke="#ffffff" stroke-opacity="0.4" stroke-width="4" stroke-linecap="round"/>
            </svg> -->
            <div class="hero-card-float card1"><i class="fa-solid fa-circle-check"></i> Order Delivered</div>
            <div class="hero-card-float card2"><i class="fa-solid fa-truck-fast"></i> Rider en route — 8 min</div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section>
    <div class="container">
        <div class="section-head reveal">
            <span class="section-tag">Simple Process</span>
            <h2>How SwiftHaul Works</h2>
            <p>From booking to doorstep, track every step of your delivery in real time.</p>
        </div>
        <div class="steps-grid stagger">
            <div class="step-card stagger-item reveal" style="--i:0">
                <span class="step-num">01</span>
                <div class="step-icon"><i class="fa-solid fa-box-open"></i></div>
                <h3>Book a Shipment</h3>
                <p>Enter pickup, drop-off and parcel details in under a minute.</p>
            </div>
            <div class="step-card stagger-item reveal" style="--i:1">
                <span class="step-num">02</span>
                <div class="step-icon"><i class="fa-solid fa-hand-holding-hand"></i></div>
                <h3>Rider Pickup</h3>
                <p>A verified rider picks up your parcel from your location.</p>
            </div>
            <div class="step-card stagger-item reveal" style="--i:2">
                <span class="step-num">03</span>
                <div class="step-icon"><i class="fa-solid fa-route"></i></div>
                <h3>Live Tracking</h3>
                <p>Follow your parcel's journey with real-time status updates.</p>
            </div>
            <div class="step-card stagger-item reveal" style="--i:3">
                <span class="step-num">04</span>
                <div class="step-icon"><i class="fa-solid fa-house-circle-check"></i></div>
                <h3>Delivered</h3>
                <p>Receiver signs off and you get instant delivery confirmation.</p>
            </div>
        </div>
    </div>
</section>

<!-- SERVICES -->
<section style="background:var(--white)">
    <div class="container">
        <div class="section-head reveal">
            <span class="section-tag">What We Offer</span>
            <h2>Services Built Around You</h2>
            <p>Whether it's a single envelope or a full pallet, we've got a service for it.</p>
        </div>
        <div class="services-grid stagger">
            <?php foreach ($services as $i => $s): ?>
            <div class="service-card stagger-item reveal" style="--i:<?= $i ?>">
                <div class="service-icon"><i class="fa-solid <?= e($s['icon']) ?>"></i></div>
                <h3><?= e($s['name']) ?></h3>
                <p><?= e($s['description']) ?></p>
                <div class="service-price">From ₦<?= number_format($s['base_fee'], 0) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-20" style="margin-top:40px;">
            <a href="<?= BASE_URL ?>/services.php" class="btn btn-navy">View Pricing & Estimator <i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- STATS BAND -->
<section class="stats-band">
    <div class="container">
        <div class="stats-grid">
            <div class="reveal"><div class="stat-num" data-counter="12480" data-suffix="+">0</div><div class="stat-label">Parcels Delivered</div></div>
            <div class="reveal"><div class="stat-num" data-counter="340" data-suffix="+">0</div><div class="stat-label">Active Riders</div></div>
            <div class="reveal"><div class="stat-num" data-counter="27">0</div><div class="stat-label">Cities Covered</div></div>
            <div class="reveal"><div class="stat-num" data-counter="98" data-suffix="%">0</div><div class="stat-label">On-Time Rate</div></div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<?php
$homeReviews = $pdo->query("
    SELECT r.*, u.full_name FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.is_hidden = 0
    ORDER BY r.rating DESC, r.created_at DESC
    LIMIT 3
")->fetchAll();
?>
<?php if ($homeReviews): ?>
<section>
    <div class="container">
        <div class="section-head reveal">
            <span class="section-tag">Testimonials</span>
            <h2>What Our Customers Say</h2>
            <p><a href="<?= BASE_URL ?>/reviews.php" style="color:var(--orange-500);font-weight:600;">See all reviews <i class="fa-solid fa-arrow-right" style="font-size:0.8rem;"></i></a></p>
        </div>
        <div class="testimonial-grid stagger">
            <?php foreach ($homeReviews as $i => $rev): ?>
            <div class="testimonial-card stagger-item reveal" style="--i:<?= $i ?>">
                <div class="testimonial-stars">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="fa-solid fa-star" style="color:<?= $s <= $rev['rating'] ? 'var(--orange-500)' : '#e2e5ec' ?>;"></i>
                    <?php endfor; ?>
                </div>
                <p class="quote"><?= e(mb_strimwidth($rev['comment'], 0, 160, '…')) ?></p>
                <div class="testimonial-author">
                    <div class="avatar-circle"><?= e(mb_strtoupper(mb_substr($rev['full_name'],0,1))) ?></div>
                    <div><div class="name"><?= e($rev['full_name']) ?></div><div class="role">Verified Customer</div></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>


<!-- CTA -->
<section style="padding-bottom:120px;">
    <div class="cta-band reveal">
        <h2>Ready to ship your first package?</h2>
        <p>Create a free account and get your first delivery moving in minutes.</p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-navy">Get Started Free <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
