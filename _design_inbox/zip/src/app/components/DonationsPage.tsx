import { useState } from "react";
import { Heart, CreditCard, Shield, CheckCircle } from "lucide-react";
import { toast } from "sonner";

export function DonationsPage() {
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    amount: "",
    message: "",
  });

  const [submitted, setSubmitted] = useState(false);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitted(true);
    toast.success("Thank you for your donation! Your contribution makes a difference.");
    setTimeout(() => {
      setSubmitted(false);
      setFormData({ name: "", email: "", amount: "", message: "" });
    }, 3000);
  };

  const quickAmounts = ["10", "25", "50", "100", "250"];

  return (
    <div className="min-h-screen bg-gray-50">
      <div className="bg-gradient-to-r from-[#1E3A8A] to-[#3B82F6] text-white px-8 py-12">
        <div className="max-w-4xl mx-auto text-center">
          <Heart className="w-16 h-16 mx-auto mb-4" />
          <h1 className="text-4xl font-bold mb-4">Make a Donation</h1>
          <p className="text-xl text-blue-100">
            Your generosity helps us bring hope and change lives
          </p>
        </div>
      </div>

      <div className="max-w-4xl mx-auto px-8 py-12">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 text-center">
            <div className="bg-blue-100 p-3 rounded-lg w-12 h-12 flex items-center justify-center mx-auto mb-4">
              <Shield className="w-6 h-6 text-[#3B82F6]" />
            </div>
            <h3 className="font-bold text-gray-900 mb-2">100% Secure</h3>
            <p className="text-sm text-gray-600">Your payment information is encrypted and secure</p>
          </div>

          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 text-center">
            <div className="bg-green-100 p-3 rounded-lg w-12 h-12 flex items-center justify-center mx-auto mb-4">
              <CheckCircle className="w-6 h-6 text-green-600" />
            </div>
            <h3 className="font-bold text-gray-900 mb-2">Full Transparency</h3>
            <p className="text-sm text-gray-600">Track your donation in real-time through our dashboard</p>
          </div>

          <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-100 text-center">
            <div className="bg-purple-100 p-3 rounded-lg w-12 h-12 flex items-center justify-center mx-auto mb-4">
              <Heart className="w-6 h-6 text-purple-600" />
            </div>
            <h3 className="font-bold text-gray-900 mb-2">Direct Impact</h3>
            <p className="text-sm text-gray-600">Every dollar goes directly to those in need</p>
          </div>
        </div>

        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-8">
          {submitted ? (
            <div className="text-center py-12">
              <div className="bg-green-100 p-4 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-4">
                <CheckCircle className="w-12 h-12 text-green-600" />
              </div>
              <h2 className="text-2xl font-bold text-gray-900 mb-2">Thank You!</h2>
              <p className="text-gray-600 mb-6">
                Your donation of ${formData.amount} has been received.
              </p>
              <p className="text-sm text-gray-500">
                A confirmation email will be sent to {formData.email}
              </p>
            </div>
          ) : (
            <form onSubmit={handleSubmit}>
              <h2 className="text-2xl font-bold text-gray-900 mb-6">Donation Details</h2>

              <div className="mb-6">
                <label className="block text-sm font-semibold text-gray-700 mb-3">
                  Select Amount
                </label>
                <div className="grid grid-cols-5 gap-3 mb-4">
                  {quickAmounts.map((amount) => (
                    <button
                      key={amount}
                      type="button"
                      onClick={() => setFormData({ ...formData, amount })}
                      className={`py-3 px-4 rounded-lg border-2 transition-all ${
                        formData.amount === amount
                          ? "border-[#3B82F6] bg-blue-50 text-[#1E3A8A] font-bold"
                          : "border-gray-200 hover:border-gray-300"
                      }`}
                    >
                      ${amount}
                    </button>
                  ))}
                </div>
                <input
                  type="number"
                  placeholder="Or enter custom amount"
                  value={formData.amount}
                  onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                  required
                />
              </div>

              <div className="mb-6">
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Full Name
                </label>
                <input
                  type="text"
                  placeholder="John Doe"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                  required
                />
              </div>

              <div className="mb-6">
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Email Address
                </label>
                <input
                  type="email"
                  placeholder="john@example.com"
                  value={formData.email}
                  onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                  required
                />
              </div>

              <div className="mb-6">
                <label className="block text-sm font-semibold text-gray-700 mb-2">
                  Message (Optional)
                </label>
                <textarea
                  placeholder="Leave a message of hope..."
                  value={formData.message}
                  onChange={(e) => setFormData({ ...formData, message: e.target.value })}
                  rows={4}
                  className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6] resize-none"
                />
              </div>

              <button
                type="submit"
                className="w-full bg-[#1E3A8A] text-white py-4 rounded-lg font-bold text-lg hover:bg-[#2d4a9e] transition-colors flex items-center justify-center gap-2"
              >
                <CreditCard className="w-5 h-5" />
                Donate ${formData.amount || "0"} Now
              </button>

              <p className="text-xs text-gray-500 text-center mt-4">
                By donating, you agree to our terms and conditions. All donations are secure and encrypted.
              </p>
            </form>
          )}
        </div>
      </div>
    </div>
  );
}
