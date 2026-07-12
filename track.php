<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Track Your Package';
require __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<section class="page-hero">
    <div class="container">
        <h1>Track Your Package</h1>
        <p>Enter your tracking ID below to see live status updates — including your rider's live location once they're on the way.</p>
    </div>
</section>

<section>
    <div class="container">
        <div class="tracker-search reveal">
            <input type="text" id="trackingInput" class="form-control" placeholder="e.g. SH-9K2M7QZX41">
            <button id="trackBtn" class="btn btn-primary">
                <span id="trackBtnText"><i class="fa-solid fa-magnifying-glass"></i> Track</span>
                <i class="fa-solid fa-circle-notch spin" id="trackSpinner"></i>
            </button>
        </div>
        <p class="text-center" style="color:var(--text-muted); font-size:0.85rem; margin-top:14px;">
            Try a demo ID: <strong>SH-9K2M7QZX41</strong>, <strong>SH-3B7T9WPL62</strong>, or <strong>SH-5F1QXN8H23</strong>
        </p>

        <div id="trackMessage"></div>

        <div class="tracker-result" id="trackerResult" style="display:none;">
            <div class="dash-header" style="margin-bottom:0;">
                <div>
                    <div style="font-size:0.8rem;color:var(--text-muted);">Tracking ID</div>
                    <h3 id="resTrackingId" style="font-size:1.3rem;"></h3>
                </div>
                <span class="status-badge" id="resStatusBadge"></span>
            </div>

            <div class="progress-track" id="progressTrack">
                <div class="progress-fill" id="progressFill" style="width:0%"></div>
                <div class="progress-step" data-status="Order Placed"><div class="progress-dot"><i class="fa-solid fa-clipboard-check"></i></div><div class="progress-label">Order Placed</div></div>
                <div class="progress-step" data-status="Rider Assigned"><div class="progress-dot"><i class="fa-solid fa-user-check"></i></div><div class="progress-label">Rider Assigned</div></div>
                <div class="progress-step" data-status="Picked Up"><div class="progress-dot"><i class="fa-solid fa-hand-holding-hand"></i></div><div class="progress-label">Picked Up</div></div>
                <div class="progress-step" data-status="In Transit"><div class="progress-dot"><i class="fa-solid fa-truck"></i></div><div class="progress-label">In Transit</div></div>
                <div class="progress-step" data-status="Out for Delivery"><div class="progress-dot"><i class="fa-solid fa-route"></i></div><div class="progress-label">Out for Delivery</div></div>
                <div class="progress-step" data-status="Delivered"><div class="progress-dot"><i class="fa-solid fa-house-circle-check"></i></div><div class="progress-label">Delivered</div></div>
            </div>

            <div id="liveMapWrap" style="display:none;margin-top:30px;">
                <div class="meta-label" style="margin-bottom:10px;"><i class="fa-solid fa-location-crosshairs"></i> Live Rider Location</div>
                <div id="liveMap"></div>
                <div id="liveMapUpdated" style="font-size:0.78rem;color:var(--text-muted);margin-top:8px;"></div>
            </div>

            <div class="shipment-meta">
                <div class="meta-item"><div class="meta-label">Receiver</div><div class="meta-value" id="resReceiver"></div></div>
                <div class="meta-item"><div class="meta-label">Drop-off Address</div><div class="meta-value" id="resDropoff"></div></div>
                <div class="meta-item"><div class="meta-label">Pickup Address</div><div class="meta-value" id="resPickup"></div></div>
                <div class="meta-item"><div class="meta-label">Rider</div><div class="meta-value" id="resRider"></div></div>
            </div>

            <div style="margin-top:30px;">
                <div class="meta-label" style="margin-bottom:10px;">Status History</div>
                <div id="resHistory"></div>
            </div>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="<?= BASE_URL ?>/assets/js/tracking.js"></script>
