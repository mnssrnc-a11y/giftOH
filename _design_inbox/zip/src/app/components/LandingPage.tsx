import { useNavigate } from "react-router";
import { Heart, TrendingUp, Boxes, Shield } from "lucide-react";

export function LandingPage() {
  const navigate = useNavigate();

  const features = [
    {
      icon: Shield,
      title: "Complete Transparency",
      description: "Track every donation in real-time with full visibility into where your contributions go.",
    },
    {
      icon: TrendingUp,
      title: "Real-Time Tracking",
      description: "Monitor donations as they happen with our advanced IoT-enabled smart donation boxes.",
    },
    {
      icon: Boxes,
      title: "Smart Automation",
      description: "Automated coin and bill detection with UV, IR, magnetic, and weight sensors.",
    },
  ];

  return (
    <div className="min-h-screen bg-white">
      <section className="bg-gradient-to-br from-[#1E3A8A] to-[#3B82F6] text-white py-20 px-8">
        <div className="max-w-6xl mx-auto">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <h1 className="text-5xl font-bold mb-6">Give Hope, Change Lives</h1>
              <p className="text-xl text-blue-100 mb-8">
                A transparent charity donation platform with smart IoT monitoring.
                Every donation is tracked, verified, and makes a real difference.
              </p>
              <div className="flex gap-4">
                <button
                  onClick={() => navigate("/donations")}
                  className="bg-white text-[#1E3A8A] px-8 py-3 rounded-lg font-semibold hover:bg-blue-50 transition-colors"
                >
                  Donate Now
                </button>
                <button
                  onClick={() => navigate("/dashboard")}
                  className="bg-transparent border-2 border-white px-8 py-3 rounded-lg font-semibold hover:bg-white/10 transition-colors"
                >
                  View Dashboard
                </button>
              </div>
            </div>

            <div className="bg-white/10 backdrop-blur-sm rounded-2xl p-8 border border-white/20">
              <div className="bg-white rounded-lg p-6 shadow-xl">
                <div className="flex items-center gap-4 mb-4">
                  <div className="bg-[#3B82F6] text-white p-3 rounded-lg">
                    <Boxes className="w-8 h-8" />
                  </div>
                  <div>
                    <div className="text-sm text-gray-500">Smart Donation Box</div>
                    <div className="text-xl font-bold text-gray-900">IoT Enabled</div>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3 text-gray-700 text-sm">
                  <div className="bg-green-50 p-3 rounded-lg">
                    <div className="text-green-600 font-semibold">UV Sensor</div>
                    <div className="text-xs">Active</div>
                  </div>
                  <div className="bg-green-50 p-3 rounded-lg">
                    <div className="text-green-600 font-semibold">IR Sensor</div>
                    <div className="text-xs">Active</div>
                  </div>
                  <div className="bg-green-50 p-3 rounded-lg">
                    <div className="text-green-600 font-semibold">Magnetic</div>
                    <div className="text-xs">Active</div>
                  </div>
                  <div className="bg-green-50 p-3 rounded-lg">
                    <div className="text-green-600 font-semibold">Weight</div>
                    <div className="text-xs">Active</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="py-20 px-8 bg-gray-50">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl font-bold text-gray-900 mb-4">Why Choose Gift of Hope?</h2>
            <p className="text-xl text-gray-600">
              Cutting-edge technology meets compassionate giving
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            {features.map((feature, index) => {
              const Icon = feature.icon;
              return (
                <div key={index} className="bg-white p-8 rounded-xl shadow-sm hover:shadow-md transition-shadow">
                  <div className="bg-[#3B82F6] text-white w-14 h-14 rounded-lg flex items-center justify-center mb-4">
                    <Icon className="w-7 h-7" />
                  </div>
                  <h3 className="text-xl font-bold text-gray-900 mb-3">{feature.title}</h3>
                  <p className="text-gray-600">{feature.description}</p>
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <section className="py-20 px-8 bg-[#1E3A8A] text-white">
        <div className="max-w-4xl mx-auto text-center">
          <Heart className="w-16 h-16 mx-auto mb-6 text-blue-300" />
          <h2 className="text-4xl font-bold mb-6">Ready to Make a Difference?</h2>
          <p className="text-xl text-blue-100 mb-8">
            Join thousands of donors who trust our platform to deliver hope to those in need.
          </p>
          <button
            onClick={() => navigate("/donations")}
            className="bg-white text-[#1E3A8A] px-12 py-4 rounded-lg font-semibold text-lg hover:bg-blue-50 transition-colors"
          >
            Start Donating Today
          </button>
        </div>
      </section>
    </div>
  );
}
