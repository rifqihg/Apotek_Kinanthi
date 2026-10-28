import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Intelligent, low-overhead synchronization composable for multi-window / multi-role workflows.
 * 
 * Features:
 * - Instant Focus Revalidation: Reloads relevant props immediately when switching back to the tab/window.
 * - Gentle Background Polling: Polling that strictly pauses when the tab is hidden or minimized.
 * - Throttle Protection: Prevents rapid duplicate reloads when quickly switching between windows.
 * - Modal/Form Safe: Can be conditionally paused when user has an active modal or form open.
 *
 * @param {Object} options
 * @param {Array<string>} [options.only] - Specific Inertia props to reload (avoids heavy full page state)
 * @param {number} [options.interval=0] - Polling interval in ms (0 to disable background interval)
 * @param {number} [options.throttle=4000] - Minimum ms between focus-triggered reloads
 * @param {Function} [options.enabled] - Function returning boolean to conditionally enable/disable
 */
export function useSmartSync(options = {}) {
    const {
        only = [],
        interval = 0,
        throttle = 4000,
        enabled = () => true,
    } = options;

    let lastReloadTime = Date.now();
    let timer = null;
    let isReloading = false;

    const performReload = () => {
        if (typeof enabled === 'function' && !enabled()) return;
        if (isReloading) return;
        if (typeof document !== 'undefined' && document.visibilityState !== 'visible') return;

        isReloading = true;
        lastReloadTime = Date.now();

        router.reload({
            only: only && only.length > 0 ? only : undefined,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isReloading = false;
            },
        });
    };

    const handleFocus = () => {
        const now = Date.now();
        if (now - lastReloadTime >= throttle) {
            performReload();
        }
    };

    const handleVisibilityChange = () => {
        if (document.visibilityState === 'visible') {
            handleFocus();
        }
    };

    onMounted(() => {
        if (typeof window !== 'undefined') {
            window.addEventListener('focus', handleFocus);
        }
        if (typeof document !== 'undefined') {
            document.addEventListener('visibilitychange', handleVisibilityChange);
        }

        if (interval && interval > 0) {
            timer = setInterval(() => {
                performReload();
            }, interval);
        }
    });

    onUnmounted(() => {
        if (typeof window !== 'undefined') {
            window.removeEventListener('focus', handleFocus);
        }
        if (typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        }

        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    });

    return {
        reloadNow: performReload,
    };
}
