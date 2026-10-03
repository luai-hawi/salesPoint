(function () {
    function copyButtons() {
        document.addEventListener('click', async function (event) {
            const button = event.target.closest('[data-copy-target]');
            if (!button) return;

            const input = document.getElementById(button.dataset.copyTarget);
            if (!input) return;

            try {
                await navigator.clipboard.writeText(input.value);
            } catch (error) {
                input.select();
                document.execCommand('copy');
            }

            if (window.SP) {
                SP.toast((window.hrSettingsTranslations && window.hrSettingsTranslations.copySuccess) || '', 'success');
            }
        });
    }

    function initMap() {
        const mapEl = document.getElementById('hr-settings-map');
        if (!mapEl) return;

        const latInput = document.getElementById('location-latitude');
        const lngInput = document.getElementById('location-longitude');
        const radiusInput = document.getElementById('location-radius');
        const radiusValue = document.getElementById('location-radius-value');
        const useLocation = document.getElementById('use-current-location');

        if (radiusInput && radiusValue) {
            radiusInput.addEventListener('input', function () {
                radiusValue.textContent = radiusInput.value;
            });
        }

        if (!window.L) return;

        const startLat = parseFloat(latInput && latInput.value ? latInput.value : mapEl.dataset.lat);
        const startLng = parseFloat(lngInput && lngInput.value ? lngInput.value : mapEl.dataset.lng);
        const map = L.map(mapEl).setView([startLat, startLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(map);

        const marker = L.marker([startLat, startLng], {draggable: true}).addTo(map);
        const circle = L.circle([startLat, startLng], {
            radius: parseInt(radiusInput && radiusInput.value ? radiusInput.value : '100', 10),
        }).addTo(map);

        function sync(lat, lng) {
            if (latInput) latInput.value = lat.toFixed(7);
            if (lngInput) lngInput.value = lng.toFixed(7);
            marker.setLatLng([lat, lng]);
            circle.setLatLng([lat, lng]);
        }

        marker.on('dragend', function () {
            const pos = marker.getLatLng();
            sync(pos.lat, pos.lng);
        });

        map.on('click', function (event) {
            sync(event.latlng.lat, event.latlng.lng);
        });

        if (radiusInput) {
            radiusInput.addEventListener('input', function () {
                circle.setRadius(parseInt(radiusInput.value, 10));
            });
        }

        if (useLocation && navigator.geolocation) {
            useLocation.addEventListener('click', function () {
                navigator.geolocation.getCurrentPosition(function (position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    sync(lat, lng);
                    map.setView([lat, lng], 16);
                });
            });
        }
    }

    copyButtons();
    initMap();
})();
