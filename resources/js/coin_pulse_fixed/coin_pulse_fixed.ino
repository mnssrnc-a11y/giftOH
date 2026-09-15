#include <WiFiS3.h>
#include <ArduinoHttpClient.h>
#include <WiFiUdp.h>

// ==========================================
// MANUAL NTP CLIENT (fallback/replacement for WiFi.getTime())
// ==========================================
// WiFi.getTime() relies on the WiFi module's own firmware syncing via NTP
// internally, which is unreliable on some Uno R4 WiFi firmware versions
// and can silently fail forever if it doesn't work on your unit. This does
// NTP directly over UDP instead, giving us full control and clear feedback
// on whether it actually worked.
WiFiUDP ntpUDP;
const char* NTP_SERVER = "pool.ntp.org";
const unsigned int NTP_PORT = 2390; // local port for UDP replies
const int NTP_PACKET_SIZE = 48;
byte ntpPacketBuffer[NTP_PACKET_SIZE];

unsigned long lastKnownEpoch = 0;   // last successfully synced UTC epoch (seconds)
unsigned long lastSyncMillis = 0;   // millis() value when that sync happened

// Sends one NTP request and waits briefly for a reply.
// Returns true and updates lastKnownEpoch/lastSyncMillis on success.
bool syncNtpTime() {
  memset(ntpPacketBuffer, 0, NTP_PACKET_SIZE);
  ntpPacketBuffer[0] = 0b11100011; // LI, Version, Mode
  ntpPacketBuffer[1] = 0;
  ntpPacketBuffer[2] = 6;
  ntpPacketBuffer[3] = 0xEC;
  ntpPacketBuffer[12] = 49;
  ntpPacketBuffer[13] = 0x4E;
  ntpPacketBuffer[14] = 49;
  ntpPacketBuffer[15] = 52;

  ntpUDP.begin(NTP_PORT);
  ntpUDP.beginPacket(NTP_SERVER, 123);
  ntpUDP.write(ntpPacketBuffer, NTP_PACKET_SIZE);
  ntpUDP.endPacket();

  unsigned long startWait = millis();
  while (millis() - startWait < 2000) { // wait up to 2s for a reply
    if (ntpUDP.parsePacket()) {
      ntpUDP.read(ntpPacketBuffer, NTP_PACKET_SIZE);

      unsigned long highWord = word(ntpPacketBuffer[40], ntpPacketBuffer[41]);
      unsigned long lowWord  = word(ntpPacketBuffer[42], ntpPacketBuffer[43]);
      unsigned long secsSince1900 = (highWord << 16) | lowWord;

      const unsigned long SEVENTY_YEARS = 2208988800UL; // seconds between 1900 and 1970
      unsigned long epoch = secsSince1900 - SEVENTY_YEARS;

      if (epoch > 1000000000UL) { // sanity check: should be a plausible 2020s+ timestamp
        lastKnownEpoch = epoch;
        lastSyncMillis = millis();
        ntpUDP.stop();
        return true;
      }
    }
    delay(50);
  }

  ntpUDP.stop();
  return false;
}

// Returns the current best-guess UTC epoch: the last successful NTP sync,
// adjusted forward by however much time has passed since then via millis().
// Returns 0 if we've never synced successfully.
unsigned long getCurrentEpoch() {
  if (lastKnownEpoch == 0) return 0;
  return lastKnownEpoch + (millis() - lastSyncMillis) / 1000UL;
}

// ==========================================
// WIFI CREDENTIALS
// ==========================================
char ssid[] = "HUAWEI-2.4G-2qTz";
char pass[] = "WU6746gd"; // rotate this after testing

// ==========================================
// FIREBASE REALTIME DATABASE
// ==========================================
char serverAddress[] = "https://githope-d36ee-default-rtdb.asia-southeast1.firebasedatabase.app/";
int port = 443;
String authParam = "?auth=V92RmdgwRXHEYNmeez8uegD7LUx5rAVgw2aBEaTa"; // rotate this too

// ==========================================
// BOX IDENTITY (matches dashboard's /boxes/{id} schema)
// ==========================================
const char* BOX_ID   = "SB-001";
const char* LOCATION = "Main Lobby";
const String BOX_PATH = "/boxes/" + String(BOX_ID);

WiFiSSLClient wifiClient;
HttpClient httpClient = HttpClient(wifiClient, serverAddress, port);

// ==========================================
// COIN PULSE DETECTION
// ==========================================
const int COIN_PIN = 2;

volatile int pulseCount = 0;
volatile unsigned long lastPulseTimeUs = 0;
volatile unsigned long lastPulseTimeMs = 0;

const unsigned long PULSE_DEBOUNCE_US = 15000;
const unsigned long COIN_TIMEOUT_MS   = 600;
const unsigned long STARTUP_IGNORE_MS = 3000;

int totalCoins = 0;
int totalAmount = 0;

// ==========================================
// DEVICE PRESENCE / ONLINE-OFFLINE STATUS
// ==========================================
const unsigned long HEARTBEAT_INTERVAL_MS = 5000;   // fast, lightweight "still alive" ping
const unsigned long FULL_STATUS_INTERVAL_MS = 30000; // slower full status+location refresh

unsigned long lastHeartbeatTime = 0;
unsigned long lastFullStatusTime = 0;
bool wasConnected = false;

// ==========================================
// INTERRUPT HANDLER
// ==========================================
void onCoinPulse() {
  unsigned long nowUs = micros();
  if (nowUs - lastPulseTimeUs >= PULSE_DEBOUNCE_US) {
    pulseCount++;
    lastPulseTimeUs = nowUs;
    lastPulseTimeMs = millis();
  }
}

// ==========================================
// DETERMINE COIN VALUE
// ==========================================
const int MAX_VALID_PULSES = 100000;

int getCoinValue(int pulses) {
  if (pulses >= 1 && pulses <= MAX_VALID_PULSES) {
    return pulses;
  }
  return 0;
}

// ==========================================
// LIGHTWEIGHT HEARTBEAT
// Writes ONLY a timestamp to /boxes/{id}/heartbeat - a single small PUT,
// much cheaper/faster than the full status+location PATCH. This is the
// dashboard's primary signal for "is this device alive right now" -
// simple, fast, and less likely to fail than the bigger JSON write.
// ==========================================
void sendHeartbeat() {
  unsigned long epochUTC = getCurrentEpoch(); // plain seconds - fits safely in 32 bits until year 2106, no overflow risk

  String body = String(epochUTC);
  String path = BOX_PATH + "/heartbeat.json" + authParam;

  httpClient.beginRequest();
  httpClient.put(path);
  httpClient.sendHeader("Content-Type", "application/json");
  httpClient.sendHeader("Content-Length", body.length());
  httpClient.beginBody();
  httpClient.print(body);
  httpClient.endRequest();

  int statusCode = httpClient.responseStatusCode();
  httpClient.responseBody();

  if (statusCode == 200) {
    Serial.print("Heartbeat sent: ");
    Serial.println(body);
  } else {
    Serial.print("Heartbeat failed (code ");
    Serial.print(statusCode);
    Serial.println(")");
  }
}

// ==========================================
// SET DEVICE STATUS ON FIREBASE (writes to /boxes/{id})
// ==========================================
void setStatusOnFirebase(String state) {

  unsigned long epochUTC = getCurrentEpoch(); // plain seconds - no overflow risk, matches heartbeat's format

  String jsonPayload = "{";
  jsonPayload += "\"location\":\"" + String(LOCATION) + "\",";
  jsonPayload += "\"status\":\"" + state + "\",";
  jsonPayload += "\"lastSeen\":" + String(epochUTC);
  jsonPayload += "}";

  // PATCH merges these fields into /boxes/{id} without wiping "total"
  String path = BOX_PATH + ".json" + authParam;

  httpClient.beginRequest();
  httpClient.patch(path);
  httpClient.sendHeader("Content-Type", "application/json");
  httpClient.sendHeader("Content-Length", jsonPayload.length());
  httpClient.beginBody();
  httpClient.print(jsonPayload);
  httpClient.endRequest();

  int statusCode = httpClient.responseStatusCode();
  httpClient.responseBody();

  if (statusCode == 200) {
    Serial.print("Status set to: ");
    Serial.println(state);
  } else {
    Serial.print("Failed to set status (code ");
    Serial.print(statusCode);
    Serial.println(")");
  }
}

// ==========================================
// GET INTEGER FROM FIREBASE (path under /boxes/{id})
// ==========================================
int getFirebaseInt(String field) {
  httpClient.beginRequest();
  httpClient.get(BOX_PATH + "/" + field + ".json" + authParam);
  httpClient.endRequest();

  int statusCode = httpClient.responseStatusCode();
  String response = httpClient.responseBody();
  response.trim();

  if (statusCode == 200 && response != "null" && response.length() > 0) {
    return response.toInt();
  }
  return 0;
}

// ==========================================
// PUT INTEGER TO FIREBASE (path under /boxes/{id})
// ==========================================
void putFirebaseInt(String field, int value) {
  String body = String(value);

  httpClient.beginRequest();
  httpClient.put(BOX_PATH + "/" + field + ".json" + authParam);
  httpClient.sendHeader("Content-Type", "application/json");
  httpClient.sendHeader("Content-Length", body.length());
  httpClient.beginBody();
  httpClient.print(body);
  httpClient.endRequest();

  httpClient.responseStatusCode();
  httpClient.responseBody();
}

// ==========================================
// SAVE COIN TO FIREBASE
// ==========================================
void saveCoinToFirebase(int coinValue) {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Cannot save - WiFi not connected.");
    return;
  }

  int currentAmount = getFirebaseInt("total");
  int currentCoins  = getFirebaseInt("totalCoins");

  int newAmount = currentAmount + coinValue;
  int newCoins  = currentCoins + 1;

  putFirebaseInt("total", newAmount);
  putFirebaseInt("totalCoins", newCoins);

  totalAmount = newAmount;
  totalCoins  = newCoins;

  Serial.print("Saved to Firebase. Total Coins: ");
  Serial.print(totalCoins);
  Serial.print(" | Total Amount: P");
  Serial.println(totalAmount);
}

// ==========================================
// WIFI CONNECT
// ==========================================
void connectToWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(ssid);

  WiFi.begin(ssid, pass);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println();
    Serial.print("WiFi Connected! IP: ");
    Serial.println(WiFi.localIP());

    Serial.println("Syncing time via NTP...");
    bool synced = false;
    for (int i = 0; i < 3 && !synced; i++) {
      synced = syncNtpTime();
      if (!synced) {
        Serial.print("NTP attempt ");
        Serial.print(i + 1);
        Serial.println(" failed, retrying...");
        delay(500);
      }
    }

    if (synced) {
      Serial.print("NTP synced. Epoch: ");
      Serial.println(lastKnownEpoch);
    } else {
      Serial.println("WARNING: NTP sync failed after 3 attempts. Will keep retrying in loop(). Check that your network allows outbound UDP on port 123 (some routers/firewalls block NTP).");
    }

  } else {
    Serial.println();
    Serial.println("WiFi connection FAILED. Will retry in loop().");
  }
}

// ==========================================
// SETUP
// ==========================================
void setup() {
  Serial.begin(9600);
  httpClient.setHttpResponseTimeout(3000);

  connectToWiFi();

  if (WiFi.status() == WL_CONNECTED) {
    setStatusOnFirebase("online");
    sendHeartbeat();
    wasConnected = true;
    lastHeartbeatTime = millis();
    lastFullStatusTime = millis();
  }

  pinMode(COIN_PIN, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(COIN_PIN), onCoinPulse, FALLING);
}

// ==========================================
// MAIN LOOP
// ==========================================
void loop() {
  bool isConnected = (WiFi.status() == WL_CONNECTED);

  // Keep trying NTP in the background if we've never synced, or refresh
  // periodically (every 6 hours) to prevent long-term drift. This uses
  // millis()-based timing so it doesn't block coin pulse handling.
  static unsigned long lastNtpAttempt = 0;
  const unsigned long NTP_RETRY_INTERVAL_MS = (lastKnownEpoch == 0) ? 15000UL : (6UL * 3600UL * 1000UL);

  if (isConnected && (millis() - lastNtpAttempt >= NTP_RETRY_INTERVAL_MS)) {
    lastNtpAttempt = millis();
    Serial.println("Attempting NTP sync...");
    if (syncNtpTime()) {
      Serial.print("NTP synced. Epoch: ");
      Serial.println(lastKnownEpoch);
    } else {
      Serial.println("NTP sync attempt failed, will retry.");
    }
  }

  if (!isConnected) {
    if (wasConnected) {
      Serial.println("WiFi lost. Attempting to mark status offline...");
      setStatusOnFirebase("offline");
      wasConnected = false;
    }
    connectToWiFi();
    if (WiFi.status() == WL_CONNECTED) {
      setStatusOnFirebase("online");
      sendHeartbeat();
      wasConnected = true;
      lastHeartbeatTime = millis();
      lastFullStatusTime = millis();
    }
  } else {
    if (millis() - lastHeartbeatTime >= HEARTBEAT_INTERVAL_MS) {
      sendHeartbeat();
      lastHeartbeatTime = millis();
    }
    if (millis() - lastFullStatusTime >= FULL_STATUS_INTERVAL_MS) {
      setStatusOnFirebase("online");
      lastFullStatusTime = millis();
    }
  }

  if (millis() < STARTUP_IGNORE_MS) {
    if (pulseCount > 0) {
      noInterrupts();
      pulseCount = 0;
      interrupts();
    }
    return;
  }

  if (pulseCount > 0 && millis() - lastPulseTimeMs >= COIN_TIMEOUT_MS) {
    noInterrupts();
    int pulses = pulseCount;
    pulseCount = 0;
    interrupts();

    Serial.print("Pulses detected: ");
    Serial.println(pulses);

    int coinValue = getCoinValue(pulses);
    if (coinValue > 0) {
      saveCoinToFirebase(coinValue);
    } else {
      Serial.println("Unrecognized pulse count - ignored.");
    }
  }
}
