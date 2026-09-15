//   /SB-002
//     status: "online" | "offline"      (best-effort, may be stale after a crash)
//     heartbeat: 1757567527000           (ms epoch, updated every 5s by the Arduino)
//     location: "Main Lobby"
//     lastSeen: 1757567527000
//     total: 245
//     totalCoins: 38

import { initializeApp } from "firebase/app";
import { getDatabase, ref, onValue, update } from "firebase/database";

// FIREBASE CONFIG
const firebaseConfig = {
  apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
  authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
  databaseURL: import.meta.env.VITE_FIREBASE_DATABASE_URL,
  projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
  storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
  appId: import.meta.env.VITE_FIREBASE_APP_ID,
};

const app = initializeApp(firebaseConfig);
const db = getDatabase(app);

// How often we re-check for a heartbeat change.
const CHECK_INTERVAL_MS = 3000;

// The Arduino sends a new heartbeat every 5s. Since our check interval (3s)
const MISSED_CHECKS_BEFORE_OFFLINE = 4; // ~4 x 3s = 12s of no change = offline

// Per-box tracking state, kept in memory across refreshes (module-level,
const boxTracking = new Map();

// DOM ELEMENTS
const activeBoxesEl = document.getElementById("stat-active-boxes");
const todayTotalEl = document.getElementById("stat-today-total");
const alertsEl = document.getElementById("stat-alerts");
const tbody = document.getElementById("boxes-tbody");

// HELPERS
function nowEpochSeconds() {
  return Math.floor(Date.now() / 1000);
}

// "Last seen" text still uses the heartbeat's own timestamp for display
// purposes (nice for humans to read), even though liveness itself no
// longer depends on this being accurate.
function formatTimeAgo(heartbeatMs) {
  if (!heartbeatMs) return "Never";
  const heartbeatSeconds = Math.floor(heartbeatMs / 1000);
  if (heartbeatSeconds === 0) return "Syncing time…";

  const diffSeconds = nowEpochSeconds() - heartbeatSeconds;
  if (diffSeconds < 0) return "Just now";
  if (diffSeconds < 60) return `${diffSeconds}s ago`;
  const diffMinutes = Math.floor(diffSeconds / 60);
  if (diffMinutes < 60) return `${diffMinutes} min ago`;
  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours} hr ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays} day${diffDays > 1 ? "s" : ""} ago`;
}


// FIREBASE STATUS WRITE-BACK
// Pushes the computed status up to /boxes/{boxId}/status (+lastChecked)
function writeStatusToFirebase(boxId, state) {
  const boxRef = ref(db, `/boxes/${boxId}`);
  update(boxRef, {
    status: state,
    lastChecked: Date.now(),
  }).catch((error) => {
    console.error(`Failed to write status="${state}" for box ${boxId}:`, error);
  });
}

// CORE: update a box's tracked state if Online/Offline/SYNCING has changed since last check, and write to Firebase if so.
function updateLivenessState(boxId, heartbeatMs, existingStatus) {
  const value = Number(heartbeatMs || 0);

  // check if heartbeat is 0 (Arduino has never gotten a synced clock)
  if (value === 0) {
    const prev = boxTracking.get(boxId);
    boxTracking.set(boxId, {
      lastHeartbeat: 0,
      missedChecks: 0,
      isOnline: false,
      reportedState: prev ? prev.reportedState : null,
    });
    maybeReportTransition(boxId, "syncing");
    return "syncing";
  }

  const prev = boxTracking.get(boxId);

  if (!prev) {
    // First time seeing this box (e.g. right after a page refresh, so
    // our in-memory tracking got wiped). Don't blindly assume online 
    const seededOnline = existingStatus !== "offline";
    const seededState = seededOnline ? "online" : "offline";
    boxTracking.set(boxId, {
      lastHeartbeat: value,
      missedChecks: seededOnline ? 0 : MISSED_CHECKS_BEFORE_OFFLINE,
      isOnline: seededOnline,
      // Seed reportedState with what Firebase already says, so we don't
      // immediately fire a redundant write confirming the same status - a write only happens once the state genuinely changes from here.
      reportedState: seededState,
    });
    return seededState;
  }

  if (value !== prev.lastHeartbeat) {
    // heartbeat changed - proof of life, reset the missed-check counter
    boxTracking.set(boxId, { lastHeartbeat: value, missedChecks: 0, isOnline: true, reportedState: prev.reportedState });
    maybeReportTransition(boxId, "online");
    return "online";
  }

  // heartbeat unchanged since last check
  const missedChecks = prev.missedChecks + 1;
  const isOnline = missedChecks < MISSED_CHECKS_BEFORE_OFFLINE;
  boxTracking.set(boxId, { lastHeartbeat: value, missedChecks, isOnline, reportedState: prev.reportedState });
  const state = isOnline ? "online" : "offline";
  maybeReportTransition(boxId, state);
  return state;
}

// Only writes to Firebase when the state actually changed from what we
// last reported for this box, so we don't spam the DB with an "online" write every 3s while nothing has changed.
function maybeReportTransition(boxId, state) {
  const tracked = boxTracking.get(boxId);
  if (!tracked) return;
  if (tracked.reportedState === state) return;

  tracked.reportedState = state;
  boxTracking.set(boxId, tracked);

  // Only push real online/offline states to Firebase - "syncing" is a
  // local-only concept (no valid heartbeat yet) and isn't something other consumers of /boxes/{id}/status need to see.
  if (state === "online" || state === "offline") {
    writeStatusToFirebase(boxId, state);
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

// RENDER
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
    const state = updateLivenessState(boxId, heartbeatMs, box.status); // "online" | "offline" | "syncing"

    if (state === "online" || state === "syncing") activeCount++;
    if (state === "offline") alertCount++;

    grandTotal += Number(box.total || 0);

    rowsHtml.push(`
      <tr>
        <td class="px-6 py-4 text-sm font-medium text-gray-900">${boxId}</td>
        <td class="px-6 py-4 text-sm text-gray-600">${box.location || "-"}</td>
        <td class="px-6 py-4 text-sm">${statusBadge(state)}</td>
        <td class="px-6 py-4 text-sm text-gray-600">${formatTimeAgo(heartbeatMs)}</td>
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

// LIVE SUBSCRIPTION
const boxesRef = ref(db, "/boxes");

// Keep the latest snapshot around so the periodic re-check (below) can re-run the comparison logic even when Firebase hasn't pushed new data -
// that's actually the important case, since "no new push" for too long is exactly what should eventually flip a box to offline.
let latestBoxesData = null;

onValue(
  boxesRef,
  (snapshot) => {
    latestBoxesData = snapshot.val();
    render(latestBoxesData);
  },
  (error) => {
    console.error("Firebase read failed:", error);
    tbody.innerHTML = `<tr><td colspan="5" class="px-6 py-4 text-sm text-red-500">Failed to load live data: ${error.message}</td></tr>`;
  }
);

// Re-run the comparison on a fixed clock, independent of whether Firebase
// pushed new data - this is what actually drives the "missed checks" counter forward when a device goes silent.
setInterval(() => {
  render(latestBoxesData);
}, CHECK_INTERVAL_MS);
