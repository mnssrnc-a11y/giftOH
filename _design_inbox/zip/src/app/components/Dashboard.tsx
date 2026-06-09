import React from "react";
import { DollarSign, TrendingUp, Users, Boxes, Bell, Search, User } from "lucide-react";
import { LineChart, Line, PieChart, Pie, Cell, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from "recharts";

export function Dashboard() {
  const stats = [
    {
      title: "Total Donations",
      value: "$124,580",
      change: "+12.5%",
      icon: DollarSign,
      color: "bg-blue-500",
    },
    {
      title: "Today's Donations",
      value: "$3,420",
      change: "+8.2%",
      icon: TrendingUp,
      color: "bg-green-500",
    },
    {
      title: "Total Donors",
      value: "2,847",
      change: "+156",
      icon: Users,
      color: "bg-purple-500",
    },
    {
      title: "Active Smart Boxes",
      value: "12",
      change: "100%",
      icon: Boxes,
      color: "bg-orange-500",
    },
  ];

  const trendData = [
    { month: "Jan", amount: 8500 },
    { month: "Feb", amount: 9200 },
    { month: "Mar", amount: 10500 },
    { month: "Apr", amount: 11200 },
    { month: "May", amount: 12800 },
    { month: "Jun", amount: 14500 },
  ];

  const donationTypes = [
    { name: "Coins", value: 35, color: "#3B82F6" },
    { name: "Bills", value: 65, color: "#1E3A8A" },
  ];

  const recentDonations = [
    { id: "DN-001", date: "2026-05-14", time: "10:30 AM", amount: "$50.00", type: "Bills", status: "Verified" },
    { id: "DN-002", date: "2026-05-14", time: "09:45 AM", amount: "$12.50", type: "Coins", status: "Verified" },
    { id: "DN-003", date: "2026-05-14", time: "09:15 AM", amount: "$100.00", type: "Bills", status: "Verified" },
    { id: "DN-004", date: "2026-05-13", time: "05:20 PM", amount: "$25.00", type: "Bills", status: "Verified" },
    { id: "DN-005", date: "2026-05-13", time: "04:30 PM", amount: "$8.75", type: "Coins", status: "Verified" },
    { id: "DN-006", date: "2026-05-13", time: "03:15 PM", amount: "$75.00", type: "Bills", status: "Verified" },
  ];

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="bg-white border-b border-gray-200 px-8 py-4">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p className="text-sm text-gray-500">Welcome back! Here's your donation overview.</p>
          </div>

          <div className="flex items-center gap-4">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
              <input
                type="text"
                placeholder="Search..."
                className="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
              />
            </div>
            <button className="relative p-2 hover:bg-gray-100 rounded-lg">
              <Bell className="w-5 h-5 text-gray-600" />
              <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
            </button>
            <div className="flex items-center gap-2 pl-4 border-l border-gray-200">
              <div className="w-9 h-9 bg-[#3B82F6] rounded-full flex items-center justify-center">
                <User className="w-5 h-5 text-white" />
              </div>
              <div className="text-sm">
                <div className="font-semibold text-gray-900">Admin User</div>
                <div className="text-gray-500">Administrator</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="p-8">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
          {stats.map((stat, index) => {
            const Icon = stat.icon;
            return (
              <div key={index} className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div className="flex items-center justify-between mb-4">
                  <div className={`${stat.color} p-3 rounded-lg`}>
                    <Icon className="w-6 h-6 text-white" />
                  </div>
                  <span className="text-sm font-semibold text-green-600">{stat.change}</span>
                </div>
                <div className="text-2xl font-bold text-gray-900 mb-1">{stat.value}</div>
                <div className="text-sm text-gray-500">{stat.title}</div>
              </div>
            );
          })}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 className="text-lg font-bold text-gray-900 mb-6">Donation Trends</h3>
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={trendData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                <XAxis dataKey="month" stroke="#6B7280" />
                <YAxis stroke="#6B7280" />
                <Tooltip />
                <Line type="monotone" dataKey="amount" stroke="#3B82F6" strokeWidth={3} dot={{ fill: "#3B82F6", r: 5 }} />
              </LineChart>
            </ResponsiveContainer>
          </div>

          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 className="text-lg font-bold text-gray-900 mb-6">Donation Types</h3>
            <ResponsiveContainer width="100%" height={300}>
              <PieChart>
                <Pie
                  data={donationTypes}
                  cx="50%"
                  cy="50%"
                  labelLine={false}
                  label={({ name, value }) => `${name}: ${value}%`}
                  outerRadius={100}
                  fill="#8884d8"
                  dataKey="value"
                >
                  {donationTypes.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip />
                <Legend />
              </PieChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100">
          <div className="p-6 border-b border-gray-100">
            <h3 className="text-lg font-bold text-gray-900">Recent Donations</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-100">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Time</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Amount</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Type</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {recentDonations.map((donation) => (
                  <tr key={donation.id} className="hover:bg-gray-50">
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{donation.id}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{donation.date}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{donation.time}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">{donation.amount}</td>
                    <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{donation.type}</td>
                    <td className="px-6 py-4 whitespace-nowrap">
                      <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                        {donation.status}
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
