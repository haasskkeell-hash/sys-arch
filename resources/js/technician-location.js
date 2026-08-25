const technicianLocationSelector = '[data-app-technician-location]';
const technicianLocationWatchers = new WeakMap();

function setTechnicianLocationStatus(element, message, detail = {}) {
    const status = element.querySelector('[data-app-technician-location-status]');

    if (status) {
        status.textContent = message;
        status.classList.toggle('hidden', message === '');
    }

    const event = {
        ...detail,
        event: detail.event ?? 'technician_location_state_changed',
        message,
        timestamp: new Date().toISOString(),
    };
    window.dispatchEvent(new CustomEvent('fixtrack:technician-location', { detail: event }));

    if (detail.event === 'technician_location_update_failed') {
        console.warn('[FixTrack]', event);
    }
}

function destroyTechnicianLocationWatcher(element) {
    const state = technicianLocationWatchers.get(element);

    if (!state) {
        return;
    }

    if (state.watchId !== null && 'geolocation' in navigator) {
        navigator.geolocation.clearWatch(state.watchId);
    }

    technicianLocationWatchers.delete(element);
}

function startTechnicianLocationWatcher(element) {
    if (element.dataset.appTechnicianLocation !== 'true' || technicianLocationWatchers.has(element) || !('geolocation' in navigator)) {
        return;
    }

    const root = element.closest('[wire\\:id]');
    const componentId = root?.getAttribute('wire:id');
    const component = componentId ? window.Livewire?.find(componentId) : null;

    if (!component?.updateLocation) {
        return;
    }

    const state = {
        inFlight: false,
        lastSentAt: 0,
        watchId: null,
    };
    const sendLocation = ({ coords }) => {
        if (document.hidden || state.inFlight) {
            return;
        }

        const latitude = Number(coords.latitude);
        const longitude = Number(coords.longitude);
        const now = Date.now();

        if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || now - state.lastSentAt < 5000) {
            return;
        }

        state.inFlight = true;
        state.lastSentAt = now;

        Promise.resolve()
            .then(() => component.updateLocation(latitude, longitude))
            .then(() => setTechnicianLocationStatus(element, ''))
            .catch((error) => {
                state.lastSentAt = 0;
                setTechnicianLocationStatus(element, 'Location updates are paused. We’ll retry when the connection returns.', {
                    event: 'technician_location_update_failed',
                    error: error?.message ?? 'unknown',
                });
            })
            .finally(() => {
                state.inFlight = false;
            });
    };

    state.watchId = navigator.geolocation.watchPosition(sendLocation, (error) => {
        setTechnicianLocationStatus(element, 'Location access is unavailable. Check browser permissions to keep dispatch updated.', {
            event: 'technician_location_permission_failed',
            error: error?.message ?? 'unknown',
        });
    }, {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 10000,
    });
    technicianLocationWatchers.set(element, state);
}

function scanTechnicianLocationWatchers() {
    document.querySelectorAll(technicianLocationSelector).forEach((element) => {
        if (element.dataset.appTechnicianLocation === 'true') {
            startTechnicianLocationWatcher(element);
        } else {
            destroyTechnicianLocationWatcher(element);
        }
    });
}

function destroyTechnicianLocationWatchersInside(element) {
    if (element.matches?.(technicianLocationSelector)) {
        destroyTechnicianLocationWatcher(element);
    }

    element.querySelectorAll?.(technicianLocationSelector).forEach(destroyTechnicianLocationWatcher);
}

document.addEventListener('DOMContentLoaded', scanTechnicianLocationWatchers);
document.addEventListener('livewire:initialized', () => {
    scanTechnicianLocationWatchers();

    if (!window.__fixtrackTechnicianLocationHook) {
        Livewire.hook('morph.removing', ({ el }) => destroyTechnicianLocationWatchersInside(el));
        Livewire.hook('morphed', () => window.setTimeout(scanTechnicianLocationWatchers, 0));
        window.__fixtrackTechnicianLocationHook = true;
    }
});
document.addEventListener('livewire:navigated', scanTechnicianLocationWatchers);
document.addEventListener('visibilitychange', scanTechnicianLocationWatchers);
window.addEventListener('focus', scanTechnicianLocationWatchers);
