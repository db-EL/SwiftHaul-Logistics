<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Services & Pricing';
$services = $pdo->query("SELECT * FROM services ORDER BY id ASC")->fetchAll();
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero">
    <div class="container">
        <h1>Services & Pricing</h1>
        <p>Transparent, distance-and-weight-based pricing. Estimate your delivery cost instantly below.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="services-grid stagger">
            <?php foreach ($services as $i => $s): ?>
            <div class="service-card stagger-item reveal" style="--i:<?= $i ?>">
                <div class="service-icon"><i class="fa-solid <?= e($s['icon']) ?>"></i></div>
                <h3><?= e($s['name']) ?></h3>
                <p><?= e($s['description']) ?></p>
                <div class="service-price">Base ₦<?= number_format($s['base_fee'],0) ?> + ₦<?= number_format($s['rate_per_km'],0) ?>/km + ₦<?= number_format($s['rate_per_kg'],0) ?>/kg</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section style="background:var(--white)">
    <div class="container">
        <div class="section-head reveal">
            <span class="section-tag">Cost Calculator</span>
            <h2>Estimate Your Delivery Cost</h2>
            <p>Choose a service, enter distance and weight, and get an instant quote.</p>
        </div>

        <div style="max-width:640px;margin:0 auto;" class="reveal">
            <div class="estimator-card">
                <label style="font-weight:600;font-size:0.88rem;margin-bottom:10px;display:block;">Select a Service</label>
                <div class="service-select-grid" id="serviceSelect">
                    <?php foreach ($services as $i => $s): ?>
                    <div class="service-pill <?= $i === 0 ? 'active' : '' ?>"
                         data-base="<?= e($s['base_fee']) ?>"
                         data-km="<?= e($s['rate_per_km']) ?>"
                         data-kg="<?= e($s['rate_per_kg']) ?>"
                         data-name="<?= e($s['name']) ?>">
                        <i class="fa-solid <?= e($s['icon']) ?>"></i>
                        <span><?= e($s['name']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="distance">Distance (km)</label>
                        <input type="number" id="distance" class="form-control" min="0.5" step="0.5" value="5">
                    </div>
                    <div class="form-group">
                        <label for="weight">Weight (kg)</label>
                        <input type="number" id="weight" class="form-control" min="0.1" step="0.1" value="2">
                    </div>
                </div>

                <button id="calcBtn" class="btn btn-primary btn-block"><i class="fa-solid fa-calculator"></i> Calculate Estimate</button>

                <div class="estimator-result" id="estimatorResult" style="display:none;">
                    <div style="font-size:0.85rem;opacity:0.75;margin-bottom:6px;" id="resultServiceName">Service</div>
                    <div class="fee-amount" id="resultFee">₦0</div>
                    <div style="font-size:0.78rem;opacity:0.65;margin-top:8px;">Estimated fee — final price confirmed at booking</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section style="padding-top:0;">
    <div class="cta-band reveal">
        <h2>Have a shipment ready to go?</h2>
        <p>Sign up and book your delivery in under two minutes.</p>
        <a href="<?= BASE_URL ?>/register.php" class="btn btn-navy">Create Free Account</a>
    </div>
</section>

<script>
(function() {
    const pills = document.querySelectorAll('.service-pill');
    let active = document.querySelector('.service-pill.active');

    pills.forEach(pill => {
        pill.addEventListener('click', () => {
            pills.forEach(p => p.classList.remove('active'));
            pill.classList.add('active');
            active = pill;
        });
    });

    document.getElementById('calcBtn').addEventListener('click', () => {
        const distance = parseFloat(document.getElementById('distance').value) || 0;
        const weight = parseFloat(document.getElementById('weight').value) || 0;
        const base = parseFloat(active.dataset.base);
        const km = parseFloat(active.dataset.km);
        const kg = parseFloat(active.dataset.kg);
        const name = active.dataset.name;

        const fee = base + (distance * km) + (weight * kg);

        document.getElementById('resultServiceName').textContent = name + ' — ' + distance + 'km, ' + weight + 'kg';
        document.getElementById('resultFee').textContent = '₦' + fee.toLocaleString(undefined, {maximumFractionDigits: 0});
        document.getElementById('estimatorResult').style.display = 'block';
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
