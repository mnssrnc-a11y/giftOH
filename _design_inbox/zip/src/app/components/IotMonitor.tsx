import { Activity, DollarSign, Wifi, Zap, Radio, Magnet, Weight, Circle, CheckCircle2, AlertCircle } from "lucide-react";

export function IotMonitor() {
  const liveStats = [
    {
      title: "Current Balance",
      value: "$2,450.75",
      subtitle: "In smart boxes",
      icon: DollarSign,
      color: "bg-green-500",
    },
    {
      title: "Last Transaction",
      value: "$50.00",
      subtitle: "2 minutes ago",
      icon: Activity,
      color: "bg-blue-500",
    },
    {
      title: "System Status",
      value: "Online",
      subtitle: "All boxes active",
      icon: Wifi,
      color: "bg-emerald-500",
    },
  ];

  const sensors = [
    { name: "UV Sensor", status: "active", icon: Zap, description: "Bill authentication" },
    { name: "IR Sensor", status: "active", icon: Radio, description: "Coin detection" },
    { name: "Magnetic Sensor", status: "active", icon: Magnet, description: "Metal verification" },
    { name: "Weight Sensor", status: "active", icon: Weight, description: "Amount calculation" },
  ];

  const activityLog = [
    { id: 1, time: "10:30:45 AM", box: "Box #3", event: "Bill detected: $50.00", status: "success", sensors: "UV, Magnetic" },
    { id: 2, time: "10:28:12 AM", box: "Box #7", event: "Coins detected: $12.50", status: "success", sensors: "IR, Weight" },
    { id: 3, time: "10:25:30 AM", box: "Box #1", event: "Bill detected: $20.00", status: "success", sensors: "UV, Magnetic" },
    { id: 4, time: "10:22:15 AM", box: "Box #5", event: "Coins detected: $5.75", status: "success", sensors: "IR, Weight" },
    { id: 5, time: "10:18:45 AM", box: "Box #2", event: "Bill detected: $100.00", status: "success", sensors: "UV, Magnetic" },
    { id: 6, time: "10:15:20 AM", box: "Box #9", event: "Coins detected: $8.25", status: "success", sensors: "IR, Weight" },
    { id: 7, time: "10:12:10 AM", box: "Box #4", event: "Bill detected: $50.00", status: "success", sensors: "UV, Magnetic" },
    { id: 8, time: "10:08:30 AM", box: "Box #6", event: "Maintenance check completed", status: "info", sensors: "All sensors" },
  ];

  const boxes = [
    { id: "Box #1", location: "Main Entrance", status: "active", balance: "$245.50" },
    { id: "Box #2", location: "Food Court", status: "active", balance: "$389.25" },
    { id: "Box #3", location: "Reception", status: "active", balance: "$512.00" },
    { id: "Box #4", location: "Community Center", status: "active", balance: "$178.40" },
    { id: "Box #5", location: "Library", status: "active", balance: "$156.75" },
    { id: "Box #6", location: "Park Entrance", status: "active", balance: "$298.60" },
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="bg-white border-b border-gray-200 px-8 py-6">
        <h1 className="text-2xl font-bold text-gray-900">Smart Donation Box Monitor</h1>
        <p className="text-sm text-gray-500">Real-time IoT monitoring and sensor analytics</p>
      </div>

      <div className="p-8">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
          {liveStats.map((stat, index) => {
            const Icon = stat.icon;
            return (
              <div key={index} className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div className="flex items-center gap-4 mb-3">
                  <div className={`${stat.color} p-3 rounded-lg`}>
                    <Icon className="w-6 h-6 text-white" />
                  </div>
                  <div className="flex-1">
                    <div className="text-sm text-gray-500 mb-1">{stat.title}</div>
                    <div className="text-2xl font-bold text-gray-900">{stat.value}</div>
                  </div>
                </div>
                <div className="text-xs text-gray-500 flex items-center gap-1">
                  <Circle className="w-2 h-2 fill-green-500 text-green-500" />
                  {stat.subtitle}
                </div>
              </div>
            );
          })}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 className="text-lg font-bold text-gray-900 mb-6">Sensor Status Panel</h3>
            <div className="space-y-4">
              {sensors.map((sensor, index) => {
                const Icon = sensor.icon;
                return (
                  <div key={index} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                    <div className="flex items-center gap-4">
                      <div className="bg-[#3B82F6] p-2.5 rounded-lg">
                        <Icon className="w-5 h-5 text-white" />
                      </div>
                      <div>
                        <div className="font-semibold text-gray-900">{sensor.name}</div>
                        <div className="text-sm text-gray-500">{sensor.description}</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      {sensor.status === "active" ? (
                        <>
                          <CheckCircle2 className="w-5 h-5 text-green-500" />
                          <span className="text-sm font-semibold text-green-600">Active</span>
                        </>
                      ) : (
                        <>
                          <AlertCircle className="w-5 h-5 text-red-500" />
                          <span className="text-sm font-semibold text-red-600">Inactive</span>
                        </>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 className="text-lg font-bold text-gray-900 mb-6">Active Donation Boxes</h3>
            <div className="space-y-3">
              {boxes.map((box, index) => (
                <div key={index} className="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                  <div className="flex items-center gap-3">
                    <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                    <div>
                      <div className="font-semibold text-gray-900">{box.id}</div>
                      <div className="text-sm text-gray-500">{box.location}</div>
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="font-bold text-gray-900">{box.balance}</div>
                    <div className="text-xs text-green-600">Online</div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100">
          <div className="p-6 border-b border-gray-100 flex items-center justify-between">
            <div>
              <h3 className="text-lg font-bold text-gray-900">Real-Time Activity Log</h3>
              <p className="text-sm text-gray-500">Live feed from all smart boxes</p>
            </div>
            <div className="flex items-center gap-2 text-sm">
              <div className="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
              <span className="text-green-600 font-semibold">Live</span>
            </div>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-100">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Time</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Box</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Event</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Sensors Used</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {activityLog.map((log) => (
                  <tr key={log.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{log.time}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{log.box}</td>
                    <td className="px-6 py-4 text-sm text-gray-900">{log.event}</td>
                    <td className="px-6 py-4 text-sm text-gray-600">{log.sensors}</td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span
                        className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                          log.status === "success"
                            ? "bg-green-100 text-green-800"
                            : "bg-blue-100 text-blue-800"
                        }`}
                      >
                        {log.status === "success" ? "Verified" : "Info"}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
