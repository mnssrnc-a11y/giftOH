import { Outlet, NavLink } from "react-router";
import { Home, Info, Box, LayoutDashboard, LogIn, FileText, FileBarChart, Settings } from "lucide-react";

export function RootLayout() {
  const navItems = [
    { path: "/", label: "Home", icon: Home },
    { path: "/about", label: "About", icon: Info },
    { path: "/iot-monitor", label: "IoT Box Monitor", icon: Box },
    { path: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
    { path: "/login", label: "Login", icon: LogIn },
    { path: "/fund-request", label: "Fund Request", icon: FileText },
    { path: "/reports", label: "Reports", icon: FileBarChart },
    { path: "/settings", label: "Settings", icon: Settings },
  ];

  return (
    <div className="flex h-screen bg-gray-50">
      <aside className="w-64 bg-[#1E3A8A] text-white flex flex-col">
        <div className="p-6 border-b border-blue-700">
          <h1 className="text-2xl font-bold">Gift of Hope</h1>
          <p className="text-blue-200 text-sm mt-1">Charity Platform</p>
        </div>

        <nav className="flex-1 p-4">
          <ul className="space-y-2">
            {navItems.map((item) => {
              const Icon = item.icon;
              return (
                <li key={item.path}>
                  <NavLink
                    to={item.path}
                    end={item.path === "/"}
                    className={({ isActive }) =>
                      `flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                        isActive
                          ? "bg-[#3B82F6] text-white"
                          : "text-blue-100 hover:bg-blue-800"
                      }`
                    }
                  >
                    <Icon className="w-5 h-5" />
                    <span>{item.label}</span>
                  </NavLink>
                </li>
              );
            })}
          </ul>
        </nav>

        <div className="p-4 border-t border-blue-700">
          <div className="text-xs text-blue-200">
            © 2026 Gift of Hope
          </div>
        </div>
      </aside>

      <main className="flex-1 overflow-auto">
        <Outlet />
      </main>
    </div>
  );
}
