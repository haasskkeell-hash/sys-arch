const technicianCompleteSwipeSelector = '[data-app-technician-complete-swipe]';
const technicianCompleteSwipeStates = new WeakMap();

function technicianActionIdempotencyKey() {
    return globalThis.crypto?.randomUUID?.()
        ?? `fixtrack-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function completeSwipeState(element) {
    const state = technicianCompleteSwipeStates.get(element);

    if (state) {
        return state;
    }

    const thumb = element.querySelector('[data-app-technician-complete-swipe-thumb]');
    const fill = element.querySelector('[data-app-technician-complete-swipe-fill]');
    const label = element.querySelector('[data-app-technician-complete-swipe-label]');

    if (!thumb || !fill || !label) {
        return null;
    }

    const nextState = {
        element,
        fill,
        idempotencyKey: null,
        label,
        dragging: false,
        inFlight: false,
        maxTravel: 0,
        pointerId: null,
        startX: 0,
        thumb,
    };
    technicianCompleteSwipeStates.set(element, nextState);

    return nextState;
}

function setCompleteSwipeProgress(state, distance) {
    const boundedDistance = Math.max(0, Math.min(distance, state.maxTravel));
    const progressWidth = boundedDistance + state.thumb.offsetWidth + 8;

    state.thumb.style.transform = `translate3d(${boundedDistance}px, 0, 0)`;
    state.fill.style.width = `${progressWidth}px`;
    state.distance = boundedDistance;
}

function resetCompleteSwipe(state) {
    state.maxTravel = Math.max(0, state.element.clientWidth - state.thumb.offsetWidth - 8);
    setCompleteSwipeProgress(state, 0);
    delete state.element.dataset.appTechnicianSwipeDragging;
    delete state.element.dataset.appTechnicianSwipeLoading;
}

function technicianComponentFor(element) {
    const root = element.closest('[wire\\:id]');
    const componentId = root?.getAttribute('wire:id');

    return componentId ? window.Livewire?.find(componentId) : null;
}

function finishCompleteSwipe(state) {
    if (!state.dragging || state.inFlight) {
        return;
    }

    state.dragging = false;
    delete state.element.dataset.appTechnicianSwipeDragging;

    if (state.element.hasPointerCapture?.(state.pointerId)) {
        state.element.releasePointerCapture(state.pointerId);
    }

    const shouldComplete = state.maxTravel > 0 && state.distance >= state.maxTravel * 0.78;
    state.pointerId = null;

    if (!shouldComplete) {
        resetCompleteSwipe(state);

        return;
    }

    completeTechnicianJob(state);
}

function completeTechnicianJob(state) {
    const bookingId = Number(state.element.dataset.bookingId);
    const component = technicianComponentFor(state.element);

    if (!Number.isInteger(bookingId) || !component?.updateBookingStatus) {
        resetCompleteSwipe(state);

        return;
    }

    state.idempotencyKey ??= technicianActionIdempotencyKey();
    state.inFlight = true;
    state.element.dataset.appTechnicianSwipeLoading = 'true';
    setCompleteSwipeProgress(state, state.maxTravel);

    Promise.resolve()
        .then(() => component.updateBookingStatus(bookingId, 'completed', state.idempotencyKey))
        .then(() => {
            state.idempotencyKey = null;
            delete state.element.dataset.appTechnicianSwipeError;
            state.label.textContent = 'Swipe to complete';
            resetCompleteSwipe(state);
        })
        .catch(() => {
            state.element.dataset.appTechnicianSwipeError = 'true';
            state.label.textContent = 'Swipe to retry completion';
            resetCompleteSwipe(state);
        })
        .finally(() => {
            state.inFlight = false;
        });
}

function initializeCompleteSwipe(element) {
    const state = completeSwipeState(element);

    if (!state || state.initialized) {
        return;
    }

    state.initialized = true;
    state.distance = 0;
    resetCompleteSwipe(state);

    element.addEventListener('pointerdown', (event) => {
        if (state.inFlight || (event.pointerType === 'mouse' && event.button !== 0)) {
            return;
        }

        state.maxTravel = Math.max(0, element.clientWidth - state.thumb.offsetWidth - 8);
        state.dragging = true;
        state.pointerId = event.pointerId;
        state.startX = event.clientX;
        element.dataset.appTechnicianSwipeDragging = 'true';
        element.setPointerCapture?.(event.pointerId);
        event.preventDefault();
    });
    element.addEventListener('pointermove', (event) => {
        if (!state.dragging || event.pointerId !== state.pointerId) {
            return;
        }

        setCompleteSwipeProgress(state, event.clientX - state.startX);
        event.preventDefault();
    });
    element.addEventListener('pointerup', (event) => {
        if (event.pointerId === state.pointerId) {
            finishCompleteSwipe(state);
        }
    });
    element.addEventListener('pointercancel', (event) => {
        if (event.pointerId === state.pointerId) {
            finishCompleteSwipe(state);
        }
    });
    element.addEventListener('keydown', (event) => {
        if (state.inFlight || !['Enter', ' ', 'ArrowRight'].includes(event.key)) {
            return;
        }

        event.preventDefault();
        state.maxTravel = Math.max(0, element.clientWidth - state.thumb.offsetWidth - 8);
        completeTechnicianJob(state);
    });
}

function scanCompleteSwipes() {
    document.querySelectorAll(technicianCompleteSwipeSelector).forEach(initializeCompleteSwipe);
}

document.addEventListener('DOMContentLoaded', scanCompleteSwipes);
document.addEventListener('livewire:initialized', () => {
    scanCompleteSwipes();

    if (!window.__fixtrackTechnicianActionsHook) {
        Livewire.hook('morphed', () => window.setTimeout(scanCompleteSwipes, 0));
        window.__fixtrackTechnicianActionsHook = true;
    }
});
document.addEventListener('livewire:navigated', scanCompleteSwipes);
