@extends('layouts.dashboard')

@section('title', 'Fund Request - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50">
        <div class="bg-white border-b border-gray-200 px-8 py-4">
            <h1 class="text-2xl font-bold text-gray-900">Fund Request</h1>
            <p class="text-sm text-gray-500">Create a funding request / campaign</p>
        </div>

        <div class="p-8">
            <div class="max-w-3xl bg-white rounded-xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Request Details</h2>

                <form method="POST" action="{{ route('fund-request.store') }}">
                    @csrf
                    
                    @if (session('status'))
                        <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-lg">
                            <p class="text-sm text-green-700">{{ session('status') }}</p>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm text-red-700">{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Request Title</label>
                            <input
                                name="title"
                                type="text"
                                required
                                value="{{ old('title') }}"
                                placeholder="Education Fund"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Target Amount (₱)</label>
                            <input
                                name="amount_requested"
                                type="number"
                                required
                                min="1"
                                value="{{ old('amount_requested') }}"
                                placeholder="100000"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                            />
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                        <select name="category" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6] bg-white">
                            <option value="Education" {{ old('category') == 'Education' ? 'selected' : '' }}>Education</option>
                            <option value="Healthcare" {{ old('category') == 'Healthcare' ? 'selected' : '' }}>Healthcare</option>
                            <option value="Food & Shelter" {{ old('category') == 'Food & Shelter' ? 'selected' : '' }}>Food & Shelter</option>
                            <option value="Emergency Relief" {{ old('category') == 'Emergency Relief' ? 'selected' : '' }}>Emergency Relief</option>
                            <option value="Community Development" {{ old('category') == 'Community Development' ? 'selected' : '' }}>Community Development</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                        <textarea
                            name="description"
                            required
                            rows="6"
                            placeholder="Describe what the funds will be used for..."
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        >{{ old('description') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                            <input
                                name="start_date"
                                type="date"
                                required
                                value="{{ old('start_date') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                            <input
                                name="end_date"
                                type="date"
                                required
                                value="{{ old('end_date') }}"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                            />
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-[#1E3A8A] text-white py-3 rounded-lg font-bold hover:bg-[#2d4a9e] transition-colors"
                    >
                        Submit Request
                    </button>
                </form>

                <p class="text-xs text-gray-500 mt-6">
                    Note: This is currently a UI prototype (no database persistence wired yet).
                </p>
            </div>
        </div>
    </div>
@endsection

