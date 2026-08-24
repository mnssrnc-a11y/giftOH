/**
 * Firebase Configuration Module
 *
 * Fetches public Firebase configuration from the backend API.
 * This keeps sensitive keys server-side and allows for easy rotation.
 */

import { initializeApp } from 'firebase/app';
import { getAnalytics } from 'firebase/analytics';

/**
 * Fetch public Firebase configuration from the backend.
 * @returns Promise containing Firebase config
 */
async function getFirebaseConfig() {
  try {
    const response = await fetch('/config/firebase');
    if (!response.ok) {
      throw new Error('Failed to fetch Firebase config');
    }
    return await response.json();
  } catch (error) {
    console.error('Error fetching Firebase configuration:', error);
    throw error;
  }
}

/**
 * Initialize Firebase app with configuration from backend.
 */
export async function initializeFirebase() {
  try {
    const config = await getFirebaseConfig();
    const app = initializeApp(config);
    const analytics = getAnalytics(app);
    return { app, analytics };
  } catch (error) {
    console.error('Failed to initialize Firebase:', error);
    throw error;
  }
}

export { initializeApp, getAnalytics };
