@extends('layouts.app')
@section('title', trans('oidc::ui.connections.sidename'))
@section('content')
    <div class="section-title">
        {{ trans('oidc::ui.connections.linked-title') }}
    </div>

    @if ($linkedIdentities->isEmpty())
        <p class="mt-2">{{ trans('oidc::ui.connections.none-linked') }}</p>
    @else
        <div class="mt-2">
            @foreach ($linkedIdentities as $identity)
                <div class="flex justify-between items-center p-2 gap-2 odd:bg-slate-200">
                    <div>{{ $identity->provider->displayName() }}</div>
                    <form method="POST" action="{{ route('plugin-oidc.connections.disconnect', ['identity' => $identity->id]) }}">
                        {{ csrf_field() }}
                        <button type="submit" class="btn-small bg-red-500">
                            {{ trans('oidc::ui.connections.btn.disconnect') }}
                        </button>
                    </form>
                </div>
            @endforeach

            <p class="mt-2 text-sm text-gray-500">{{ trans('oidc::ui.connections.disconnect-hint') }}</p>
        </div>
    @endif

    <div class="section-title mt-10">
        {{ trans('oidc::ui.connections.connect-title') }}
    </div>

    @if ($connectableProviders->isEmpty())
        <p class="mt-2">{{ trans('oidc::ui.connections.none-connectable') }}</p>
    @else
        <div class="mt-2">
            @foreach ($connectableProviders as $provider)
                <div class="flex justify-between items-center p-2 gap-2 odd:bg-slate-200">
                    <div>{{ $provider->displayName() }}</div>
                    <div>
                        <a href="{{ route('plugin-oidc.redirect', ['provider' => $provider->slug, 'intent' => 'link']) }}" class="btn-small bg-emerald-500">
                            {{ trans('oidc::ui.connections.btn.connect') }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
