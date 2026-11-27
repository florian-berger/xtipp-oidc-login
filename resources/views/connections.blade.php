@extends('layouts.app')
@section('title', trans('oidc::ui.connections.sidename'))
@section('content')
    {{-- Plain explanation for users without technical knowledge; padding-left inline, xTIPP's CSS has no pl-5 --}}
    <section class="bg-slate-200 rounded p-3 mb-8" aria-labelledby="plugin-oidc-intro-title">
        <h2 id="plugin-oidc-intro-title" class="font-bold mb-2">{{ trans('oidc::ui.connections.intro.title') }}</h2>
        <p>{{ trans('oidc::ui.connections.intro.text') }}</p>
        <ul class="list-disc mt-2" style="padding-left: 1.25rem;">
            @foreach (trans('oidc::ui.connections.intro.points') as $point)
                <li>{{ $point }}</li>
            @endforeach
        </ul>
    </section>

    <div class="section-title">
        {{ trans('oidc::ui.connections.linked-title') }}
    </div>

    @if ($linkedIdentities->isEmpty())
        <p class="mt-2">{{ trans('oidc::ui.connections.none-linked') }}</p>
    @else
        <div class="mt-2">
            @foreach ($linkedIdentities as $identity)
                <div class="flex justify-between items-center p-2 gap-2 odd:bg-slate-200">
                    <div>
                        {{ $identity->provider->displayName() }}
                        @if ($identity->created_at)
                            <i>{{ trans('oidc::ui.connections.linked-since', ['date' => $identity->created_at->format('d.m.Y, H:i')]) }}</i>
                        @endif
                    </div>
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
