/**
 * Atelier front-end entry (#35).
 *
 * Two jobs, both about native web push:
 *
 *  1. Register the root-scope service worker on load. Registration alone never prompts
 *     for permission — it only readies the worker so a later, explicit opt-in can
 *     subscribe.
 *  2. Expose `window.atelierPush` so the bell, the settings toggle, and the client
 *     reply banner can turn a browser subscription on and off. The permission prompt
 *     is only ever raised from inside `enable()`, i.e. from a user click — never here.
 */

const PUSH_KIND_KEY = 'atelier.push.kind';
const SUBSCRIBED_KEY = 'atelier.push.subscribed';

const ENDPOINTS = {
    creator: { subscribe: '/push/subscribe', unsubscribe: '/push/unsubscribe' },
    client: { subscribe: '/push/client/subscribe', unsubscribe: '/push/client/unsubscribe' },
};

function pushSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/**
 * What an opt-in attempt came to. `enable()` and `disable()` never reject and never
 * answer with a bare boolean: a subscription can fail in half a dozen places — an
 * insecure origin, a missing VAPID key, a refused permission, the browser's own push
 * service — and a caller that only learns "false" can only leave the button sitting
 * there. Each failure is named here so the UI can say which one happened.
 *
 * `reason` is one of:
 *   unsupported  — no service worker or PushManager (an insecure origin, usually)
 *   unconfigured — the page carries no VAPID public key
 *   denied       — the browser refused, or the person did
 *   no-worker    — the service worker would not register
 *   push-service — the browser could not register the subscription with its push service
 *   server       — the subscription was made but this app would not store it
 *
 * @typedef {{ok: boolean, reason: string|null, detail: string|null}} PushResult
 *
 * @param {string|null} reason
 * @param {string|null} detail The browser's own words, where it offered any.
 * @returns {PushResult}
 */
function outcome(reason = null, detail = null) {
    return { ok: reason === null, reason, detail };
}

function metaContent(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || '';
}

/**
 * The VAPID public key travels as base64url; PushManager wants a Uint8Array.
 */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
}

async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return null;
    }

    try {
        return await navigator.serviceWorker.register('/sw.js', { scope: '/' });
    } catch (error) {
        return null;
    }
}

async function postSubscription(url, body) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': metaContent('csrf-token'),
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        return response.ok;
    } catch (error) {
        return false;
    }
}

/**
 * Turn browser notifications on for this device. Called from a user click, so this is
 * the one place a permission prompt is allowed to appear.
 *
 * `pushManager.subscribe()` is a call into the browser's own push service, and it fails
 * for reasons that have nothing to do with this app — a Chromium fork with Google's push
 * service switched off answers with `AbortError: Registration failed`. That rejection is
 * caught here rather than left to escape into the click handler, where it surfaced as a
 * console trace and an unchanged button.
 *
 * @param {'creator'|'client'} kind Which door to store the subscription behind.
 * @returns {Promise<PushResult>}
 */
async function enable(kind = 'creator') {
    if (!pushSupported()) {
        return outcome('unsupported');
    }

    const vapidPublicKey = metaContent('vapid-public-key');

    if (!vapidPublicKey) {
        return outcome('unconfigured');
    }

    let permission;

    try {
        permission = await Notification.requestPermission();
    } catch (error) {
        return outcome('denied', error.message);
    }

    if (permission !== 'granted') {
        return outcome('denied');
    }

    const registration = (await navigator.serviceWorker.getRegistration('/')) || (await registerServiceWorker());

    if (!registration) {
        return outcome('no-worker');
    }

    await navigator.serviceWorker.ready;

    let subscription;

    try {
        subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
            });
        }
    } catch (error) {
        return outcome('push-service', error.message);
    }

    const endpoints = ENDPOINTS[kind] || ENDPOINTS.creator;
    const payload = subscription.toJSON();

    const stored = await postSubscription(endpoints.subscribe, {
        endpoint: payload.endpoint,
        keys: payload.keys,
        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
    });

    if (!stored) {
        return outcome('server');
    }

    window.localStorage.setItem(PUSH_KIND_KEY, kind);
    window.localStorage.setItem(SUBSCRIBED_KEY, '1');

    return outcome();
}

/**
 * Turn browser notifications off for this device, dropping the row server-side too.
 * Off is the state the person asked for, so this reports success even where the
 * browser end of the teardown fails: the device is unsubscribed locally either way.
 *
 * @returns {Promise<PushResult>}
 */
async function disable() {
    window.localStorage.removeItem(SUBSCRIBED_KEY);

    if (!('serviceWorker' in navigator)) {
        return outcome();
    }

    try {
        const registration = await navigator.serviceWorker.getRegistration('/');
        const subscription = await registration?.pushManager.getSubscription();

        if (!subscription) {
            return outcome();
        }

        const kind = window.localStorage.getItem(PUSH_KIND_KEY) || 'creator';
        const endpoints = ENDPOINTS[kind] || ENDPOINTS.creator;
        const endpoint = subscription.endpoint;

        await subscription.unsubscribe();
        await postSubscription(endpoints.unsubscribe, { endpoint });
    } catch (error) {
        return outcome();
    }

    window.localStorage.removeItem(PUSH_KIND_KEY);

    return outcome();
}

/**
 * A cheap, synchronous best-effort for button initial state. The authoritative answer
 * is async (pushManager.getSubscription); this is only the last thing this device did.
 */
function isSubscribed() {
    return window.localStorage.getItem(SUBSCRIBED_KEY) === '1';
}

window.atelierPush = { enable, disable, isSubscribed, supported: pushSupported };

if (pushSupported()) {
    // Ready the worker for a later opt-in. This does not prompt for permission.
    window.addEventListener('load', () => {
        registerServiceWorker();
    });
}
