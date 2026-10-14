{{--
    Phone navigation: a bar at the bottom of the screen with the main places of the workspace,
    Notifications (with the unread count) and Menu (opens the full sidebar).
    @param array $tabs  [['href' => url, 'icon' => '⌂', 'label' => 'Home', 'match' => 'route.name' | '#section', 'attrs' => ''], ...]
--}}
@php($tabCount = count($navNotifications ?? []))
<nav class="ws-tabbar" aria-label="Quick navigation" data-tabbar data-tour="tabbar">
    @foreach ($tabs as $tab)
        @php($current = isset($tab['route']) && request()->routeIs($tab['route']))
        <a href="{{ $tab['href'] }}" class="ws-tab" @if ($current) aria-current="page" @endif {!! $tab['attrs'] ?? '' !!} @isset($tab['section']) data-tab-section="{{ $tab['section'] }}" data-tour="tab-{{ $tab['section'] }}" @endisset>
            <span class="ws-tab-icon" aria-hidden="true">{{ $tab['icon'] }}</span><span>{{ $tab['label'] }}</span>
        </a>
    @endforeach
    <a href="{{ route('notifications') }}" class="ws-tab" data-tour="tab-alerts" @if (request()->routeIs('notifications')) aria-current="page" @endif>
        <span class="ws-tab-icon" aria-hidden="true">◔@if ($tabCount)<b>{{ $tabCount > 99 ? '99+' : $tabCount }}</b>@endif</span><span>Alerts</span><span class="sr-only">{{ $tabCount ? "({$tabCount} unread)" : '' }}</span>
    </a>
    <button type="button" class="ws-tab" data-tab-menu data-tour="tab-menu" aria-label="Open the full menu"><span class="ws-tab-icon" aria-hidden="true">☰</span><span>Menu</span></button>
</nav>
