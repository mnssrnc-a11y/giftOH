{{--
    The release line (App\Services\FundQueue): approved requests waiting for their transfer, first
    approved first. The donations on hand cover them in order; nobody skips the line.
    @param array $line     FundQueue::state()
    @param string $route   request page route name (admin or super admin)
--}}
@php($rlPeso = fn ($value) => '₱' . number_format((float) $value, 2))
<div class="rl" data-tour="release-line">
    <dl class="rl-totals">
        <div><dt>Funds on hand</dt><dd>{{ $line['balance'] === null ? 'Unavailable' : $rlPeso($line['balance']) }}</dd><small>Collected minus money transferred</small></div>
        <div><dt>Set aside</dt><dd>{{ $rlPeso($line['set_aside']) }}</dd><small>For the requests covered below</small></div>
        <div><dt>Free to grant</dt><dd>{{ $line['free'] === null ? '—' : $rlPeso($line['free']) }}</dd><small>After every approved request</small></div>
    </dl>
    @if ($line['rows'] === [])
        <p class="rl-empty">No approved request is waiting for its transfer.</p>
    @else
        <ol class="rl-list">
            @foreach ($line['rows'] as $row)
                <li class="{{ $row['ready'] ? 'is-ready' : ($row['covered'] ? 'is-covered' : 'is-waiting') }}">
                    <span class="rl-pos" aria-label="Position {{ $row['position'] }}">{{ $row['position'] }}</span>
                    <div class="rl-copy">
                        <a href="{{ route($route, $row['id']) }}"><strong>{{ $row['organization'] }}</strong></a>
                        <small>{{ $rlPeso($row['amount']) }} · approved {{ $row['approved_at'] ? \Carbon\Carbon::parse($row['approved_at'])->format('M d, Y') : '—' }}</small>
                    </div>
                    <span class="rl-state">
                        @if ($row['ready']) Ready to transfer
                        @elseif ($row['covered']) Covered · waiting for the list of people
                        @elseif ($line['balance'] === null) Funds unavailable
                        @else Waiting for donations · {{ $rlPeso($row['short']) }} more
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
