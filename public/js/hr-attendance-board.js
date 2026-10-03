(function () {
    const mapEl = document.getElementById('attendance-day-map');
    if (!mapEl || !window.L) return;

    let points = [];

    try {
        points = JSON.parse(mapEl.dataset.points || '[]');
    } catch (error) {
        points = [];
    }

    const center = points.length ? [points[0].lat, points[0].lng] : [31.5017, 34.4668];
    const map = L.map(mapEl).setView(center, points.length ? 14 : 8);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap',
    }).addTo(map);

    if (!points.length) return;

    const bounds = [];

    points.forEach(function (point) {
        bounds.push([point.lat, point.lng]);
        var content = document.createElement('div');
        content.textContent = point.label || '';
        L.marker([point.lat, point.lng]).addTo(map).bindPopup(content);
    });

    if (bounds.length > 1) {
        map.fitBounds(bounds, {padding: [30, 30]});
    }
})();
