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

            <!-- AI Scoring Legend -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">AI Priority Scoring Guide</h3>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full bg-red-500"></span>
                        <span class="text-gray-600"><strong class="text-red-700">75–100</strong> Critical — Must fund</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full bg-amber-500"></span>
                        <span class="text-gray-600"><strong class="text-amber-700">50–74</strong> High — Should fund</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full bg-emerald-500"></span>
                        <span class="text-gray-600"><strong class="text-emerald-700">25–49</strong> Moderate — Fund if available</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full bg-gray-400"></span>
                        <span class="text-gray-600"><strong class="text-gray-500">0–24</strong> Low — Not urgent</span>
                    </div>
                </div>
            </div>

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
                            @php
                                $score = $request->ai_score;
                                $breakdown = $request->ai_score_breakdown;
                                $hasScore = !is_null($score);

                                // Determine color theme based on score
                                if ($hasScore && $score >= 75) {
                                    $badgeBg = 'bg-red-100'; $badgeText = 'text-red-800'; $badgeBorder = 'border-red-200';
                                    $ringColor = 'text-red-500'; $label = 'Critical'; $labelBg = 'bg-red-600';
                                    $barColor = 'bg-red-500'; $glowBorder = 'border-l-red-500';
                                } elseif ($hasScore && $score >= 50) {
                                    $badgeBg = 'bg-amber-100'; $badgeText = 'text-amber-800'; $badgeBorder = 'border-amber-200';
                                    $ringColor = 'text-amber-500'; $label = 'High Priority'; $labelBg = 'bg-amber-500';
                                    $barColor = 'bg-amber-500'; $glowBorder = 'border-l-amber-500';
                                } elseif ($hasScore && $score >= 25) {
                                    $badgeBg = 'bg-emerald-100'; $badgeText = 'text-emerald-800'; $badgeBorder = 'border-emerald-200';
                                    $ringColor = 'text-emerald-500'; $label = 'Moderate'; $labelBg = 'bg-emerald-500';
                                    $barColor = 'bg-emerald-500'; $glowBorder = 'border-l-emerald-500';
                                } else {
                                    $badgeBg = 'bg-gray-100'; $badgeText = 'text-gray-600'; $badgeBorder = 'border-gray-200';
                                    $ringColor = 'text-gray-400'; $label = $hasScore ? 'Low Priority' : 'Not Scored'; $labelBg = 'bg-gray-400';
                                    $barColor = 'bg-gray-400'; $glowBorder = 'border-l-gray-300';
                                }
                            @endphp

                            <div class="border border-gray-100 rounded-xl p-6 bg-gray-50 hover:bg-white hover:shadow-md transition-all border-l-4 {{ $glowBorder }}">
                                {{-- Top row: category + title + score badge --}}
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-block bg-blue-100 text-blue-800 text-xs font-semibold px-2.5 py-0.5 rounded-full">
                                                {{ $request->category->category_name ?? 'General' }}
                                            </span>
                                            @if ($hasScore)
                                                <span class="inline-block {{ $labelBg }} text-white text-xs font-bold px-2.5 py-0.5 rounded-full">
                                                    {{ $label }}
                                                </span>
                                            @endif
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-900">{{ $request->title }}</h3>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Requested by: <span class="font-semibold text-gray-700">{{ $request->user->fname ?? 'Unknown' }} {{ $request->user->lname ?? '' }}</span> ({{ $request->user->email ?? '' }})
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-6">
                                        {{-- AI Score Circle --}}
                                        @if ($hasScore)
                                            <div class="flex flex-col items-center">
                                                <div class="relative w-16 h-16">
                                                    <svg class="w-16 h-16 transform -rotate-90" viewBox="0 0 64 64">
                                                        <circle cx="32" cy="32" r="28" stroke-width="4" fill="none" class="text-gray-200" stroke="currentColor"/>
                                                        <circle cx="32" cy="32" r="28" stroke-width="4" fill="none"
                                                            class="{{ $ringColor }}" stroke="currentColor"
                                                            stroke-dasharray="{{ 175.93 }}"
                                                            stroke-dashoffset="{{ 175.93 - (175.93 * $score / 100) }}"
                                                            stroke-linecap="round"/>
                                                    </svg>
                                                    <div class="absolute inset-0 flex items-center justify-center">
                                                        <span class="text-lg font-black {{ $badgeText }}">{{ round($score) }}</span>
                                                    </div>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-500 mt-1">AI Score</span>
                                            </div>
                                        @else
                                            <div class="flex flex-col items-center">
                                                <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center">
                                                    <span class="text-xs text-gray-400 font-semibold">N/A</span>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-400 mt-1">No Score</span>
                                            </div>
                                        @endif

                                        <div class="text-right">
                                            <p class="text-2xl font-black text-gray-900">₱{{ number_format($request->amount_requested, 2) }}</p>
                                            <p class="text-xs text-gray-400 mt-1">Submitted: {{ $request->created_at->format('M d, Y') }}</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <div class="bg-white border border-gray-100 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $request->description }}</p>
                                </div>

                                {{-- AI Score Breakdown (collapsible) --}}
                                @if ($hasScore && $breakdown)
                                    <details class="mb-4 group">
                                        <summary class="cursor-pointer flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 transition-colors select-none">
                                            <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                            View AI Score Breakdown
                                        </summary>
                                        <div class="mt-3 bg-white border border-gray-100 rounded-lg p-5">
                                            {{-- Dimension progress bars --}}
                                            @if (isset($breakdown['breakdown']))
                                                @php
                                                    $dimensions = [
                                                        'urgency' => ['label' => 'Urgency', 'weight' => '25%', 'icon' => '⏰'],
                                                        'impact' => ['label' => 'Impact', 'weight' => '25%', 'icon' => '🎯'],
                                                        'need_severity' => ['label' => 'Need Severity', 'weight' => '20%', 'icon' => '🔥'],
                                                        'feasibility' => ['label' => 'Feasibility', 'weight' => '15%', 'icon' => '✅'],
                                                        'category_fit' => ['label' => 'Category Fit', 'weight' => '15%', 'icon' => '📋'],
                                                    ];
                                                @endphp
                                                <div class="space-y-3">
                                                    @foreach ($dimensions as $key => $dim)
                                                        @php $dimScore = $breakdown['breakdown'][$key] ?? 0; @endphp
                                                        <div>
                                                            <div class="flex items-center justify-between text-sm mb-1">
                                                                <span class="font-medium text-gray-700">
                                                                    {{ $dim['icon'] }} {{ $dim['label'] }}
                                                                    <span class="text-gray-400 text-xs">({{ $dim['weight'] }})</span>
                                                                </span>
                                                                <span class="font-bold {{ $dimScore >= 75 ? 'text-red-600' : ($dimScore >= 50 ? 'text-amber-600' : ($dimScore >= 25 ? 'text-emerald-600' : 'text-gray-500')) }}">
                                                                    {{ round($dimScore) }}/100
                                                                </span>
                                                            </div>
                                                            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                                                <div class="h-2.5 rounded-full transition-all duration-500 {{ $dimScore >= 75 ? 'bg-red-500' : ($dimScore >= 50 ? 'bg-amber-500' : ($dimScore >= 25 ? 'bg-emerald-500' : 'bg-gray-400')) }}"
                                                                    style="width: {{ $dimScore }}%"></div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif

                                            {{-- AI Reasoning --}}
                                            @if (isset($breakdown['reasoning']))
                                                <div class="mt-4 pt-4 border-t border-gray-100">
                                                    <div class="flex items-start gap-2">
                                                        <svg class="w-4 h-4 text-indigo-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                                        </svg>
                                                        <div>
                                                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">AI Reasoning</p>
                                                            <p class="text-sm text-gray-700">{{ $breakdown['reasoning'] }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </details>
                                @endif

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
