import './bootstrap';

import { initializeApp } from "https://www.gstatic.com/firebasejs/12.17.1/firebase-app.js";
import { getAnalytics } from "https://www.gstatic.com/firebasejs/12.17.1/firebase-analytics.js";

const firebaseConfig = {
  apiKey: "AIzaSyAR5tkycRDFWRPtlgaTbaYQQYzq2Fvs_A8",
  authDomain: "giftofhope-17667.firebaseapp.com",
  databaseURL: "https://giftofhope-17667-default-rtdb.firebaseio.com",
  projectId: "giftofhope-17667",
  storageBucket: "giftofhope-17667.firebasestorage.app",
  messagingSenderId: "776299859195",
  appId: "1:776299859195:web:71f7ea61b567ecbda5b45e",
  measurementId: "G-T36JVDDHT6"
};

const app = initializeApp(firebaseConfig);
const analytics = getAnalytics(app);