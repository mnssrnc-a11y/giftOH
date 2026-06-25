@extends('layouts.admin')

@section('title', 'Admin Dashboard - Gift of Hope')

@section('content')
    <div class="min-h-screen bg-gray-50 p-8">
        <div class="max-w-6xl mx-auto">
            <!-- Header Section -->
            <div class="flex items-center justify-between mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Admin Control Panel</h1>
                    <p class="text-gray-600 mt-1">Manage and approve/reject charity funding requests</p>
                </div>
            </div>

            @if (session('status'))
                <div class="mb-5 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <p class="text-sm text-green-700">{{ session('status') }}</p>
                </div>
            @endif

            @if (session('alert_error'))
                <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm text-red-700">{{ session('alert_error') }}</p>
                </div>
            @endif

            <!-- Pending Requests Section -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-xl font-bold text-gray-900 mb-6">Pending Funding Requests ({{ $pendingRequests->count() }})</h2>

                @if ($pendingRequests->isEmpty())
                    <div class="text-center py-12">
                        <div class="bg-gray-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <p class="text-gray-500 text-lg">No pending requests to display.</p>
                    </div>
                @else
                    <div class="grid gap-6">
                        @foreach ($pendingRequests as $request)
                            <div class="border border-gray-100 rounded-xl p-6 bg-gray-50 hover:bg-white hover:shadow-md transition-all">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                                    <div>
                                        <span class="inline-block bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full mb-2">
                                            {{ $request->category->category_name ?? 'General' }}
                                        </span>
                                        <h3 class="text-lg font-bold text-gray-900">{{ $request->title }}</h3>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Requested by: <span class="font-semibold text-gray-700">{{ $request->user->fname ?? 'Unknown' }} {{ $request->user->lname ?? '' }}</span> ({{ $request->user->email ?? '' }})
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-2xl font-black text-gray-900">₱{{ number_format($request->amount_requested, 2) }}</p>
                                        <p class="text-xs text-gray-400 mt-1">Submitted: {{ $request->created_at->format('M d, Y') }}</p>
                                    </div>
                                </div>

                                <div class="bg-white border border-gray-100 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $request->description }}</p>
                                </div>

                                <!-- Action Form -->
                                <form method="POST" action="{{ route('admin.fund-request.action', $request->id) }}" class="flex flex-col md:flex-row items-end gap-4 bg-white p-4 border border-gray-100 rounded-lg shadow-sm">
                                    @csrf
                                    <div class="flex-1 w-full">
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Decision Notes</label>
                                        <input name="notes" type="text" placeholder="Add some notes about this decision..." class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#3B82F6] text-sm"/>
                                    </div>
                                    <div class="flex gap-2 w-full md:w-auto">
                                        <button type="submit" name="action" value="rejected" class="flex-1 md:flex-none bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-lg font-bold text-sm transition-colors">
                                            Reject
                                        </button>
                                        <button type="submit" name="action" value="approved" class="flex-1 md:flex-none bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg font-bold text-sm transition-colors">
                                            Approve
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
