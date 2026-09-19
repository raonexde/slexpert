(function () {
    'use strict';
    var data = window.DESTINATION_MAP_DATA;
    var mapElement = document.querySelector('[data-destination-map]');
    var statusElement = document.querySelector('[data-destination-map-status]');
    var latitudeInput = document.querySelector('input[name="latitude"]');
    var longitudeInput = document.querySelector('input[name="longitude"]');
    var placeIdInput = document.querySelector('input[name="google_place_id"]');
    if (!data || !mapElement || !latitudeInput || !longitudeInput) return;

    var map = null;
    var marker = null;
    var AdvancedMarkerElement = null;

    function showStatus(message, error) {
        statusElement.textContent = message || '';
        statusElement.classList.toggle('visible', Boolean(message));
        statusElement.classList.toggle('error', Boolean(error));
    }
    function currentPosition() {
        var lat = Number(latitudeInput.value);
        var lng = Number(longitudeInput.value);
        if (latitudeInput.value === '' || longitudeInput.value === '' || !Number.isFinite(lat) || !Number.isFinite(lng)) return null;
        if (lat < -90 || lat > 90 || lng < -180 || lng > 180) return null;
        return { lat: lat, lng: lng };
    }
    function writePosition(position, clearPlaceId) {
        latitudeInput.value = Number(position.lat).toFixed(7);
        longitudeInput.value = Number(position.lng).toFixed(7);
        if (clearPlaceId && placeIdInput) placeIdInput.value = '';
    }
    function placeMarker(position, centreMap, clearPlaceId) {
        if (!map || !AdvancedMarkerElement) return;
        writePosition(position, clearPlaceId);
        if (!marker) {
            marker = new AdvancedMarkerElement({ map: map, position: position, title: 'Reiseziel', gmpDraggable: true });
            if (marker.addListener) marker.addListener('dragend', function (event) {
                if (event.latLng) placeMarker(event.latLng.toJSON(), false, true);
            });
            if (marker.addEventListener) marker.addEventListener('gmp-dragend', function (event) {
                var point = event.latLng || (event.detail && event.detail.latLng);
                if (point) placeMarker(typeof point.toJSON === 'function' ? point.toJSON() : point, false, true);
            });
        } else marker.position = position;
        if (centreMap) {
            map.panTo(position);
            if (map.getZoom() < 10) map.setZoom(11);
        }
    }
    async function initialise() {
        try {
            var libraries = await Promise.all([
                google.maps.importLibrary('maps'),
                google.maps.importLibrary('marker')
            ]);
            AdvancedMarkerElement = libraries[1].AdvancedMarkerElement;
            var initial = currentPosition();
            map = new libraries[0].Map(mapElement, {
                center: initial || { lat: 7.8731, lng: 80.7718 },
                zoom: initial ? 11 : 7,
                mapId: data.mapId || 'DEMO_MAP_ID',
                mapTypeControl: true,
                streetViewControl: false
            });
            map.addListener('click', function (event) {
                if (event.latLng) placeMarker(event.latLng.toJSON(), false, true);
            });
            if (initial) placeMarker(initial, false, false);
            showStatus('', false);
        } catch (error) {
            console.error('Destination map error:', error);
            showStatus('Google Maps konnte nicht geladen werden. Koordinaten bitte manuell eingeben.', true);
        }
    }
    function load() {
        if (!data.apiKey) return;
        window.__ceylonDestinationMapReady = function () { initialise(); };
        var params = new URLSearchParams({ key: data.apiKey, v: 'weekly', loading: 'async', callback: '__ceylonDestinationMapReady', language: 'de', region: 'LK' });
        var script = document.createElement('script');
        script.src = 'https://maps.googleapis.com/maps/api/js?' + params.toString();
        script.async = true;
        script.onerror = function () { showStatus('Google Maps konnte nicht geladen werden. Koordinaten bitte manuell eingeben.', true); };
        document.head.appendChild(script);
    }
    function updateFromInputs() {
        var position = currentPosition();
        if (position && map) placeMarker(position, true, false);
    }
    latitudeInput.addEventListener('change', updateFromInputs);
    longitudeInput.addEventListener('change', updateFromInputs);
    load();
})();
