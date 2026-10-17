@extends('layouts.app')
@section('container', 'max-w-6xl')
@section('title', trim($__env->yieldContent('agent_title', 'Opportunity Agent')))

@section('content')
<div class="agent">
    @yield('agent')
</div>
@endsection
