import { createBrowserRouter } from "react-router";
import { RootLayout } from "./components/RootLayout";
import { LandingPage } from "./components/LandingPage";
import { Dashboard } from "./components/Dashboard";
import { IotMonitor } from "./components/IotMonitor";
import { DonationsPage } from "./components/DonationsPage";
import { ReportsPage } from "./components/ReportsPage";
import { AboutPage } from "./components/AboutPage";
import { LoginPage } from "./components/LoginPage";
import { FundRequest } from "./components/FundRequest";
import { SettingsPage } from "./components/SettingsPage";

export const router = createBrowserRouter([
  {
    path: "/",
    Component: RootLayout,
    children: [
      { index: true, Component: LandingPage },
      { path: "dashboard", Component: Dashboard },
      { path: "iot-monitor", Component: IotMonitor },
      { path: "donations", Component: DonationsPage },
      { path: "reports", Component: ReportsPage },
      { path: "about", Component: AboutPage },
      { path: "login", Component: LoginPage },
      { path: "fund-request", Component: FundRequest },
      { path: "settings", Component: SettingsPage },
    ],
  },
]);
