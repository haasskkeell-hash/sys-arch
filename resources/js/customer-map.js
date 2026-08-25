const leafletVersion = '1.9.4';
const customerMapSelector = '[data-app-customer-map]';
const customerBookingMapSelector = '[data-app-customer-booking-map]';
const mobileMapShellSelector = '[data-app-customer-mobile-shell], [data-app-technician-mobile-shell]';
const customerMobileShellSelector = '[data-app-customer-mobile-shell]';
const technicianMapSelector = '[data-app-technician-mobile-map]';
const mobileHomeSheetSelector = '[data-app-mobile-home-sheet]';
const mobileHomeSheetHandleSelector = '[data-app-mobile-home-sheet-handle]';
// ponytail: public OSRM keeps the local MVP dependency-free; self-host or use a quota-managed router before production traffic.
const roadRoutingEndpoint = 'https://router.project-osrm.org/route/v1/driving';
const mapInstances = new WeakMap();
const mobileHomeSheetStates = new WeakMap();
let leafletPromise = null;
let latestCustomerTracking = null;

function loadLeaflet() {
    if (window.L) {
        return Promise.resolve(window.L);
    }

    if (leafletPromise) {
        return leafletPromise;
    }

    leafletPromise = new Promise((resolve, reject) => {
        if (!document.querySelector('[data-fixtrack-leaflet-style]')) {
            const style = document.createElement('link');
            style.rel = 'stylesheet';
            style.href = `https://unpkg.com/leaflet@${leafletVersion}/dist/leaflet.css`;
            style.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
            style.crossOrigin = '';
            style.dataset.fixtrackLeafletStyle = '';
            document.head.append(style);
        }

        const script = document.createElement('script');
        script.src = `https://unpkg.com/leaflet@${leafletVersion}/dist/leaflet.js`;
        script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
        script.crossOrigin = '';
        script.async = true;
        script.dataset.fixtrackLeafletScript = '';
        script.addEventListener('load', () => window.L ? resolve(window.L) : reject(new Error('Leaflet did not load.')));
        script.addEventListener('error', () => reject(new Error('Leaflet could not be loaded.')));
        document.head.append(script);
    });

    return leafletPromise;
}

function numericValue(value, fallback) {
    const number = Number(value);

    return Number.isFinite(number) ? number : fallback;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function markerIcon(leaflet, type, marker = {}) {
    const markerClass = type === 'technician'
        ? 'customer-map-marker--technician'
        : type === 'active'
            ? 'customer-map-marker--active'
            : 'customer-map-marker--booking';
    const markerGlyph = type === 'technician'
        ? '<path d="M24.6 11.4a4.6 4.6 0 0 1-5.99 5.99L12 24l-2.25-2.25 6.61-6.61a4.6 4.6 0 0 1 5.99-5.99l-2.5 2.5 2.25 2.25 2.5-2.5Z"/><circle cx="9.25" cy="25.75" r="1.25"/>'
        : type === 'active'
            ? '<path d="m11.5 19 8.5-6.75 8.5 6.75"/><path d="M14 17.5v8h12v-8"/><path d="M18.25 25.5v-4h3.5v4"/>'
            : '<path d="M15.5 12.5h9a2 2 0 0 1 2 2v11h-11z"/><path d="M17.5 12.5v-1.25a1.5 1.5 0 0 1 1.5-1.5h1.5a1.5 1.5 0 0 1 1.5 1.5v1.25"/><path d="M12 16.5h1.5M12 20h1.5M12 23.5h1.5"/>';
    const technicianStatus = type === 'technician'
        ? '<span class="customer-map-marker__status" aria-hidden="true"></span>'
        : '';
    const avatar = typeof marker.avatar === 'string' ? marker.avatar.trim() : '';
    const initials = typeof marker.initials === 'string' ? marker.initials.trim() : '';
    const markerPortrait = avatar
        ? `<span class="customer-map-marker__portrait"><img src="${escapeHtml(avatar)}" alt="" class="customer-map-marker__portrait-image"></span>`
        : initials
            ? `<span class="customer-map-marker__portrait customer-map-marker__portrait--initials"><span class="customer-map-marker__initials">${escapeHtml(initials)}</span></span>`
            : '';
    const portraitClass = markerPortrait ? ' customer-map-marker--has-portrait' : '';

    return leaflet.divIcon({
        className: 'customer-map-marker-wrap',
        html: `<span class="customer-map-marker ${markerClass}${portraitClass}" aria-hidden="true"><svg class="customer-map-marker__pin" viewBox="0 0 40 48"><path class="customer-map-marker__pin-shape" d="M20 46S4 31.2 4 18C4 9.72 11.16 3 20 3s16 6.72 16 15c0 13.2-16 28-16 28Z"/><g class="customer-map-marker__icon" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25">${markerGlyph}</g></svg>${markerPortrait}${technicianStatus}</span>`,
        iconSize: [40, 48],
        iconAnchor: [20, 45],
        popupAnchor: [0, -43],
    });
}

function locationPinIcon(leaflet) {
    return leaflet.divIcon({
        className: 'customer-map-location-marker-wrap',
        html: '<svg class="customer-map-location-marker" viewBox="0 0 34 42" aria-hidden="true"><path d="M17 40S3 27.4 3 16a14 14 0 1 1 28 0c0 11.4-14 24-14 24Z" fill="#ef4444" stroke="#fff" stroke-width="3"/><circle cx="17" cy="16" r="5.5" fill="#fff"/></svg>',
        iconSize: [34, 42],
        iconAnchor: [17, 40],
        popupAnchor: [0, -40],
    });
}

function userIcon(leaflet) {
    return leaflet.divIcon({
        className: 'customer-map-user-marker-wrap',
        html: '<span class="customer-map-user-marker" aria-hidden="true"><span></span></span>',
        iconSize: [30, 30],
        iconAnchor: [15, 15],
    });
}

function readMarkers(wrapper, mapElement) {
    try {
        const source = wrapper.dataset.mapMarkers !== undefined ? wrapper.dataset.mapMarkers : mapElement.dataset.mapMarkers;
        const markers = JSON.parse(source || '[]');

        return Array.isArray(markers) ? markers : [];
    } catch {
        return [];
    }
}

function readCenter(wrapper, mapElement) {
    const source = wrapper.dataset.mapCenterLat !== undefined && wrapper.dataset.mapCenterLng !== undefined
        ? wrapper.dataset
        : mapElement.dataset;

    return [
        numericValue(source.mapCenterLat, 14.5995),
        numericValue(source.mapCenterLng, 120.9842),
    ];
}

function syncMapCenter(wrapper, mapElement, state) {
    if (wrapper.dataset.mapCenterLat === undefined || wrapper.dataset.mapCenterLng === undefined) {
        return;
    }

    const center = readCenter(wrapper, mapElement);
    state.map.setView(center, 14, { animate: false });
    state.draggableMarker?.setLatLng(center);
}

function trackingCoordinates(point) {
    const latitude = Number(point?.latitude);
    const longitude = Number(point?.longitude);

    return Number.isFinite(latitude) && Number.isFinite(longitude) ? [latitude, longitude] : null;
}

function routeSignature(origin, destination) {
    return [...origin, ...destination].map((coordinate) => coordinate.toFixed(4)).join(',');
}

function resetTrackingRoute(state, signature = null) {
    state.routeController?.abort();
    state.routeController = null;
    state.routeSignature = signature;
    state.routeRequestId += 1;
    if (state.trackingLine) {
        state.trackingLayer.removeLayer(state.trackingLine);
    }
    state.trackingLine = null;
}

async function drawRoadRoute(state, origin, destination) {
    if (state.destroyed || !state.mapElement.isConnected || !origin || !destination) {
        return;
    }

    const signature = routeSignature(origin, destination);

    if (signature === state.routeSignature) {
        return;
    }

    resetTrackingRoute(state, signature);

    const controller = new AbortController();
    const requestId = state.routeRequestId;
    state.routeController = controller;
    let timedOut = false;
    const timeoutId = window.setTimeout(() => {
        timedOut = true;
        controller.abort();
    }, 8000);
    const coordinates = [origin, destination]
        .map(([latitude, longitude]) => `${longitude},${latitude}`)
        .join(';');
    const url = `${roadRoutingEndpoint}/${coordinates}?overview=full&geometries=geojson&steps=false`;

    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });

        if (!response.ok) {
            throw new Error(`Road route failed with ${response.status}`);
        }

        const payload = await response.json();
        const routeCoordinates = payload.routes?.[0]?.geometry?.coordinates;
        const routePoints = Array.isArray(routeCoordinates)
            ? routeCoordinates
                .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
                .map(([longitude, latitude]) => [Number(latitude), Number(longitude)])
                .filter(([latitude, longitude]) => Number.isFinite(latitude) && Number.isFinite(longitude))
            : [];

        if (state.destroyed || !state.mapElement.isConnected || requestId !== state.routeRequestId || signature !== state.routeSignature) {
            return;
        }

        if (routePoints.length < 2) {
            throw new Error('Road route returned no usable path.');
        }

        const routeCasing = state.leaflet.polyline(routePoints, {
            color: '#ffffff',
            opacity: 0.9,
            weight: 9,
            lineCap: 'round',
            lineJoin: 'round',
        });
        const routeLine = state.leaflet.polyline(routePoints, {
            color: '#7c3aed',
            opacity: 0.95,
            weight: 5,
            lineCap: 'round',
            lineJoin: 'round',
        });

        state.trackingLine = state.leaflet.layerGroup([routeCasing, routeLine]).addTo(state.trackingLayer);

        if (!state.routeHasFit) {
            state.map.fitBounds(state.leaflet.latLngBounds([origin, destination]), {
                padding: [56, 56],
                maxZoom: 15,
            });
            state.routeHasFit = true;
        }
        setMapStatus(state.wrapper, '', false);
    } catch (error) {
        if (timedOut && requestId === state.routeRequestId) {
            const message = 'Route request timed out. Showing the service locations instead.';
            state.routeSignature = null;
            dispatchMapError(state.wrapper, 'map_route_failed', message, error);
            setMapStatus(state.wrapper, message, true, () => updateTrackingRoute(state));
        } else if (error?.name !== 'AbortError' && requestId === state.routeRequestId) {
            const message = 'Route unavailable. Showing the service locations instead.';
            state.routeSignature = null;
            dispatchMapError(state.wrapper, 'map_route_failed', message, error);
            setMapStatus(state.wrapper, message, true, () => updateTrackingRoute(state));
        }
    } finally {
        window.clearTimeout(timeoutId);
        if (requestId === state.routeRequestId) {
            state.routeController = null;
        }
    }
}

function updateTrackingRoute(state) {
    if (state.destroyed) {
        return;
    }

    const technicianPosition = trackingCoordinates(state.tracking?.technician)
        ?? (state.isTechnicianMap ? state.userPosition ?? state.routeOrigin : null);
    const customerPosition = trackingCoordinates(state.tracking?.customer)
        ?? (state.isTechnicianMap ? state.routeDestination : state.userPosition);

    if (!customerPosition || !technicianPosition) {
        return;
    }

    drawRoadRoute(state, technicianPosition, customerPosition);
}

function applyTrackingState(state, tracking) {
    if (state.tracking?.booking_id !== tracking?.booking_id) {
        state.routeHasFit = false;
    }

    state.tracking = tracking;
    state.trackingLayer.clearLayers();
    resetTrackingRoute(state);
    state.trackingMarker = null;

    if (!tracking || !['assigned', 'en_route', 'in_progress'].includes(tracking.status)) {
        state.routeHasFit = false;
        return;
    }

    const technicianPosition = trackingCoordinates(tracking.technician);

    if (!technicianPosition) {
        return;
    }

    state.trackingMarker = state.leaflet.marker(technicianPosition, {
        icon: markerIcon(state.leaflet, 'technician', tracking?.technician),
        zIndexOffset: 700,
    }).addTo(state.trackingLayer);
    state.trackingMarker.bindPopup(`<strong>${escapeHtml(tracking.technician?.name || 'Your technician')}</strong>`);

    updateTrackingRoute(state);
}

function syncMarkers(wrapper, mapElement, state, fitMap = false) {
    const markers = readMarkers(wrapper, mapElement).filter((marker) => Number.isFinite(Number(marker.latitude)) && Number.isFinite(Number(marker.longitude)));
    const signature = JSON.stringify(markers);

    if (signature === state.markersSignature) {
        return;
    }

    const routeOrigin = state.isTechnicianMap
        ? trackingCoordinates(markers.find((marker) => marker.type === 'technician'))
        : null;
    const routeDestination = state.isTechnicianMap
        ? trackingCoordinates(markers.find((marker) => marker.type === 'active'))
        : null;

    if (JSON.stringify([state.routeOrigin, state.routeDestination]) !== JSON.stringify([routeOrigin, routeDestination])) {
        state.routeHasFit = false;
    }

    state.routeOrigin = routeOrigin;
    state.routeDestination = routeDestination;

    state.markerLayer.clearLayers();
    state.trackingLayer.clearLayers();
    resetTrackingRoute(state);
    state.trackingMarker = null;
    markers.forEach((marker) => {
        const layer = marker.type === 'technician' ? state.trackingLayer : state.markerLayer;
        const leafletMarker = state.leaflet.marker([Number(marker.latitude), Number(marker.longitude)], {
            icon: markerIcon(state.leaflet, marker.type, marker),
            zIndexOffset: marker.type === 'technician' ? 700 : 0,
        }).addTo(layer);
        const title = marker.type === 'technician' ? marker.title || 'Available technician' : marker.title || 'Service location';
        const address = marker.address || '';

        leafletMarker.bindPopup(`<strong>${escapeHtml(title)}</strong><br><span>${escapeHtml(address)}</span>`);

        if (marker.type === 'technician') {
            state.trackingMarker = leafletMarker;
        }
    });

    state.markersSignature = signature;

    if (state.tracking) {
        applyTrackingState(state, state.tracking);
    } else {
        updateTrackingRoute(state);
    }

    if (fitMap && markers.length > 0) {
        state.map.fitBounds(state.leaflet.latLngBounds(markers.map((marker) => [Number(marker.latitude), Number(marker.longitude)])), {
            padding: [44, 44],
            maxZoom: 15,
        });
    }
}

function updateCoordinateInput(wrapper, selector, value) {
    const input = wrapper.querySelector(selector);

    if (!input) {
        return;
    }

    input.value = value.toFixed(7);
    input.dispatchEvent(new Event('input', { bubbles: true }));
}

function setMapStatus(wrapper, message, visible = true, retry = null) {
    const status = wrapper.querySelector('[data-app-map-status]');

    if (!status) {
        return;
    }

    status.replaceChildren();
    if (message !== '') {
        status.append(document.createTextNode(message));
    }
    if (typeof retry === 'function') {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = 'Retry';
        button.className = 'pointer-events-auto ml-2 rounded-lg border border-zinc-300 bg-white px-3 py-1 text-xs font-semibold text-zinc-800 shadow-sm dark:border-white/10 dark:bg-zinc-950 dark:text-white';
        button.addEventListener('click', retry, { once: true });
        status.append(button);
    }
    status.hidden = !visible;
}

function dispatchMapError(wrapper, event, message, error = null) {
    const detail = {
        event,
        message,
        map: wrapper.dataset.appCustomerMap || wrapper.dataset.appCustomerMobileShell || null,
        error: error instanceof Error ? error.message : null,
        timestamp: new Date().toISOString(),
    };

    document.dispatchEvent(new CustomEvent('fixtrack:map-error', { detail }));
    console.warn('[FixTrack]', detail);
}

function locationErrorMessage(error) {
    if (error?.code === 1) {
        return 'Location access is blocked. Allow it in your browser, then try again.';
    }

    if (error?.code === 2) {
        return 'Your location could not be determined. Check your device settings and try again.';
    }

    if (error?.code === 3) {
        return 'Location request timed out. Try again.';
    }

    return 'Unable to find your location. Try again.';
}

function destroyMap(mapElement) {
    const state = mapInstances.get(mapElement);

    if (!state) {
        return;
    }

    state.destroyed = true;
    state.locationButton?.removeEventListener('click', state.onLocate);

    if (state.watchId !== null && 'geolocation' in navigator) {
        navigator.geolocation.clearWatch(state.watchId);
    }

    if (state.reverseGeocodeTimer) {
        window.clearTimeout(state.reverseGeocodeTimer);
    }

    resetTrackingRoute(state);
    state.draggableMarker?.off('dragend', state.onMarkerDragEnd);
    state.map.stop();
    state.map.remove();
    mapInstances.delete(mapElement);
}

function destroyMapsInside(element) {
    if (element.matches?.(customerMapSelector)) {
        destroyMap(element);
    }

    element.querySelectorAll?.(customerMapSelector).forEach(destroyMap);
}

function scheduleMobileHomeSheetMapRefresh(sheet, state) {
    if (state.mapRefreshFrame) {
        window.cancelAnimationFrame(state.mapRefreshFrame);
    }

    state.mapRefreshFrame = window.requestAnimationFrame(() => {
        state.mapRefreshFrame = null;
        const mapElement = sheet.closest(mobileMapShellSelector)?.querySelector('[data-app-customer-map]');
        const mapState = mapElement ? mapInstances.get(mapElement) : null;

        mapState?.map.invalidateSize({ animate: false });
    });
}

function mobileHomeSheetNaturalHeight(sheet) {
    const currentHeight = sheet.style.height;
    const currentState = sheet.dataset.mobileSheetState;

    sheet.style.height = 'auto';
    sheet.dataset.mobileSheetState = 'normal';
    const naturalHeight = sheet.getBoundingClientRect().height;
    sheet.style.height = currentHeight;

    if (currentState) {
        sheet.dataset.mobileSheetState = currentState;
    } else {
        delete sheet.dataset.mobileSheetState;
    }

    return naturalHeight;
}

function mobileHomeSheetExpandedHeight(sheet, naturalHeight) {
    const shellHeight = sheet.closest(mobileMapShellSelector)?.getBoundingClientRect().height || window.innerHeight;
    const expandedHeight = Math.min(shellHeight - 16, window.innerHeight * 0.72);

    return Math.max(naturalHeight, expandedHeight);
}

function setMobileHomeSheetState(sheet, expanded, state) {
    const naturalHeight = mobileHomeSheetNaturalHeight(sheet);
    const targetHeight = expanded
        ? mobileHomeSheetExpandedHeight(sheet, naturalHeight)
        : naturalHeight;

    sheet.dataset.mobileSheetState = expanded ? 'expanded' : 'normal';
    sheet.style.height = `${targetHeight}px`;
    state.handle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    state.handle.setAttribute('aria-label', expanded ? 'Return home sheet to normal position' : 'Raise home sheet');
    scheduleMobileHomeSheetMapRefresh(sheet, state);

    if (!expanded) {
        window.setTimeout(() => {
            if (sheet.dataset.mobileSheetState === 'normal') {
                sheet.style.height = '';
                scheduleMobileHomeSheetMapRefresh(sheet, state);
            }
        }, 260);
    }
}

function initializeMobileHomeSheet(sheet) {
    const handle = sheet.querySelector(mobileHomeSheetHandleSelector);

    if (!handle) {
        return;
    }

    const existingState = mobileHomeSheetStates.get(sheet);

    if (existingState?.handle === handle) {
        return;
    }

    const state = {
        handle,
        dragging: false,
        ignoreClickUntil: 0,
        mapRefreshFrame: null,
        pointerId: null,
        startHeight: 0,
        startY: 0,
        naturalHeight: 0,
        expandedHeight: 0,
    };
    const toggle = () => setMobileHomeSheetState(sheet, sheet.dataset.mobileSheetState !== 'expanded', state);
    const finishDrag = (event) => {
        if (!state.dragging || event.pointerId !== state.pointerId) {
            return;
        }

        const dragDistance = state.startY - event.clientY;
        const midpoint = state.naturalHeight + (state.expandedHeight - state.naturalHeight) / 2;
        const shouldExpand = sheet.getBoundingClientRect().height >= midpoint || dragDistance > 48;

        state.dragging = false;
        state.ignoreClickUntil = Math.abs(dragDistance) > 6 ? performance.now() + 350 : 0;
        delete sheet.dataset.mobileSheetDragging;
        setMobileHomeSheetState(sheet, shouldExpand, state);

        if (handle.hasPointerCapture?.(event.pointerId)) {
            handle.releasePointerCapture(event.pointerId);
        }

        state.pointerId = null;
    };

    handle.addEventListener('pointerdown', (event) => {
        if (event.pointerType === 'mouse' && event.button !== 0) {
            return;
        }

        state.dragging = true;
        state.pointerId = event.pointerId;
        state.startY = event.clientY;
        state.startHeight = sheet.getBoundingClientRect().height;
        state.naturalHeight = mobileHomeSheetNaturalHeight(sheet);
        state.expandedHeight = mobileHomeSheetExpandedHeight(sheet, state.naturalHeight);
        sheet.dataset.mobileSheetDragging = 'true';
        sheet.style.height = `${state.startHeight}px`;
        handle.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    });
    handle.addEventListener('pointermove', (event) => {
        if (!state.dragging || event.pointerId !== state.pointerId) {
            return;
        }

        const nextHeight = Math.min(
            state.expandedHeight,
            Math.max(state.naturalHeight, state.startHeight + state.startY - event.clientY),
        );

        sheet.style.height = `${nextHeight}px`;
        scheduleMobileHomeSheetMapRefresh(sheet, state);
        event.preventDefault();
    });
    handle.addEventListener('pointerup', finishDrag);
    handle.addEventListener('pointercancel', finishDrag);
    handle.addEventListener('click', () => {
        if (performance.now() < state.ignoreClickUntil) {
            return;
        }

        toggle();
    });
    handle.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') {
            return;
        }

        event.preventDefault();
        state.ignoreClickUntil = performance.now() + 350;
        toggle();
    });

    mobileHomeSheetStates.set(sheet, state);
}

function scanMobileHomeSheets() {
    if (!window.matchMedia('(max-width: 1023px)').matches) {
        return;
    }

    document.querySelectorAll(mobileHomeSheetSelector).forEach(initializeMobileHomeSheet);
}

function initializeMap(wrapper, mapElement, leaflet) {
    if (mapInstances.has(mapElement)) {
        return;
    }

    const center = readCenter(wrapper, mapElement);
    const map = leaflet.map(mapElement, {
        zoomControl: false,
        attributionControl: true,
        scrollWheelZoom: false,
    }).setView(center, 14);

    const tileLayer = leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
    }).addTo(map);
    let tileErrorReported = false;
    tileLayer.on('tileerror', () => {
        if (! tileErrorReported) {
            tileErrorReported = true;
            dispatchMapError(wrapper, 'map_tiles_failed', 'Map tiles are unavailable. Check your connection and try again.');
        }
        setMapStatus(wrapper, 'Map tiles are unavailable. Check your connection and try again.', true, () => {
            setMapStatus(wrapper, 'Retrying map tiles…');
            tileLayer.redraw();
        });
    });
    tileLayer.on('tileload', () => {
        tileErrorReported = false;
    });
    leaflet.control.zoom({ position: 'bottomright' }).addTo(map);

    const markerLayer = leaflet.layerGroup().addTo(map);
    const trackingLayer = leaflet.layerGroup().addTo(map);
    const draggableMarker = mapElement.dataset.mapDraggable === 'true'
        ? leaflet.marker(center, {
            draggable: true,
            icon: locationPinIcon(leaflet),
            zIndexOffset: 600,
        }).addTo(map)
        : null;
    let userMarker = null;
    let centeredOnUser = false;

    const onMarkerDragEnd = () => {
        if (!draggableMarker) {
            return;
        }

        const position = draggableMarker.getLatLng();
        updateCoordinateInput(wrapper, '[data-app-map-latitude]', position.lat);
        updateCoordinateInput(wrapper, '[data-app-map-longitude]', position.lng);
        updateLocationAddress(position.lat, position.lng);
    };

    draggableMarker?.on('dragend', onMarkerDragEnd);

    const state = {
        map,
        mapElement,
        isTechnicianMap: wrapper.matches(technicianMapSelector),
        leaflet,
        wrapper,
        markerLayer,
        trackingLayer,
        markersSignature: null,
        locationButton: null,
        onLocate: null,
        watchId: null,
        draggableMarker,
        onMarkerDragEnd,
        routeController: null,
        routeHasFit: false,
        routeOrigin: null,
        routeDestination: null,
        routeRequestId: 0,
        routeSignature: null,
        tracking: null,
        trackingMarker: null,
        trackingLine: null,
        destroyed: false,
        userPosition: null,
        locationRequestId: 0,
        reverseGeocodeTimer: null,
    };

    mapInstances.set(mapElement, state);
    syncMarkers(wrapper, mapElement, state, true);

    const trackingElement = document.querySelector('[data-realtime-tracking]');

    if (latestCustomerTracking && trackingElement && String(latestCustomerTracking.booking_id) === trackingElement.dataset.realtimeTrackingBookingId) {
        applyTrackingState(state, latestCustomerTracking);
    }

    const updateUserLocation = ({ coords }, updateBookingPin = false, updateAddress = false) => {
        const latitude = Number(coords.latitude);
        const longitude = Number(coords.longitude);

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
            return;
        }

        state.userPosition = [latitude, longitude];

        if (!userMarker) {
            userMarker = leaflet.marker([latitude, longitude], { icon: userIcon(leaflet), zIndexOffset: 500 }).addTo(map);
        } else {
            userMarker.setLatLng([latitude, longitude]);
        }

        if (updateBookingPin && draggableMarker) {
            draggableMarker.setLatLng([latitude, longitude]);
            updateCoordinateInput(wrapper, '[data-app-map-latitude]', latitude);
            updateCoordinateInput(wrapper, '[data-app-map-longitude]', longitude);
        }

        if (!centeredOnUser) {
            map.setView([latitude, longitude], 15, { animate: false });
            centeredOnUser = true;
        }

        updateTrackingRoute(state);
        if (updateAddress) {
            updateLocationAddress(latitude, longitude);
        } else {
            setLocationBusy(false);
            setMapStatus(wrapper, '', false);
        }
    };

    const locationButton = wrapper.querySelector('[data-app-map-locate]');
    const setLocationBusy = (busy) => {
        if (!locationButton) {
            return;
        }

        locationButton.disabled = busy;
        locationButton.setAttribute('aria-busy', busy ? 'true' : 'false');
    };
    const updateLocationAddress = (latitude, longitude) => {
        state.locationRequestId += 1;
        const requestId = state.locationRequestId;
        const root = wrapper.closest('[wire\\:id]');
        const componentId = root?.getAttribute('wire:id');
        const component = componentId ? window.Livewire?.find(componentId) : null;

        if (state.reverseGeocodeTimer) {
            window.clearTimeout(state.reverseGeocodeTimer);
        }

        if (!component || typeof component.updateLocationFromCoordinates !== 'function') {
            setLocationBusy(false);
            setMapStatus(wrapper, 'Address update is unavailable. Enter it manually.');

            return;
        }

        setLocationBusy(true);
        setMapStatus(wrapper, 'Updating address…');
        state.reverseGeocodeTimer = window.setTimeout(() => {
            Promise.resolve(component.updateLocationFromCoordinates(latitude, longitude))
                .then(() => {
                    if (!state.destroyed && requestId === state.locationRequestId) {
                        setMapStatus(wrapper, '', false);
                    }
                })
                .catch((error) => {
                    if (requestId !== state.locationRequestId) {
                        return;
                    }

                    const message = 'Address could not be updated. Enter it manually.';
                    dispatchMapError(wrapper, 'map_reverse_geocode_failed', message, error);
                    setMapStatus(wrapper, message);
                })
                .finally(() => {
                    if (requestId === state.locationRequestId) {
                        setLocationBusy(false);
                    }
                });
        }, 500);
    };
    const handleLocationError = (error) => {
        setLocationBusy(false);
        setMapStatus(wrapper, locationErrorMessage(error));
    };
    const requestLocation = () => {
        if (!('geolocation' in navigator)) {
            setMapStatus(wrapper, 'Location is not available in this browser.');

            return;
        }

        setLocationBusy(true);
        setMapStatus(wrapper, 'Finding your location…');
        navigator.geolocation.getCurrentPosition((position) => updateUserLocation(position, true, true), handleLocationError, {
            enableHighAccuracy: false,
            maximumAge: 30000,
            timeout: 10000,
        });
    };
    const onLocate = () => {
        if (userMarker) {
            const position = userMarker.getLatLng();
            state.userPosition = [position.lat, position.lng];
            map.flyTo(position, 16, { duration: 0.5 });

            if (draggableMarker) {
                draggableMarker.setLatLng(position);
                updateCoordinateInput(wrapper, '[data-app-map-latitude]', position.lat);
                updateCoordinateInput(wrapper, '[data-app-map-longitude]', position.lng);
                updateLocationAddress(position.lat, position.lng);
            }

            updateTrackingRoute(state);
            if (!draggableMarker) {
                setMapStatus(wrapper, '', false);
            }

            return;
        }

        requestLocation();
    };

    locationButton?.addEventListener('click', onLocate);

    const shouldWatchLocation = mapElement.dataset.mapWatchLocation !== 'false'
        && mapElement.dataset.mapAutoLocate !== 'true';
    const watchId = shouldWatchLocation && 'geolocation' in navigator
        ? navigator.geolocation.watchPosition(updateUserLocation, handleLocationError, {
            enableHighAccuracy: false,
            maximumAge: 30000,
            timeout: 10000,
        })
        : null;

    map.whenReady(() => {
        setMapStatus(wrapper, '', false);
        window.setTimeout(() => {
            if (!state.destroyed && mapElement.isConnected) {
                map.invalidateSize();
            }
        }, 0);

        if (mapElement.dataset.mapAutoLocate === 'true') {
            requestLocation();
        }
    });

    state.locationButton = locationButton;
    state.onLocate = onLocate;
    state.watchId = watchId;
}

function initializeCustomerMap(wrapper) {
    const mapElement = wrapper.querySelector(customerMapSelector);

    if (!mapElement || mapElement.dataset.mapFailed === 'true') {
        return;
    }

    const existingMap = mapInstances.get(mapElement);

    if (existingMap) {
        syncMapCenter(wrapper, mapElement, existingMap);
        syncMarkers(wrapper, mapElement, existingMap);

        return;
    }

    setMapStatus(wrapper, 'Loading live map…');

    loadLeaflet()
        .then((leaflet) => initializeMap(wrapper, mapElement, leaflet))
        .catch((error) => {
            mapElement.dataset.mapFailed = 'true';
            dispatchMapError(wrapper, 'map_leaflet_load_failed', 'Map is unavailable. Check your connection and try again.', error);
            setMapStatus(wrapper, 'Map is unavailable. Check your connection and try again.', true, () => {
                mapElement.dataset.mapFailed = 'false';
                leafletPromise = null;
                initializeCustomerMap(wrapper);
            });
        });
}

function scanCustomerMaps() {
    scanMobileHomeSheets();
    const selectors = [customerBookingMapSelector];

    if (window.matchMedia('(max-width: 1023px)').matches) {
        selectors.unshift(`${mobileMapShellSelector} [data-app-customer-mobile-map]`);
    }

    document.querySelectorAll(selectors.join(', ')).forEach(initializeCustomerMap);
}

document.addEventListener('DOMContentLoaded', scanCustomerMaps);
document.addEventListener('livewire:initialized', () => {
    scanCustomerMaps();

    if (!window.__fixtrackCustomerMapHook) {
        Livewire.hook('morph.removing', ({ el }) => destroyMapsInside(el));
        Livewire.hook('morphed', () => window.setTimeout(scanCustomerMaps, 0));
        window.__fixtrackCustomerMapHook = true;
    }
});
document.addEventListener('livewire:navigated', scanCustomerMaps);
document.addEventListener('fixtrack:customer-tracking', (event) => {
    latestCustomerTracking = event.detail ?? null;

    document.querySelectorAll(`${customerMobileShellSelector}[data-app-customer-mobile-tab="home"][data-app-customer-mobile-view="home"] ${customerMapSelector}`).forEach((mapElement) => {
        const state = mapInstances.get(mapElement);

        if (state) {
            applyTrackingState(state, latestCustomerTracking);
        }
    });
});
window.addEventListener('resize', scanCustomerMaps, { passive: true });
