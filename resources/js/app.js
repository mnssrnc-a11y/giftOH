import './bootstrap';

import { initializeApp } from "https://www.gstatic.com/firebasejs/12.19.0/firebase-app.js";
import { getAnalytics } from "https://www.gstatic.com/firebasejs/12.19.0/firebase-analytics.js";

const response = await fetch('/config/firebase');
if (!response.ok) {
  throw new Error('Unable to load Firebase configuration.');
}

const firebaseConfig = await response.json();
const app = initializeApp(firebaseConfig);
getAnalytics(app);