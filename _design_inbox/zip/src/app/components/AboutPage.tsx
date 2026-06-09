import { Target, Eye, Award, Users, Shield, TrendingUp } from "lucide-react";

export function AboutPage() {
  const values = [
    {
      icon: Shield,
      title: "Transparency",
      description: "Every donation is tracked and verified through our IoT-enabled smart boxes, ensuring complete accountability.",
    },
    {
      icon: Target,
      title: "Impact",
      description: "We focus on creating meaningful change in the lives of those who need it most, one donation at a time.",
    },
    {
      icon: Users,
      title: "Community",
      description: "Building a network of compassionate donors and beneficiaries working together for a better tomorrow.",
    },
    {
      icon: TrendingUp,
      title: "Innovation",
      description: "Leveraging cutting-edge IoT technology to revolutionize charitable giving and donation tracking.",
    },
  ];

  const stats = [
    { value: "$2.5M+", label: "Total Donations" },
    { value: "15K+", label: "Donors Worldwide" },
    { value: "50+", label: "Communities Served" },
    { value: "100%", label: "Transparency" },
  ];

  return (
    <div className="min-h-screen bg-white">
      <div className="bg-gradient-to-r from-[#1E3A8A] to-[#3B82F6] text-white px-8 py-16">
        <div className="max-w-5xl mx-auto text-center">
          <h1 className="text-5xl font-bold mb-6">About Gift of Hope</h1>
          <p className="text-xl text-blue-100 max-w-3xl mx-auto">
            A revolutionary charity platform combining transparency, technology, and compassion
            to transform how we give and receive help.
          </p>
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-8 py-16">
        <div className="grid md:grid-cols-2 gap-12 mb-16">
          <div>
            <div className="flex items-center gap-3 mb-4">
              <Eye className="w-8 h-8 text-[#3B82F6]" />
              <h2 className="text-3xl font-bold text-gray-900">Our Vision</h2>
            </div>
            <p className="text-lg text-gray-700 leading-relaxed">
              To create a world where charitable giving is transparent, efficient, and impactful.
              We envision a future where every donation is tracked, every dollar is accounted for,
              and every person in need receives the help they deserve.
            </p>
          </div>

          <div>
            <div className="flex items-center gap-3 mb-4">
              <Target className="w-8 h-8 text-[#3B82F6]" />
              <h2 className="text-3xl font-bold text-gray-900">Our Mission</h2>
            </div>
            <p className="text-lg text-gray-700 leading-relaxed">
              To leverage IoT technology and smart donation boxes to provide complete transparency
              in charitable giving. We're committed to building trust between donors and beneficiaries
              through real-time tracking, verification, and accountability.
            </p>
          </div>
        </div>

        <div className="bg-gray-50 rounded-2xl p-12 mb-16">
          <h2 className="text-3xl font-bold text-gray-900 text-center mb-12">Our Impact</h2>
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8">
            {stats.map((stat, index) => (
              <div key={index} className="text-center">
                <div className="text-4xl font-bold text-[#1E3A8A] mb-2">{stat.value}</div>
                <div className="text-sm text-gray-600">{stat.label}</div>
              </div>
            ))}
          </div>
        </div>

        <div>
          <h2 className="text-3xl font-bold text-gray-900 text-center mb-12">Our Core Values</h2>
          <div className="grid md:grid-cols-2 gap-8">
            {values.map((value, index) => {
              const Icon = value.icon;
              return (
                <div key={index} className="bg-white border border-gray-200 rounded-xl p-8 hover:shadow-lg transition-shadow">
                  <div className="flex items-start gap-4">
                    <div className="bg-[#3B82F6] p-3 rounded-lg flex-shrink-0">
                      <Icon className="w-6 h-6 text-white" />
                    </div>
                    <div>
                      <h3 className="text-xl font-bold text-gray-900 mb-3">{value.title}</h3>
                      <p className="text-gray-600 leading-relaxed">{value.description}</p>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        <div className="bg-gradient-to-r from-[#1E3A8A] to-[#3B82F6] rounded-2xl p-12 mt-16 text-white text-center">
          <Award className="w-16 h-16 mx-auto mb-6" />
          <h2 className="text-3xl font-bold mb-4">The Technology Behind the Mission</h2>
          <p className="text-xl text-blue-100 max-w-3xl mx-auto">
            Our smart donation boxes use UV sensors for bill authentication, IR sensors for coin detection,
            magnetic sensors for metal verification, and weight sensors for amount calculation.
            Every transaction is logged in real-time, ensuring complete transparency and trust.
          </p>
        </div>
      </div>
    </div>
  );
}
