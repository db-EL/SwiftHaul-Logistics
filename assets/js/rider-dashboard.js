// ============================================================
// Rider Dashboard — Bolt-style live order feed
// ============================================================
(function () {
    const POLL_INTERVAL_MS = 6000;
    const LOCATION_INTERVAL_MS = 15000;

    let knownAvailableIds = new Set();
    let firstPoll = true;
    let watchId = null;
    let lastLocationSentAt = 0;

    const availableList = document.getElementById('availableOrdersList');
    const activeList = document.getElementById('activeDeliveriesList');
    const locationStatusValue = document.getElementById('locationStatusValue');

    function csrfBody(extra) {
        return JSON.stringify({ ...extra, csrf_token: RIDER_CSRF_TOKEN });
    }

    async function postJson(url, body) {
        const res = await fetch(BASE_URL_JS + url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: csrfBody(body),
        });
        return res.json();
    }

    function renderAvailableOrders(orders) {
        if (!orders.length) {
            availableList.innerHTML = '<div class="table-empty">No available orders right now. New orders will appear here automatically.</div>';
            return;
        }

        availableList.innerHTML = orders.map(o => `
            <div class="order-card" data-id="${o.id}">
                <div class="order-card-main">
                    <div class="order-card-route">
                        <div><i class="fa-solid fa-circle" style="color:var(--orange-500);font-size:0.55rem;"></i> ${escapeHtml(o.pickup_address)}</div>
                        <div><i class="fa-solid fa-location-dot" style="color:var(--navy-900);font-size:0.7rem;"></i> ${escapeHtml(o.dropoff_address)}</div>
                    </div>
                    <div class="order-card-meta">
                        <span><i class="fa-solid ${o.service_icon || 'fa-truck'}"></i> ${escapeHtml(o.service_name || 'Delivery')}</span>
                        <span><i class="fa-solid fa-weight-hanging"></i> ${o.weight_kg}kg</span>
                        <span><i class="fa-solid fa-route"></i> ${o.distance_km}km</span>
                        <span><i class="fa-solid fa-clock"></i> ${o.created_at_human}</span>
                    </div>
                </div>
                <div class="order-card-actions">
                    <div class="order-card-fee">${o.fee_formatted}</div>
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-ghost btn-sm reject-btn" data-id="${o.id}">Skip</button>
                        <button class="btn btn-primary btn-sm accept-btn" data-id="${o.id}">Accept</button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function renderActiveDeliveries(deliveries) {
        if (!deliveries.length) {
            activeList.innerHTML = '<div class="table-empty">No active deliveries. Accept an order above to get started.</div>';
            return;
        }

        activeList.innerHTML = deliveries.map(d => `
            <div class="order-card" data-id="${d.id}">
                <div class="order-card-main">
                    <div style="margin-bottom:6px;"><strong>${escapeHtml(d.tracking_id)}</strong> <span class="status-badge ${statusBadgeClass(d.current_status)}">${d.current_status}</span></div>
                    <div class="order-card-route">
                        <div><i class="fa-solid fa-circle" style="color:var(--orange-500);font-size:0.55rem;"></i> ${escapeHtml(d.pickup_address)}</div>
                        <div><i class="fa-solid fa-location-dot" style="color:var(--navy-900);font-size:0.7rem;"></i> ${escapeHtml(d.dropoff_address)}</div>
                    </div>
                    <div class="order-card-meta">
                        <span><i class="fa-solid fa-user"></i> ${escapeHtml(d.receiver_name)} (${escapeHtml(d.receiver_phone)})</span>
                    </div>
                </div>
                <div class="order-card-actions">
                    <div class="order-card-fee">${d.fee_formatted}</div>
                    <button class="btn btn-primary btn-sm update-status-btn" data-id="${d.id}" data-current="${d.current_status}">Update Status</button>
                </div>
            </div>
        `).join('');
    }

    function statusBadgeClass(status) {
        return 'st-' + status.toLowerCase().replace(/\s+/g, '');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    async function poll() {
        try {
            const res = await fetch(BASE_URL_JS + '/api/rider_dashboard_data.php');
            const data = await res.json();
            if (!data.success) return;

            // Notify (styled toast, not a native browser notification) when a
            // genuinely new order shows up in the pool.
            const currentIds = new Set(data.available_orders.map(o => o.id));
            if (!firstPoll) {
                for (const id of currentIds) {
                    if (!knownAvailableIds.has(id)) {
                        showToast('New delivery request available nearby!', 'order');
                        break; // one toast per poll cycle is enough even if several arrived at once
                    }
                }
            }
            knownAvailableIds = currentIds;
            firstPoll = false;

            renderAvailableOrders(data.available_orders);
            renderActiveDeliveries(data.active_deliveries);
        } catch (e) {
            // Silent fail on a single poll — next interval will retry.
        }
    }

    // ---------- Accept / Reject (event delegation, since cards are re-rendered) ----------
    availableList.addEventListener('click', async (e) => {
        const acceptBtn = e.target.closest('.accept-btn');
        const rejectBtn = e.target.closest('.reject-btn');

        if (acceptBtn) {
            acceptBtn.disabled = true;
            const result = await postJson('/api/rider_claim_order.php', { shipment_id: acceptBtn.dataset.id });
            showToastFromResult(result);
            poll();
        } else if (rejectBtn) {
            rejectBtn.disabled = true;
            const result = await postJson('/api/rider_reject_order.php', { shipment_id: rejectBtn.dataset.id });
            showToastFromResult(result, 'info');
            poll();
        }
    });

    // ---------- Delivery status update modal ----------
    const statusModal = document.getElementById('statusModal');
    const statusForm = document.getElementById('statusUpdateForm');
    const statusShipmentIdInput = document.getElementById('statusShipmentId');
    const statusNewStatusSelect = document.getElementById('statusNewStatus');
    const otpFieldGroup = document.getElementById('otpFieldGroup');

    const STATUS_FLOW = ['Rider Assigned', 'Picked Up', 'In Transit', 'Out for Delivery', 'Delivered'];

    activeList.addEventListener('click', (e) => {
        const btn = e.target.closest('.update-status-btn');
        if (!btn) return;

        const currentStatus = btn.dataset.current;
        const currentIndex = STATUS_FLOW.indexOf(currentStatus);
        const nextOptions = STATUS_FLOW.slice(Math.max(currentIndex, 0) + 1);

        statusShipmentIdInput.value = btn.dataset.id;
        statusNewStatusSelect.innerHTML = nextOptions.map(s => `<option value="${s}">${s}</option>`).join('');
        toggleOtpField();
        statusModal.classList.add('open');
    });

    statusNewStatusSelect?.addEventListener('change', toggleOtpField);
    function toggleOtpField() {
        otpFieldGroup.style.display = statusNewStatusSelect.value === 'Delivered' ? 'block' : 'none';
        if (statusNewStatusSelect.value === 'Delivered') updateConfirmationMethodView();
    }

    function updateConfirmationMethodView() {
        const method = statusForm.querySelector('input[name="confirmation_method"]:checked')?.value || 'otp';
        document.getElementById('otpMethodFields').style.display = method === 'otp' ? 'block' : 'none';
        document.getElementById('signatureMethodFields').style.display = method === 'signature' ? 'block' : 'none';
    }
    statusForm.querySelectorAll('input[name="confirmation_method"]').forEach(radio => {
        radio.addEventListener('change', updateConfirmationMethodView);
    });

    statusForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = statusForm.querySelector('button[type="submit"]');

        const isDelivering = statusNewStatusSelect.value === 'Delivered';
        const method = statusForm.querySelector('input[name="confirmation_method"]:checked')?.value || 'otp';

        let payload = {
            shipment_id: statusShipmentIdInput.value,
            new_status: statusNewStatusSelect.value,
        };

        if (isDelivering && method === 'signature') {
            if (!window.signaturePad || window.signaturePad.isEmpty()) {
                showToast('Please have the receiver sign before submitting.', 'error');
                return;
            }
            payload.signature_data = window.signaturePad.toDataURL();
        } else if (isDelivering) {
            payload.delivery_otp = document.getElementById('statusOtpInput').value;
        }

        submitBtn.disabled = true;
        const result = await postJson('/api/rider_update_delivery_status.php', payload);

        showToastFromResult(result);
        submitBtn.disabled = false;

        if (result.success) {
            statusModal.classList.remove('open');
            window.signaturePad?.clear();
            poll();
        }
    });

    // ---------- Online/Offline toggle ----------
    const toggleBtn = document.getElementById('toggleOnlineBtn');
    toggleBtn?.addEventListener('click', async () => {
        toggleBtn.disabled = true;
        const result = await postJson('/api/rider_toggle_status.php', {});
        showToastFromResult(result, 'success');
        if (result.success) {
            setTimeout(() => window.location.reload(), 900);
        } else {
            toggleBtn.disabled = false;
        }
    });

    // ---------- Live location broadcasting ----------
    function startLocationSharing() {
        if (!('geolocation' in navigator)) {
            if (locationStatusValue) locationStatusValue.textContent = 'Unsupported';
            return;
        }

        watchId = navigator.geolocation.watchPosition(
            (position) => {
                const now = Date.now();
                if (locationStatusValue) locationStatusValue.textContent = 'Sharing';
                if (now - lastLocationSentAt < LOCATION_INTERVAL_MS) return;
                lastLocationSentAt = now;

                postJson('/api/rider_location_update.php', {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                }).catch(() => {});
            },
            () => {
                if (locationStatusValue) locationStatusValue.textContent = 'Permission denied';
            },
            { enableHighAccuracy: true, maximumAge: 10000, timeout: 15000 }
        );
    }

    startLocationSharing();
    poll();
    setInterval(poll, POLL_INTERVAL_MS);
})();
