@extends('layouts.app')
@section('title', trans('oidc::ui.login.sidename'))
@section('content')
    @include('oidc::partials.styles')
    <div class="mx-auto w-full md:w-1/2 text-center">
        @forelse ($oidcProviders as $oidcProvider)
            @include('oidc::partials.button', ['oidcProvider' => $oidcProvider])
        @empty
            <p>{{ trans('oidc::ui.login.none') }}</p>
        @endforelse

        <div class="mt-6">
            <a href="{{ route('login') }}" class="link-normal">{{ trans('oidc::ui.login.password') }}</a>
        </div>
    </div>
@endsection
