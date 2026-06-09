@extends('layouts.dashboard')

@section('title', 'Dashboard - Gift of Hope')

@section('content')
<a href="{{ route('logout') }}" onclick="event.preventDefault(); 
       document.getElementById('logout-form').submit();" class="...">
        Logout
    </a>
    <form id="logout-form" action="{{ route('logout') }}" method="POST" hidden>
        @csrf
    </form>


@endsection