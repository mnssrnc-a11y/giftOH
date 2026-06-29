@extends('layouts.public')

@section('title', 'Register - Gift of Hope')

@section('content')
    <div class="min-h-screen flex items-center justify-center bg-gradient-to-r from-[#1E3A8A] to-[#3B82F6]">
        <div class="bg-white rounded-2xl shadow-lg p-8 w-full max-w-md">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">Create Your Account</h2>
            <form method="POST" action="{{ route('register.store') }}" class="space-y-6">
                @csrf

                @if ($errors->any())
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg">
                        @foreach ($errors->all() as $error)
                            <p class="text-sm text-red-700">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="flex">
                    <div>
                        <label for="fname" class="block text-sm font-medium text-gray-700">First Name</label>
                        <input id="fname" name="fname" type="text" required autofocus
                            value="{{ old('fname') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="lname" class="block text-sm font-medium text-gray-700">Last Name</label>
                        <input id="lname" name="lname" type="text" required
                            value="{{ old('lname') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="mname" class="block text-sm font-medium text-gray-700">Middle Name</label>
                        <input id="mname" name="mname" type="text"
                            value="{{ old('mname') }}"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input id="email" name="email" type="email" required
                        value="{{ old('email') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
                    <input id="contact_number" name="contact_number" type="text" required
                        value="{{ old('contact_number') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
                    <select id="gender" name="gender" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="" disabled selected>Select Gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div>
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700">Date of Birth</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" required
                        value="{{ old('date_of_birth') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="province" class="block text-sm font-medium text-gray-700">Province</label>
                    <input id="province" name="province" type="text" required
                        value="{{ old('province') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                    <input id="city" name="city" type="text" required
                        value="{{ old('city') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="barangay" class="block text-sm font-medium text-gray-700">Barangay</label>
                    <input id="barangay" name="barangay" type="text" required
                        value="{{ old('barangay') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="street_address" class="block text-sm font-medium text-gray-700">Street Address</label>
                    <input id="street_address" name="street_address" type="text" required
                        value="{{ old('street_address') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
</div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input id="password" name="password" type="password" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-white bg-[#1E3A8A] hover:bg-[#3B82F6] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Register
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-[#3B82F6] font-semibold hover:underline">Sign in</a>
                </p>
                @if(session('alert_error'))
                    <div class="mt-6 text-center p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        {{ session('alert_error') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
    <script>
        function validatePasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirmation').value;

            const minLength = 8;
            const hasUpperCase = /[A-Z]/.test(password);
            const hasLowerCase = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecialChar = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            if (password.length < minLength || !hasUpperCase || !hasLowerCase || !hasNumber || !hasSpecialChar) {
                    alert('Password must be at least 8 characters long and include uppercase, lowercase, number, and special character.');
                    return false; // Prevent form submission
                }
            if (password !== confirmPassword) {
                alert('Passwords do not match. Please try again.');
                return false; // Prevent form submission
            }
            return true; // Allow form submission
        }
        document.querySelector('form').addEventListener('submit', function(event) {
            if (!validatePasswordMatch()) {
                event.preventDefault(); // Prevent form submission if passwords don't match
            }
        });

        @if(session('alert_error'))
            Swal.fire({
                icon: 'error',
                title: 'Registration Failed',
                text: '{{ session('alert_error') }}'
            });
        @endif

    </script>
@endsection