@extends('app')
@section('body')

    <div class="sidebar">
        @yield('sidebar')
    </div>
    <div class="content">
        @yield('content')
    </div>

@endsection