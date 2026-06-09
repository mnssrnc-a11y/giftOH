import { Download, FileText, Calendar, TrendingUp, DollarSign, Users } from "lucide-react";
import { BarChart, Bar, LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, Legend } from "recharts";
import { toast } from "sonner";

export function ReportsPage() {
  const monthlyData = [
    { month: "Jan", donations: 8500, donors: 145 },
    { month: "Feb", donations: 9200, donors: 158 },
    { month: "Mar", donations: 10500, donors: 178 },
    { month: "Apr", donations: 11200, donors: 195 },
    { month: "May", donations: 12800, donors: 224 },
    { month: "Jun", donations: 14500, donors: 256 },
  ];

  const categoryData = [
    { category: "Education", amount: 28500 },
    { category: "Healthcare", amount: 22300 },
    { category: "Food & Shelter", amount: 18700 },
    { category: "Emergency Relief", amount: 15200 },
    { category: "Community Dev", amount: 12400 },
  ];

  const monthlySummary = {
    totalDonations: "$124,580",
    totalDonors: "2,847",
    avgDonation: "$43.75",
    growth: "+18.5%",
  };

  const handleExport = (format: string) => {
    toast.success(`Exporting report as ${format.toUpperCase()}...`);
  };

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="bg-white border-b border-gray-200 px-8 py-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Donation Reports</h1>
            <p className="text-sm text-gray-500">Comprehensive analytics and insights</p>
          </div>
          <div className="flex gap-3">
            <button
              onClick={() => handleExport("pdf")}
              className="flex items-center gap-2 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
            >
              <FileText className="w-4 h-4" />
              Export PDF
            </button>
            <button
              onClick={() => handleExport("excel")}
              className="flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
              <Download className="w-4 h-4" />
              Export Excel
            </button>
          </div>
        </div>
      </div>

      <div className="p-8">
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-8">
          <div className="flex items-center gap-2 mb-6">
            <Calendar className="w-5 h-5 text-[#3B82F6]" />
            <h2 className="text-xl font-bold text-gray-900">Monthly Summary</h2>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div className="bg-gradient-to-br from-blue-50 to-blue-100 p-6 rounded-lg">
              <div className="flex items-center gap-3 mb-2">
                <div className="bg-[#3B82F6] p-2 rounded-lg">
                  <DollarSign className="w-5 h-5 text-white" />
                </div>
                <div className="text-sm text-gray-600">Total Donations</div>
              </div>
              <div className="text-3xl font-bold text-gray-900">{monthlySummary.totalDonations}</div>
            </div>

            <div className="bg-gradient-to-br from-purple-50 to-purple-100 p-6 rounded-lg">
              <div className="flex items-center gap-3 mb-2">
                <div className="bg-purple-600 p-2 rounded-lg">
                  <Users className="w-5 h-5 text-white" />
                </div>
                <div className="text-sm text-gray-600">Total Donors</div>
              </div>
              <div className="text-3xl font-bold text-gray-900">{monthlySummary.totalDonors}</div>
            </div>

            <div className="bg-gradient-to-br from-green-50 to-green-100 p-6 rounded-lg">
              <div className="flex items-center gap-3 mb-2">
                <div className="bg-green-600 p-2 rounded-lg">
                  <DollarSign className="w-5 h-5 text-white" />
                </div>
                <div className="text-sm text-gray-600">Avg Donation</div>
              </div>
              <div className="text-3xl font-bold text-gray-900">{monthlySummary.avgDonation}</div>
            </div>

            <div className="bg-gradient-to-br from-orange-50 to-orange-100 p-6 rounded-lg">
              <div className="flex items-center gap-3 mb-2">
                <div className="bg-orange-600 p-2 rounded-lg">
                  <TrendingUp className="w-5 h-5 text-white" />
                </div>
                <div className="text-sm text-gray-600">Growth Rate</div>
              </div>
              <div className="text-3xl font-bold text-gray-900">{monthlySummary.growth}</div>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 className="text-lg font-bold text-gray-900 mb-6">Monthly Donation Trends</h3>
            <ResponsiveContainer width="100%" height={350}>
              <LineChart data={monthlyData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                <XAxis dataKey="month" stroke="#6B7280" />
                <YAxis stroke="#6B7280" />
                <Tooltip />
                <Legend />
                <Line
                  type="monotone"
                  dataKey="donations"
                  stroke="#3B82F6"
                  strokeWidth={3}
                  name="Donations ($)"
                  dot={{ fill: "#3B82F6", r: 4 }}
                />
                <Line
                  type="monotone"
                  dataKey="donors"
                  stroke="#10B981"
                  strokeWidth={3}
                  name="Donors"
                  dot={{ fill: "#10B981", r: 4 }}
                />
              </LineChart>
            </ResponsiveContainer>
          </div>

          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
            <h3 className="text-lg font-bold text-gray-900 mb-6">Donations by Category</h3>
            <ResponsiveContainer width="100%" height={350}>
              <BarChart data={categoryData}>
                <CartesianGrid strokeDasharray="3 3" stroke="#E5E7EB" />
                <XAxis dataKey="category" stroke="#6B7280" angle={-15} textAnchor="end" height={80} />
                <YAxis stroke="#6B7280" />
                <Tooltip />
                <Bar dataKey="amount" fill="#1E3A8A" name="Amount ($)" radius={[8, 8, 0, 0]} />
              </BarChart>
            </ResponsiveContainer>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100">
          <div className="p-6 border-b border-gray-100">
            <h3 className="text-lg font-bold text-gray-900">Detailed Transaction History</h3>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 border-b border-gray-100">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Month</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Donations</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Total Donors</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Avg Donation</th>
                  <th className="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Growth</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {monthlyData.map((item, index) => {
                  const avgDonation = (item.donations / item.donors).toFixed(2);
                  const growth = index > 0
                    ? (((item.donations - monthlyData[index - 1].donations) / monthlyData[index - 1].donations) * 100).toFixed(1)
                    : "0.0";

                  return (
                    <tr key={item.month} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{item.month} 2026</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">${item.donations.toLocaleString()}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{item.donors}</td>
                      <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">${avgDonation}</td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                          parseFloat(growth) >= 0 ? "bg-green-100 text-green-800" : "bg-red-100 text-red-800"
                        }`}>
                          {parseFloat(growth) >= 0 ? "+" : ""}{growth}%
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
}
