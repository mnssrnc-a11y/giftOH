// resources/js/iot-monitor.js
//
// Reads live data from Firebase Realtime Database and populates the
// IoT Box Monitor dashboard. Matches the structure written by the
// Arduino sketch (coin_pulse_fixed.ino):
//
// /boxes
//   /SB-002
//     status: "online" | "offline"      (best-effort, may be stale after a crash)
//     heartbeat: 1757567527000           (ms epoch, updated every 5s by the Arduino)
//     location: "Main Lobby"
//     lastSeen: 1757567527000
//     total: 245
//     totalCoins: 38
//
// LIVENESS LOGIC (as requested): don't compare the heartbeat value
// against the current wall-clock time. Instead, remember the heartbeat
// value from the last check and compare it to the new one on each
// refresh:
//   - value CHANGED since last check  -> device is online
//   - value is IDENTICAL for too many checks in a row -> device is offline
// This avoids relying on the Arduino's own clock being accurate (it
// doesn't matter if NTP drifted or even failed - a changing number is
// proof of life regardless of what that number actually says).
//
// STATUS WRITE-BACK: whenever our computed liveness state actually
// *transitions* (online -> offline or offline -> online), we push that
// new status (and a lastSeen timestamp) back up to
// /boxes/{boxId}/status in Firebase, so anything else reading the DB
// directly (other dashboards, alerts, etc.) sees the same picture.
// We only write on a transition, not on every check, to avoid hammering
// the database with redundant writes every 3s.

import { initializeApp } from "firebase/app";
import { getDatabase, ref, onValue, update, push } from "firebase/database";

// ==========================================
// FIREBASE CONFIG
// ==========================================
let db;

// How often we re-check for a heartbeat change.
const CHECK_INTERVAL_MS = 3000;

// The Arduino sends a new heartbeat every 5s. Since our check interval
// (3s) is faster than that, we WILL sometimes catch the same value twice
// in a row even while the device is perfectly online - that's expected,
// not a failure. Only flag offline after several consecutive unchanged
// checks, long enough to be confident an update was actually missed.
const MISSED_CHECKS_BEFORE_OFFLINE = 4; // ~4 x 3s = 12s of no change = offline

// Per-box tracking state, kept in memory across refreshes (module-level,
// so it persists between render() calls without needing localStorage).
// Map<boxId, { lastHeartbeat: number, missedChecks: number, isOnline: boolean, reportedState: string }>
const boxTracking = new Map();

// ==========================================
// DOM ELEMENTS
// ==========================================
const activeBoxesEl = document.getElementById("stat-active-boxes");
const todayTotalEl = document.getElementById("stat-today-total");
const alertsEl = document.getElementById("stat-alerts");
const tbody = document.getElementById("boxes-tbody");

// ==========================================
// HELPERS
// ==========================================

// Decides what to show in the "Last Seen" column based on the box's
// current liveness state:
//   - online  -> always "Just now" (we just proved it's alive)
//   - offline -> the UTC+8 date/time the box was FIRST detected offline,
//                frozen - it does NOT keep advancing to "now" on every
//                refresh, since the box hasn't actually done anything
//                since then.
//   - syncing -> "Syncing time…" (no valid heartbeat yet)
function formatLastSeen(state, offlineSinceMs) {
  if (state === "online") return "Just now";
  if (state === "syncing") return "Syncing time…";
  return formatUTC8(offlineSinceMs);
}

// Formats a given epoch-ms timestamp as a UTC+8 (Philippine time) date/time
// string, e.g. "2026-09-14 5:44 PM (UTC+8)".
function formatUTC8(ms) {
  if (!ms) return "Unknown";
  const UTC8_OFFSET_MS = 8 * 60 * 60 * 1000;
  const shifted = new Date(ms + UTC8_OFFSET_MS);

  const year = shifted.getUTCFullYear();
  const month = String(shifted.getUTCMonth() + 1).padStart(2, "0");
  const day = String(shifted.getUTCDate()).padStart(2, "0");

  let hours = shifted.getUTCHours();
  const minutes = String(shifted.getUTCMinutes()).padStart(2, "0");
  const ampm = hours >= 12 ? "PM" : "AM";
  hours = hours % 12;
  if (hours === 0) hours = 12;

  return `${year}-${month}-${day} ${hours}:${minutes} ${ampm} (UTC+8)`;
}

// ==========================================
// FIREBASE STATUS WRITE-BACK
// ==========================================
// Pushes the computed status up to /boxes/{boxId}/status (+lastChecked)
// so other consumers of the DB see the same liveness picture we do.
// Fire-and-forget with error logging; a failed write here shouldn't
// break the dashboard's own rendering.
function writeStatusToFirebase(boxId, state) {
  const boxRef = ref(db, `/boxes/${boxId}`);
  update(boxRef, {
    status: state,
    lastChecked: Date.now(),
  }).catch((error) => {
    console.error(`Failed to write status="${state}" for box ${boxId}:`, error);
  });
}

// Creates a log entry under /logs/{boxId} whenever a box is newly
// detected offline, so there's a persistent history of outages (not
// just the current status, which gets overwritten). One entry per
// offline transition, not per check.
function writeOfflineLog(boxId, offlineSinceMs, lastHeartbeat) {
  const logsRef = ref(db, `/logs/${boxId}`);
  push(logsRef, {
    event: "offline",
    timestamp: offlineSinceMs,
    lastHeartbeat: lastHeartbeat,
  }).catch((error) => {
    console.error(`Failed to write offline log for box ${boxId}:`, error);
  });
}

// ==========================================
// CORE: update a box's tracked state by comparing this heartbeat value
// against the one from the last check.
// Returns "online" | "offline" | "syncing"
// ==========================================
function updateLivenessState(boxId, heartbeatMs, existingStatus, existingLastChecked) {
  const value = Number(heartbeatMs || 0);

  // heartbeat 0 = the Arduino has never gotten a synced clock at all yet.
  // Can't tell if it's changing since there's nothing real to compare.
  if (value === 0) {
    const prev = boxTracking.get(boxId);
    boxTracking.set(boxId, {
      lastHeartbeat: 0,
      missedChecks: 0,
      isOnline: false,
      reportedState: prev ? prev.reportedState : null,
      offlineSince: prev ? prev.offlineSince : null,
    });
    maybeReportTransition(boxId, "syncing");
    return "syncing";
  }

  const prev = boxTracking.get(boxId);

  if (!prev) {
    // First time seeing this box (e.g. right after a page refresh, so
    // our in-memory tracking got wiped). Don't blindly assume online -
    // trust whatever Firebase already has recorded in status. Only if
    // Firebase has no status yet (brand new box) do we default to
    // online, since there's nothing else to go on.
    const seededOnline = existingStatus !== "offline";
    const seededState = seededOnline ? "online" : "offline";
    // If it was already offline before the refresh, freeze "last seen"
    // at whenever Firebase last recorded a check (not "now"), so
    // reloading the page doesn't push the timestamp forward.
    const seededOfflineSince = seededOnline ? null : existingLastChecked || Date.now();
    boxTracking.set(boxId, {
      lastHeartbeat: value,
      missedChecks: seededOnline ? 0 : MISSED_CHECKS_BEFORE_OFFLINE,
      isOnline: seededOnline,
      // Seed reportedState with what Firebase already says, so we don't
      // immediately fire a redundant write/log confirming the same
      // status - a write only happens once the state genuinely changes
      // from here.
      reportedState: seededState,
      offlineSince: seededOfflineSince,
    });
    return seededState;
  }

  if (value !== prev.lastHeartbeat) {
    // heartbeat changed - proof of life, reset the missed-check counter
    boxTracking.set(boxId, {
      lastHeartbeat: value,
      missedChecks: 0,
      isOnline: true,
      reportedState: prev.reportedState,
      offlineSince: null,
    });
    maybeReportTransition(boxId, "online");
    return "online";
  }

  // heartbeat unchanged since last check
  const missedChecks = prev.missedChecks + 1;
  const isOnline = missedChecks < MISSED_CHECKS_BEFORE_OFFLINE;
  const state = isOnline ? "online" : "offline";
  // Freeze offlineSince the moment we first flip to offline; keep it
  // unchanged on every subsequent check while still offline.
  const offlineSince = isOnline ? null : (prev.offlineSince || Date.now());
  boxTracking.set(boxId, {
    lastHeartbeat: value,
    missedChecks,
    isOnline,
    reportedState: prev.reportedState,
    offlineSince,
  });
  maybeReportTransition(boxId, state, offlineSince);
  return state;
}

// Only writes to Firebase when the state actually changed from what we
// last reported for this box, so we don't spam the DB with an "online"
// write every 3s while nothing has changed.
function maybeReportTransition(boxId, state, offlineSinceMs) {
  const tracked = boxTracking.get(boxId);
  if (!tracked) return;
  if (tracked.reportedState === state) return;

  tracked.reportedState = state;
  boxTracking.set(boxId, tracked);

  // Only push real online/offline states to Firebase - "syncing" is a
  // local-only concept (no valid heartbeat yet) and isn't something
  // other consumers of /boxes/{id}/status need to see.
  if (state === "online" || state === "offline") {
    writeStatusToFirebase(boxId, state);
  }

  // A fresh offline detection also gets its own permanent log entry.
  if (state === "offline") {
    writeOfflineLog(boxId, offlineSinceMs || Date.now(), tracked.lastHeartbeat);
  }
}

function formatPeso(amount) {
  const value = Number(amount || 0);
  return `\u20B1${value.toLocaleString()}`;
}

// Pulsing dot while online; still dot once the heartbeat has stopped changing.
function statusBadge(state) {
  if (state === "online") {
    return `
      <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-700">
        <span class="relative flex h-2 w-2">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2 w-2 bg-green-600"></span>
        </span>
        Online
      </span>`;
  }
  if (state === "syncing") {
    return `
      <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-yellow-100 text-yellow-700">
        <span class="inline-flex rounded-full h-2 w-2 bg-yellow-500"></span>
        Syncing
      </span>`;
  }
  return `
    <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-medium rounded-full bg-red-100 text-red-700">
      <span class="inline-flex rounded-full h-2 w-2 bg-red-500"></span>
      Offline
    </span>`;
}

// ==========================================
// RENDER
// ==========================================
function render(boxesObj) {
  const boxIds = Object.keys(boxesObj || {});

  let activeCount = 0;
  let alertCount = 0;
  let grandTotal = 0;
  const rowsHtml = [];

  if (boxIds.length === 0) {
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-sm text-gray-400">No boxes registered yet.</td></tr>`;
  }

  boxIds.forEach((boxId) => {
    const box = boxesObj[boxId] || {};
    const heartbeatMs = Number(box.heartbeat || 0);
    const state = updateLivenessState(boxId, heartbeatMs, box.status, box.lastChecked); // "online" | "offline" | "syncing"

    if (state === "online" || state === "syncing") activeCount++;
    if (state === "offline") alertCount++;

    grandTotal += Number(box.total || 0);

    // "Last Seen" display depends on state: online = "Just now",
    // offline = the frozen UTC+8 timestamp of when it WENT offline
    // (looked up from tracking, not recomputed each render), syncing =
    // "Syncing time…".
    const tracked = boxTracking.get(boxId);
    const lastSeenDisplay = formatLastSeen(state, tracked ? tracked.offlineSince : null);

    rowsHtml.push(`
      <tr>
        <td class="px-6 py-4 text-sm font-medium text-gray-900">${boxId}</td>
        <td class="px-6 py-4 text-sm text-gray-600">${box.location || "-"}</td>
        <td class="px-6 py-4 text-sm">${statusBadge(state)}</td>
        <td class="px-6 py-4 text-sm text-gray-600">${lastSeenDisplay}</td>
        <td class="px-6 py-4 text-sm font-medium text-gray-900">${formatPeso(box.total)}</td>
      </tr>
    `);
  });

  if (boxIds.length > 0) {
    tbody.innerHTML = rowsHtml.join("");
  }

  activeBoxesEl.textContent = activeCount;
  todayTotalEl.textContent = formatPeso(grandTotal);
  alertsEl.textContent = alertCount;
}

// ==========================================
// LIVE SUBSCRIPTION
// ==========================================
// Keep the latest snapshot around so the periodic re-check (below) can
// re-run the comparison logic even when Firebase hasn't pushed new data -
// that's actually the important case, since "no new push" for too long
// is exactly what should eventually flip a box to offline.
let latestBoxesData = null;

async function startMonitor() {
  try {
    const response = await fetch("/config/firebase");
    if (!response.ok) {
      throw new Error(`Firebase configuration request failed (${response.status})`);
    }

    const firebaseConfig = await response.json();
    db = getDatabase(initializeApp(firebaseConfig));

    onValue(
      ref(db, "/boxes"),
      (snapshot) => {
        latestBoxesData = snapshot.val();
        render(latestBoxesData);
      },
      (error) => {
        console.error("Firebase read failed:", error);
        tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-sm text-red-500">Failed to load live data: ${error.message}</td></tr>`;
      }
    );
  } catch (error) {
    console.error("Firebase monitor initialization failed:", error);
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-sm text-red-500">Failed to initialize live data: ${error.message}</td></tr>`;
  }
}

startMonitor();

// Re-run the comparison on a fixed clock, independent of whether Firebase
// pushed new data - this is what actually drives the "missed checks"
// counter forward when a device goes silent.
setInterval(() => {
  render(latestBoxesData);
}, CHECK_INTERVAL_MS);