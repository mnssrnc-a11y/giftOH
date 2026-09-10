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

                <form method="POST" action="{{ route('fund-request.store') }}" enctype="multipart/form-data">
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

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Organization Name</label>
                            <input
                                name="org_name"
                                type="text"
                                required
                                value="{{ old('org_name') }}"
                                placeholder="Organization Name"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Amount Requested (₱)</label>
                            <input
                                name="amount_requested"
                                type="number"
                                required
                                min="1"
                                value="{{ old('amount_requested') }}"
                                placeholder="100"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                            />
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Category</label>
                        <select name="category" required class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6] bg-white placeholder-gray-400= SELECT   ">
                            <option value="" {{ old('category') == '' ? 'selected' : '' }}>Select a category</option>
                            <option value="Education" {{ old('category') == 'Education' ? 'selected' : '' }}>Education</option>
                            <option value="Healthcare" {{ old('category') == 'Healthcare' ? 'selected' : '' }}>Healthcare</option>
                            <option value="Food & Shelter" {{ old('category') == 'Food & Shelter' ? 'selected' : '' }}>Food & Shelter</option>
                            <option value="Emergency Relief" {{ old('category') == 'Emergency Relief' ? 'selected' : '' }}>Emergency Relief</option>
                            <option value="Community Development" {{ old('category') == 'Community Development' ? 'selected' : '' }}>Community Development</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Person</label>
                        <input
                            name="contact_person"
                            type="text"
                            required
                            value="{{ old('contact_person') }}"
                            placeholder="contact person name"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Email</label>
                        <input
                            name="contact_email"
                            type="email"
                            required
                            value="{{ old('contact_email') }}"
                            placeholder="contact@example.com"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Contact Number</label>
                        <input
                            name="phone"
                            type="text"
                            required
                            value="{{ old('phone') }}"
                            placeholder="09123456789"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Tax ID</label>
                        <input
                            name="tax_id"
                            type="text"
                            required
                            value="{{ old('tax_id') }}"
                            placeholder="TAX-123456789"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                        <input
                            name="address"
                            type="text"
                            required
                            value="{{ old('address') }}"
                            placeholder="123 Main St, City, Province"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        />
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Mission</label>
                        <textarea
                            name="mission"
                            required
                            placeholder="Describe your organization's mission and how the requested funds will be used."
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6]"
                        >{{ old('mission') }}</textarea>
                    </div>

<div class="border-t border-gray-200 pt-7 mb-7">
                    <h3 class="text-base font-bold text-gray-900 mb-1">Supporting Documents</h3>
                    <p class="text-xs text-gray-500 mb-5">
                        Upload the three documents below. Accepted formats: JPG, PNG, PDF. Max 5MB each.
                    </p>
                    <!-- <<-- Document Uploads -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Supporting Document <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">e.g. organization/foundation BIR registration and relevant tax documents Mayor’s/Business Permit, if applicable</p>
                            <label
                                for="doc_image"
                                id="doc_image_label"
                                class="flex flex-col items-center gap-2 px-4 py-5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-[#3B82F6] hover:bg-blue-50 transition-colors"
                            >
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                <span class="text-sm text-gray-400" id="doc_image_name">Click to upload</span>
                            </label>
                            <input
                                type="file"
                                id="doc_image"
                                name="doc_image"
                                accept="image/*,.pdf"
                                required
                                class="sr-only"
                                onchange="previewFileName(this, 'doc_image_name')"/>
                            @error('doc_image')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <!-- Valid ID -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Valid ID <span class="text-red-500">*</span></label>
                            <p class="text-xs text-gray-400 mb-2">Any government-issued photo ID</p>
                            <label
                                for="id_image"
                                class="flex flex-col items-center gap-2 px-4 py-5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-[#3B82F6] hover:bg-blue-50 transition-colors">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                <span class="text-sm text-gray-400" id="id_image_name">Click to upload</span>
                            </label>
                            <input
                                type="file"
                                id="id_image"
                                name="id_image"
                                accept="image/*,.pdf"
                                required
                                class="sr-only"
                                onchange="previewFileName(this, 'id_image_name')"/>
                            @error('id_image')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <!-- Financial Report -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Financial Report <span class="text-red-500">*</span></label>
                            <p class="text-xs text-gray-400 mb-2">Annual financial report</p>
                            <label
                                for="financial_rprt"
                                class="flex flex-col items-center gap-2 px-4 py-5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-[#3B82F6] hover:bg-blue-50 transition-colors">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                <span class="text-sm text-gray-400" id="financial_rprt_name">Click to upload</span>
                            </label>
                            <input
                                type="file"
                                id="financial_rprt"
                                name="financial_rprt"
                                accept="image/*,.pdf"
                                required
                                class="sr-only"
                                onchange="previewFileName(this, 'financial_rprt_name')"/>
                            @error('financial_rprt')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <!-- Government Document -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">
                                Barangay Clearance <span class="text-red-500">*</span>
                            </label>
                            <p class="text-xs text-gray-400 mb-2">Latest Barangay Clearance</p>
                            <label
                                for="barangay_clr"
                                class="flex flex-col items-center gap-2 px-4 py-5 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-[#3B82F6] hover:bg-blue-50 transition-colors">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                </svg>
                                <span class="text-sm text-gray-400" id="barangay_clr_name">Click to upload</span>
                            </label>
                            <input
                                type="file"
                                id="barangay_clr"
                                name="barangay_clr"
                                accept="image/*,.pdf"
                                required
                                class="sr-only"
                                onchange="previewFileName(this, 'barangay_clr_name')"
                            />
                            @error('barangay_clr')
                                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>
                <button
                    type="submit"
                    class="w-full bg-[#1E3A8A] text-white py-3.5 rounded-xl font-bold hover:bg-[#2d4a9e] transition-colors">
                    Submit Request
                </button>

                <p class="text-xs text-gray-500 text-center mt-5">
                    Submitted requests are reviewed by our team within 3–5 business days.
                    You will receive a notification once a decision has been made.
                </p>
            </form>
        </div>
    </div>
</div>

<script>
    // Shows the selected filename inside the upload box instead of "Click to upload"
    function previewFileName(input, labelId) {
        const label = document.getElementById(labelId);
        if (input.files && input.files[0]) {
            const name = input.files[0].name;
            // Truncate long filenames
            label.textContent = name.length > 22 ? name.substring(0, 20) + '…' : name;
            label.classList.remove('text-gray-400');
            label.classList.add('text-[#1E3A8A]', 'font-semibold');
        }
    }
</script>
@endsection

