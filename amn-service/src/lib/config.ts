// Runtime settings (env-overridable).

function hhmm(value: string | undefined, fallback: string) {
  return value && /^\d{2}:\d{2}$/.test(value) ? value : fallback;
}

/** Location tracking is only accepted between these Bangkok local times. */
export const TRACKING_START = hhmm(process.env.TRACKING_START, "07:00");
export const TRACKING_END = hhmm(process.env.TRACKING_END, "20:00");

/** Client sends a ping at most this often while stationary… */
export const PING_INTERVAL_MS = 3 * 60 * 1000;
/** …or sooner if the technician moved at least this far. */
export const PING_MIN_MOVE_M = 150;
/** Server ignores pings closer together than this. */
export const PING_MIN_GAP_MS = 45 * 1000;

export const MAX_LOGIN_ATTEMPTS = 5;
export const LOCKOUT_MINUTES = 15;
