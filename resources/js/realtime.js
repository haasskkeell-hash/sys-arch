const realtimePollers = new WeakMap();
const realtimeSelector = '[data-realtime-scope]';

function refreshLivewireComponent(element) {
    const root = element.closest('[wire\\:id]');
    const componentId = root?.getAttribute('wire:id');
    const component = componentId ? window.Livewire?.find(componentId) : null;

    component?.$wire?.$refresh?.();
}

function schedulePoll(element, state) {
    if (!element.isConnected) {
        return;
    }

    state.timer = window.setTimeout(() => poll(element), state.interval);
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

        state.version = payload.version ?? state.version;

        if (hasVersionChanged || state.forceRefresh) {
            state.forceRefresh = false;
            refreshLivewireComponent(element);
        }
    } catch {
        // Polling is best-effort; manual refresh remains available if the endpoint is unavailable.
    } finally {
        state.inFlight = false;
        schedulePoll(element, state);
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
        inFlight: false,
        forceRefresh: false,
        timer: null,
        version: null,
    };

    realtimePollers.set(element, state);
    poll(element);
}

function scanRealtimeScopes() {
    document.querySelectorAll(realtimeSelector).forEach(startRealtimePoller);
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
}

document.addEventListener('DOMContentLoaded', scanRealtimeScopes);
document.addEventListener('livewire:initialized', scanRealtimeScopes);
document.addEventListener('livewire:navigated', scanRealtimeScopes);
document.addEventListener('visibilitychange', refreshVisibleRealtimeScopes);
window.addEventListener('focus', refreshVisibleRealtimeScopes);
