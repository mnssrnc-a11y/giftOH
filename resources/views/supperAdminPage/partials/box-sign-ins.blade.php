{{--
    System monitor → Box sign-ins. Each smart donation box signs in to Firebase with its own email
    and password; once the database rules are published, a box can write only its own totals (and
    a total can never go down). See DEPLOY.md → "Protect the donation boxes".
    @param array      $devices   SuperAdminController::devices()
    @param array|null $accounts  box id => uid (null: could not be read)
--}}
@php($issued = session('box_credentials'))
<article class="sa-card" id="monitor-boxes">
    <div class="sa-card-head"><div><h2>Box sign-ins</h2><p>Each box needs its own sign-in so no one else can change the donation totals the release line relies on.</p></div></div>
    <div class="sa-card-body">
        @if ($issued)
            <div class="sa-note" role="status">
                <strong>Sign-in for box {{ $issued['box'] }}: shown once, put it in the box now.</strong><br>
                Email: <code>{{ $issued['email'] }}</code><br>
                Password: <code>{{ $issued['password'] }}</code><br>
                <small>The app does not keep this password. If it is lost, create a new one.</small>
            </div>
        @endif
        @if ($accounts === null)
            <p class="sa-empty">Box sign-ins could not be read right now.</p>
        @elseif ($devices === [])
            <p class="sa-empty">No donation boxes yet.</p>
        @else
            <div class="sa-table-wrap"><table class="sa-table"><thead><tr><th>Box</th><th>Sign-in</th><th>Action</th></tr></thead><tbody>
                @foreach ($devices as $device)
                    @php($has = isset($accounts[$device['id']]))
                    <tr>
                        <td><strong>{{ $device['id'] }}</strong><small>{{ $device['location'] }}</small></td>
                        <td>@if ($has)<span class="sa-badge">Set up</span><small>{{ \App\Services\BoxAccountService::emailFor($device['id']) }}</small>@else<span class="sa-badge amber">Not yet</span>@endif</td>
                        <td>
                            <form method="POST" action="{{ route('superadmin.boxes.sign-in', $device['id']) }}" data-sa-confirm="{{ $has ? 'Give box ' . $device['id'] . ' a new password? The box stops reporting until the new password is put in it.' : 'Create a sign-in for box ' . $device['id'] . '?' }}">
                                @csrf
                                <input type="hidden" name="_section" value="monitor">
                                <button class="sa-link" type="submit">{{ $has ? 'New password' : 'Create sign-in' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody></table></div>
            <p class="sa-muted">When every box reports with its sign-in, publish the protected database rules (DEPLOY.md → “Protect the donation boxes”).</p>
        @endif
    </div>
</article>
