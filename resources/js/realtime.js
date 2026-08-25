const realtimePollers = new WeakMap();
const realtimeSelector = '[data-realtime-scope]';
const trackingPollers = new WeakMap();
const trackingSelector = '[data-realtime-tracking]';
const networkStatusSelector = '[data-app-network-status]';

function dispatchNetworkState(online, degraded = false) {
    document.dispatchEvent(new CustomEvent('fixtrack:network-state', {
        detail: { degraded, online },
    }));
}

function dispatchRealtimeError(state, error) {
    const detail = {
        event: 'realtime_poll_failed',
        failureCount: state.failureCount,
        message: error instanceof Error ? error.message : 'Realtime polling failed.',
        online: navigator.onLine !== false,
        scope: state.scope ?? null,
        timestamp: new Date().toISOString(),
    };

    document.dispatchEvent(new CustomEvent('fixtrack:realtime-error', { detail }));
    console.warn('[FixTrack]', detail);
}

function markPollSuccess(state) {
    state.failureCount = 0;

    if (state.networkOnline !== true || state.networkDegraded) {
        state.networkOnline = true;
        state.networkDegraded = false;
        dispatchNetworkState(true);
    }
}

function markPollFailure(state, error = null) {
    state.failureCount = Math.min(state.failureCount + 1, 10);
    const online = navigator.onLine !== false;

    dispatchRealtimeError(state, error);

    if (state.networkOnline !== online || state.networkDegraded !== online) {
        state.networkOnline = online;
        state.networkDegraded = online;
        dispatchNetworkState(online, online);
    }
}

function updateNetworkStatusIndicators({ degraded = false, online = true } = {}) {
    document.querySelectorAll(networkStatusSelector).forEach((element) => {
        const visible = !online || degraded;

        element.hidden = !visible;
        element.textContent = !online
            ? 'You are offline. We will retry automatically.'
            : 'Connection is unstable. Retrying…';
    });
}

function trackingStateSignature(tracking) {
    if (!tracking) {
        return 'none';
    }

    return JSON.stringify([
        tracking.booking_id ?? null,
        tracking.reference ?? null,
        tracking.service_type ?? null,
        tracking.status ?? null,
        tracking.address ?? null,
        tracking.technician?.id ?? null,
        tracking.technician?.name ?? null,
        tracking.technician?.avatar ?? null,
        tracking.technician?.initials ?? null,
    ]);
}

function refreshLivewireComponent(element) {
    const root = element.closest('[wire\\:id]');
    const componentId = root?.getAttribute('wire:id');
    const component = componentId ? window.Livewire?.find(componentId) : null;

    component?.$refresh?.();
}

function schedulePoll(element, state) {
    if (!element.isConnected) {
        return;
    }

    const delay = state.failureCount > 0
        ? Math.min(state.interval * 2 ** state.failureCount, 30000)
        : state.interval;

    state.timer = window.setTimeout(() => poll(element), delay);
}

async function poll(element) {
    const state = realtimePollers.get(element);

    if (!state || !element.isConnected || state.inFlight) {
        return;
    }

    if (document.hidden) {
        schedulePoll(element, state);

        return;
    }

    if (navigator.onLine === false) {
        markPollFailure(state, new Error('Browser is offline.'));
        schedulePoll(element, state);

        return;
    }

    state.inFlight = true;

    try {
        const url = new URL(element.dataset.realtimeUrl, window.location.origin);

        if (state.version) {
            url.searchParams.set('since', state.version);
        }

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error(`Realtime snapshot failed with ${response.status}`);
        }

        const payload = await response.json();
        const hasVersionChanged = state.version !== null && payload.version !== state.version;

        markPollSuccess(state);
        state.version = payload.version ?? state.version;

        if (hasVersionChanged || state.forceRefresh) {
            state.forceRefresh = false;
            refreshLivewireComponent(element);
        }
    } catch (error) {
        markPollFailure(state, error);
    } finally {
        state.inFlight = false;
        schedulePoll(element, state);
    }
}

function scheduleTrackingPoll(element, state) {
    if (!element.isConnected) {
        return;
    }

    const delay = state.failureCount > 0
        ? Math.min(state.interval * 2 ** state.failureCount, 30000)
        : state.interval;

    state.timer = window.setTimeout(() => pollTracking(element), delay);
}

async function pollTracking(element) {
    const state = trackingPollers.get(element);

    if (!state || !element.isConnected || state.inFlight) {
        return;
    }

    if (document.hidden) {
        scheduleTrackingPoll(element, state);

        return;
    }

    if (navigator.onLine === false) {
        markPollFailure(state, new Error('Browser is offline.'));
        scheduleTrackingPoll(element, state);

        return;
    }

    state.inFlight = true;

    try {
        const url = new URL(element.dataset.realtimeTrackingUrl, window.location.origin);

        if (state.version) {
            url.searchParams.set('since', state.version);
        }

        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            cache: 'no-store',
        });

        if (!response.ok) {
            throw new Error(`Tracking snapshot failed with ${response.status}`);
        }

        const payload = await response.json();
        const firstSnapshot = state.version === null;
        const hasVersionChanged = firstSnapshot || payload.version !== state.version;
        const previousTrackingSignature = state.trackingSignature;
        const tracking = Object.prototype.hasOwnProperty.call(payload, 'tracking')
            ? payload.tracking
            : state.tracking;
        const nextTrackingSignature = trackingStateSignature(tracking);
        const shouldRefreshLivewire = !firstSnapshot && previousTrackingSignature !== nextTrackingSignature;

        markPollSuccess(state);
        state.version = payload.version ?? state.version;
        state.tracking = tracking;
        state.trackingSignature = nextTrackingSignature;

        if (hasVersionChanged || state.forceRefresh) {
            state.forceRefresh = false;
            document.dispatchEvent(new CustomEvent('fixtrack:customer-tracking', {
                detail: tracking,
            }));
        }

        if (shouldRefreshLivewire) {
            refreshLivewireComponent(element);
        }
    } catch (error) {
        markPollFailure(state, error);
    } finally {
        state.inFlight = false;
        scheduleTrackingPoll(element, state);
    }
}

function startRealtimePoller(element) {
    if (realtimePollers.has(element)) {
        return;
    }

    const interval = Number(element.dataset.realtimeInterval);
    const url = element.dataset.realtimeUrl;

    if (!url || !Number.isFinite(interval) || interval <= 0) {
        return;
    }

    const state = {
        interval,
        scope: element.dataset.realtimeScope ?? 'realtime',
        inFlight: false,
        forceRefresh: false,
        failureCount: 0,
        timer: null,
        version: null,
    };

    realtimePollers.set(element, state);
    poll(element);
}

function scanRealtimeScopes() {
    document.querySelectorAll(realtimeSelector).forEach(startRealtimePoller);
}

function startTrackingPoller(element) {
    const bookingId = element.dataset.realtimeTrackingBookingId ?? null;
    const existingState = trackingPollers.get(element);

    if (existingState) {
        if (existingState.bookingId !== bookingId) {
            existingState.bookingId = bookingId;
            existingState.version = null;
            existingState.tracking = null;
            existingState.trackingSignature = null;
            existingState.forceRefresh = true;
            window.clearTimeout(existingState.timer);
            pollTracking(element);
        }

        return;
    }

    const interval = Number(element.dataset.realtimeTrackingInterval);
    const url = element.dataset.realtimeTrackingUrl;

    if (!url || !Number.isFinite(interval) || interval <= 0) {
        return;
    }

    const state = {
        bookingId,
        interval,
        scope: element.dataset.realtimeTrackingScope ?? 'customer-active-booking',
        inFlight: false,
        forceRefresh: false,
        failureCount: 0,
        timer: null,
        version: null,
        tracking: null,
        trackingSignature: null,
    };

    trackingPollers.set(element, state);
    pollTracking(element);
}

function scanTrackingScopes() {
    document.querySelectorAll(trackingSelector).forEach(startTrackingPoller);
}

function refreshVisibleRealtimeScopes() {
    if (document.hidden) {
        return;
    }

    document.querySelectorAll(realtimeSelector).forEach((element) => {
        const state = realtimePollers.get(element);

        if (!state) {
            startRealtimePoller(element);

            return;
        }

        state.forceRefresh = true;
        window.clearTimeout(state.timer);
        poll(element);
    });

    refreshVisibleTrackingScopes();
}

function refreshVisibleTrackingScopes() {
    if (document.hidden) {
        return;
    }

    document.querySelectorAll(trackingSelector).forEach((element) => {
        const state = trackingPollers.get(element);

        if (!state) {
            startTrackingPoller(element);

            return;
        }

        state.forceRefresh = true;
        window.clearTimeout(state.timer);
        pollTracking(element);
    });
}

function recoverRealtimeScopes() {
    if (navigator.onLine === false || document.hidden) {
        return;
    }

    document.querySelectorAll(realtimeSelector).forEach((element) => {
        const state = realtimePollers.get(element);

        if (!state) {
            startRealtimePoller(element);

            return;
        }

        state.failureCount = 0;
        state.forceRefresh = true;
        window.clearTimeout(state.timer);
        poll(element);
    });

    document.querySelectorAll(trackingSelector).forEach((element) => {
        const state = trackingPollers.get(element);

        if (!state) {
            startTrackingPoller(element);

            return;
        }

        state.failureCount = 0;
        state.forceRefresh = true;
        window.clearTimeout(state.timer);
        pollTracking(element);
    });
}

document.addEventListener('DOMContentLoaded', () => {
    scanRealtimeScopes();
    scanTrackingScopes();
});
document.addEventListener('livewire:initialized', () => {
    scanRealtimeScopes();
    scanTrackingScopes();

    if (!window.__fixtrackRealtimeHook) {
        Livewire.hook('morphed', () => {
            scanRealtimeScopes();
            scanTrackingScopes();
        });
        window.__fixtrackRealtimeHook = true;
    }
});
document.addEventListener('livewire:navigated', () => {
    scanRealtimeScopes();
    scanTrackingScopes();
});
document.addEventListener('visibilitychange', refreshVisibleRealtimeScopes);
window.addEventListener('focus', refreshVisibleRealtimeScopes);
window.addEventListener('online', () => {
    dispatchNetworkState(true);
    recoverRealtimeScopes();
});
window.addEventListener('offline', () => dispatchNetworkState(false));
document.addEventListener('fixtrack:network-state', (event) => updateNetworkStatusIndicators(event.detail));
