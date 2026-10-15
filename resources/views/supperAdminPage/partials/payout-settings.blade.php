{{--
    System settings → Bank transfers: how automatic (bank-to-bank / e-wallet) transfers are
    connected, the payout account balance, and the payout channel of each bank / e-wallet.
    @param array $p  SuperAdminController::payoutSettings()
--}}
@php
    $badge = ['live' => 'red', 'test' => 'amber', 'sandbox' => 'amber', 'manual' => 'gray'][$p['mode']] ?? 'gray';
    $available = collect($p['available']);
@endphp
<article class="sa-card" id="settings-payouts">
    <div class="sa-card-head"><div><h2>Bank transfers</h2><p>Send approved funds straight to the receiver's bank account or e-wallet (InstaPay / PESONet), confirmed with a code emailed to you.</p></div>
        <span class="sa-badge {{ $badge }}">{{ $p['mode_label'] }}</span></div>
    <div class="sa-card-body">
        <dl class="sa-facts" style="margin-bottom:16px">
            <div><dt>Connection</dt><dd>@switch ($p['driver'])
                @case('xendit') Xendit Payouts API @break
                @case('sandbox') Practice bank (sandbox) @break
                @default Not connected: admins send transfers and record them @endswitch
                <small>PAYOUT_DRIVER={{ $p['driver'] }}</small></dd></div>
            <div><dt>Payout account</dt><dd>{{ $p['balance'] !== null ? '₱' . number_format($p['balance'], 2) : '—' }}<small>{{ $p['mode'] === 'live' ? 'Real money in the Xendit balance' : ($p['enabled'] ? 'Practice money, not real' : 'Connect a provider to see it') }}</small></dd></div>
            @if ($p['driver'] === 'xendit')
                <div><dt>API key</dt><dd>{{ $p['key_set'] ? 'Set (' . ($p['mode'] === 'live' ? 'live' : 'test') . ')' : 'Missing: XENDIT_SECRET_KEY' }}</dd></div>
                <div><dt>Webhook</dt><dd>{{ $p['webhook_set'] ? 'Token set' : 'Missing: XENDIT_WEBHOOK_TOKEN' }}<small>{{ $p['webhook_url'] }}</small></dd></div>
            @endif
        </dl>

        @if ($p['mode'] === 'sandbox')
            <div class="sa-note">Sandbox: the whole transfer works (send, code, confirmation, release recorded) but <strong>no real money moves</strong>. Account numbers 999999, 121212 and 123456 fail on purpose, like Xendit's test mode.</div>
        @elseif ($p['mode'] === 'test')
            <div class="sa-note">Xendit test mode: transfers are simulated. Switch to a live key (xnd_production_…) to send real money.</div>
        @elseif ($p['mode'] === 'live')
            <div class="sa-note sa-note-error">Live: transfers send real money from the Xendit balance.</div>
        @endif
        @if ($p['error'])<div class="sa-note sa-note-error">The bank list could not be read: {{ $p['error'] }}</div>@endif

        @if ($p['enabled'])
            <form method="POST" action="{{ route('superadmin.settings.payouts.test') }}" class="sa-inline-form" style="margin:14px 0">@csrf<button class="sa-button secondary" type="submit">Test the connection</button></form>
        @endif

        @if ($p['driver'] === 'xendit')
            <form method="POST" action="{{ route('superadmin.settings.payouts') }}" data-sa-confirm="Save the payout channel of each bank and e-wallet?">
                @csrf
                <input type="hidden" name="_section" value="settings-payouts">
                <p class="sa-muted">The channel each bank / e-wallet uses. Pick it from the list Xendit returns for your account; a bank with no channel is sent by hand.</p>
                <div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Bank / e-wallet</th><th>Payout channel</th></tr></thead><tbody>
                    @foreach ($p['providers'] as $mode => $names)
                        @foreach ($names as $provider)
                            <tr><td>{{ $provider }}<small>{{ $mode === 'e_wallet' ? 'E-wallet' : 'Bank' }}</small></td>
                                <td>
                                    @if ($available->isNotEmpty())
                                        <select name="channels[{{ $provider }}]" aria-label="Payout channel for {{ $provider }}">
                                            <option value="">Not sent automatically</option>
                                            @foreach ($available as $channel)<option value="{{ $channel['code'] }}" @selected(($p['codes'][$provider] ?? null) === $channel['code'])>{{ $channel['name'] }} ({{ $channel['code'] }})</option>@endforeach
                                        </select>
                                    @else
                                        <input name="channels[{{ $provider }}]" value="{{ $p['saved'][$provider] ?? $p['codes'][$provider] ?? '' }}" placeholder="e.g. PH_BDO" maxlength="40" aria-label="Payout channel for {{ $provider }}">
                                    @endif
                                </td></tr>
                        @endforeach
                    @endforeach
                </tbody></table></div>
                <div class="sa-actions"><button class="sa-button">Save channels</button></div>
            </form>
        @elseif ($p['driver'] === 'sandbox')
            <p class="sa-muted">Every bank and e-wallet on the request form has a practice channel.</p>
        @else
            <p class="sa-muted">To connect: set PAYOUT_DRIVER=sandbox to practice, or PAYOUT_DRIVER=xendit with XENDIT_SECRET_KEY and XENDIT_WEBHOOK_TOKEN for real transfers. See DEPLOY.md → “Automatic transfers”.</p>
        @endif
    </div>
</article>
