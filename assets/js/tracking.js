// Package Tracking — AJAX lookup (no page reload) + live rider map

(function () {
    const input = document.getElementById('trackingInput');
    const btn = document.getElementById('trackBtn');
    const btnText = document.getElementById('trackBtnText');
    const spinner = document.getElementById('trackSpinner');
    const messageBox = document.getElementById('trackMessage');
    const resultBox = document.getElementById('trackerResult');
    const liveMapWrap = document.getElementById('liveMapWrap');

    const STATUS_ORDER = ['Order Placed', 'Rider Assigned', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered'];
    const AUTO_POLL_MS = 10000;

    let map = null;
    let marker = null;
    let autoPollTimer = null;

    function statusClass(status) {
        return 'st-' + status.toLowerCase().replace(/\s+/g, '');
    }

    function setLoading(loading) {
        btn.disabled = loading;
        btnText.style.display = loading ? 'none' : 'inline-flex';
        spinner.style.display = loading ? 'inline-block' : 'none';
    }

    async function trackPackage(isBackgroundRefresh) {
        const trackingId = input.value.trim();
        if (!isBackgroundRefresh) messageBox.innerHTML = '';

        if (!trackingId) {
            messageBox.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Please enter a tracking ID.</div>';
            return;
        }

        if (!isBackgroundRefresh) {
            setLoading(true);
            resultBox.style.display = 'none';
        }

        try {
            const res = await fetch(BASE_URL_JS + '/api/track.php?tracking_id=' + encodeURIComponent(trackingId));
            const data = await res.json();

            if (!data.success) {
                if (!isBackgroundRefresh) {
                    messageBox.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> ' + data.message + '</div>';
                }
                stopAutoPoll();
                return;
            }

            renderResult(data.shipment);

            if (data.shipment.is_active) startAutoPoll();
            else stopAutoPoll();
        } catch (err) {
            if (!isBackgroundRefresh) {
                messageBox.innerHTML = '<div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> Something went wrong. Please try again.</div>';
            }
        } finally {
            if (!isBackgroundRefresh) setLoading(false);
        }
    }

    function startAutoPoll() {
        stopAutoPoll();
        autoPollTimer = setInterval(() => trackPackage(true), AUTO_POLL_MS);
    }
    function stopAutoPoll() {
        if (autoPollTimer) clearInterval(autoPollTimer);
        autoPollTimer = null;
    }

    function renderResult(shipment) {
        document.getElementById('resTrackingId').textContent = shipment.tracking_id;

        const badge = document.getElementById('resStatusBadge');
        badge.textContent = shipment.current_status;
        badge.className = 'status-badge ' + statusClass(shipment.current_status);

        document.getElementById('resReceiver').textContent = shipment.receiver_name + ' (' + shipment.receiver_phone + ')';
        document.getElementById('resDropoff').textContent = shipment.dropoff_address;
        document.getElementById('resPickup').textContent = shipment.pickup_address;
        document.getElementById('resRider').textContent = shipment.rider_name || 'Not yet assigned';

        // Progress tracker
        const currentIndex = STATUS_ORDER.indexOf(shipment.current_status);
        document.querySelectorAll('.progress-step').forEach((step, i) => {
            step.classList.remove('done', 'active');
            if (shipment.current_status === 'Cancelled') return;
            if (i < currentIndex) step.classList.add('done');
            else if (i === currentIndex) step.classList.add('active');
        });

        const percent = shipment.current_status === 'Cancelled' ? 0 : (currentIndex / (STATUS_ORDER.length - 1)) * 100;
        document.getElementById('progressFill').style.width = percent + '%';

        // Live rider location map
        if (shipment.rider_location && window.L) {
            liveMapWrap.style.display = 'block';
            const lat = shipment.rider_location.lat;
            const lng = shipment.rider_location.lng;

            if (!map) {
                map = L.map('liveMap').setView([lat, lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; OpenStreetMap contributors',
                    maxZoom: 19,
                }).addTo(map);
                const riderIcon = L.divIcon({
                    className: 'rider-map-marker',
                    html: '<i class="fa-solid fa-motorcycle"></i>',
                    iconSize: [34, 34],
                });
                marker = L.marker([lat, lng], { icon: riderIcon }).addTo(map);
            } else {
                marker.setLatLng([lat, lng]);
                map.panTo([lat, lng]);
            }

            const updatedDate = new Date(shipment.rider_location.updated_at.replace(' ', 'T'));
            document.getElementById('liveMapUpdated').textContent = 'Last updated ' + updatedDate.toLocaleTimeString();

            // Leaflet needs a nudge to size correctly if the container was
            // hidden (display:none) when first created.
            setTimeout(() => map.invalidateSize(), 200);
        } else {
            liveMapWrap.style.display = 'none';
        }

        // History
        const historyBox = document.getElementById('resHistory');
        historyBox.innerHTML = shipment.history.map(h => `
            <div style="display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border);font-size:0.88rem;">
                <span><strong>${h.status}</strong>${h.note ? ' — ' + h.note : ''}</span>
                <span style="color:var(--text-muted);">${h.updated_at}</span>
            </div>
        `).join('');

        resultBox.style.display = 'block';
        resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    btn.addEventListener('click', () => trackPackage(false));
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter') trackPackage(false); });

    // Auto-track if ?id= is present in URL
    const params = new URLSearchParams(window.location.search);
    if (params.get('id')) {
        input.value = params.get('id');
        trackPackage(false);
    }
})();
