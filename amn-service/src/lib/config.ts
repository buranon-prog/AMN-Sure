// Runtime settings (env-overridable).

function hhmm(value: string | undefined, fallback: string) {
  return value && /^\d{2}:\d{2}$/.test(value) ? value : fallback;
}

/** Location tracking is only accepted between these Bangkok local times. */
export const TRACKING_START = hhmm(process.env.TRACKING_START, "07:00");
export const TRACKING_END = hhmm(process.env.TRACKING_END, "20:00");

// Real-time tracking cadence (web app on the phone):
/** While moving: send at most this often… */
export const PING_MOVING_MS = 8 * 1000;
/** …and only once the phone moved at least this far. */
export const PING_MIN_MOVE_M = 20;
/** While standing still: still send a heartbeat this often. */
export const PING_HEARTBEAT_MS = 60 * 1000;
/** Server ignores points closer together than this (per employee). */
export const PING_MIN_GAP_MS = 5 * 1000;
/** A technician counts as "LIVE" on the office map if seen within this window. */
export const LIVE_WINDOW_MS = 90 * 1000;

/** Location history older than this is deleted (PDPA: keep only what is needed). */
export const LOCATION_RETENTION_DAYS = Math.max(7, Number(process.env.LOCATION_RETENTION_DAYS) || 90);

export const MAX_LOGIN_ATTEMPTS = 5;
export const LOCKOUT_MINUTES = 15;
