@extends('layouts.admin')

@section('title', 'Donations - Gift of Hope')
@section('page-kicker', 'Monitoring')
@section('page-title', 'Donations')

@section('content')
@php
    $peso = fn ($value) => '₱' . number_format((float) $value, 2);
    $day = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('M d, Y g:i A') : '—';
@endphp

<div class="admin-page-head">
    <div><h2>Donations</h2><p>Money collected by the smart boxes and donations recorded in Firebase.</p></div>
    <a class="admin-button" href="{{ route('iot-monitor') }}">Open IoT monitor</a>
</div>

<div class="admin-grid admin-funding-summary">
    <article><span>Total funds</span><strong>{{ is_null($totalFunds) ? '—' : $peso($totalFunds) }}</strong><small>Boxes + recorded donations</small></article>
    <article><span>Collected by boxes</span><strong>{{ $peso($boxTotal) }}</strong><small>{{ count($boxes) }} smart box(es)</small></article>
    <article><span>Recorded donations</span><strong>{{ $peso($directTotal) }}</strong><small>{{ count($donations) }} record(s)</small></article>
    <article><span>Available to grant</span><strong>{{ is_null($availableFunds) ? '—' : $peso($availableFunds) }}</strong><small>After approved grants</small></article>
</div>

<div class="admin-grid admin-two-col">
    <article class="admin-card">
        <div class="admin-card-head"><div><h3>Donation records</h3><p>Newest first</p></div></div>
        @if (! $donationsAvailable)
            <div class="admin-empty admin-state-visible"><strong>Donation records could not be loaded</strong><p>Firebase did not respond. Try again shortly.</p></div>
        @elseif ($donations)
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Date</th><th>Source</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody>
                @foreach ($donations as $donation)
                    <tr><td>{{ $day($donation['date']) }}<small>{{ \Illuminate\Support\Str::limit($donation['id'], 12) }}</small></td>
                        <td>{{ $donation['box'] ? 'Box ' . $donation['box'] : ($donation['donor'] ?? 'Direct') }}</td>
                        <td>{{ ucfirst($donation['type']) }}</td><td><strong>{{ $peso($donation['amount']) }}</strong></td>
                        <td><span class="admin-status {{ in_array($donation['status'], ['verified', 'completed', 'paid'], true) ? 'approved' : '' }}">{{ $donation['status'] }}</span></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="admin-empty admin-state-visible"><strong>No donation records yet</strong><p>Records appear here as donations are logged in Firebase. Box collections are shown on the right.</p></div>
        @endif
    </article>

    <article class="admin-card">
        <div class="admin-card-head"><div><h3>Smart box collections</h3><p>Running totals reported by each box</p></div></div>
        @if ($boxes)
            <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Box</th><th>Location</th><th>Coins</th><th>Collected</th></tr></thead><tbody>
                @foreach ($boxes as $box)
                    <tr><td><strong>{{ $box['id'] }}</strong></td><td>{{ $box['location'] }}</td><td>{{ number_format($box['coins']) }}</td><td><strong>{{ $peso($box['total']) }}</strong></td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="admin-empty admin-state-visible"><strong>No smart boxes reported yet</strong></div>
        @endif
    </article>
</div>
@endsection
