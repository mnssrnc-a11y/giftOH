import './bootstrap';
import './nav-progress';
import './preferences';
import './admin-ui';
import './user-flow';
import './superadmin-ui';
import './fund-request';
import './request-thread';
import './notify';
import './announcements';

import { initializeApp } from "https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js";
import { getAnalytics } from "https://www.gstatic.com/firebasejs/12.19.0/firebase-analytics.js";

// The page carries the public Firebase web config (layout app.blade.php); fetching it was an extra
// server request on every page, and the local PHP server handles one request at a time.
async function loadFirebaseConfig() {
  if (window.giftOfHopeFirebaseConfig) return window.giftOfHopeFirebaseConfig;
  const response = await fetch('/config/firebase');
  if (!response.ok) {
    throw new Error('Unable to load Firebase configuration.');
  }
  return response.json();
}

const firebaseConfig = await loadFirebaseConfig();
const app = initializeApp(firebaseConfig);
getAnalytics(app);
