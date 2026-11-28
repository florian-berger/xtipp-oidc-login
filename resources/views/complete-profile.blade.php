@extends('layouts.app')
@section('title', trans('oidc::ui.complete_profile.sidename'))
@section('content')
    <div class="mx-auto w-full md:w-1/2">
        <p class="mb-5">{{ trans('oidc::ui.complete_profile.intro') }}</p>

        <form action="{{ route('plugin-oidc.complete_profile.store') }}" method="POST">
            {{ csrf_field() }}

            @if (config('xtipp.user-nickname') === 1)
                <div class="w-full{{ $errors->has('nickname') ? ' has-error' : '' }}">
                    <label for="nickname" class="label-normal-gray">{{ trans('oidc::ui.complete_profile.nickname') }}</label>
                    <input id="nickname" type="text" class="input-normal-gray" name="nickname" value="{{ old('nickname', $pending['nickname'] ?? null) }}" required
                           @if ($errors->has('nickname')) aria-invalid="true" aria-describedby="error-nickname-msg" @endif>

                    @if ($errors->has('nickname'))
                        <span id="error-nickname-msg" class="error-text" role="alert">
                            {{ $errors->first('nickname') }}
                        </span>
                    @endif
                </div>
            @endif

            @if (config('xtipp.user-first-last-name') === 1)
                <div class="w-full mt-5{{ $errors->has('firstname') ? ' has-error' : '' }}">
                    <label for="firstname" class="label-normal-gray">{{ trans('oidc::ui.complete_profile.firstname') }}</label>
                    <input id="firstname" type="text" class="input-normal-gray" name="firstname" value="{{ old('firstname', $pending['given_name']) }}" required
                           @if ($errors->has('firstname')) aria-invalid="true" aria-describedby="error-firstname-msg" @endif>

                    @if ($errors->has('firstname'))
                        <span id="error-firstname-msg" class="error-text" role="alert">
                            {{ $errors->first('firstname') }}
                        </span>
                    @endif
                </div>

                <div class="w-full mt-5{{ $errors->has('name') ? ' has-error' : '' }}">
                    <label for="name" class="label-normal-gray">{{ trans('oidc::ui.complete_profile.name') }}</label>
                    <input id="name" type="text" class="input-normal-gray" name="name" value="{{ old('name', $pending['family_name']) }}" required
                           @if ($errors->has('name')) aria-invalid="true" aria-describedby="error-name-msg" @endif>

                    @if ($errors->has('name'))
                        <span id="error-name-msg" class="error-text" role="alert">
                            {{ $errors->first('name') }}
                        </span>
                    @endif
                </div>
            @endif

            {{-- A provided email is taken from the verified claims of the session, never from this form --}}
            @if ($pending['email'])
                <div class="w-full mt-5">
                    <label class="label-normal-gray">{{ trans('oidc::ui.complete_profile.email') }}</label>
                    <input type="text" class="input-normal-gray" value="{{ $pending['email'] }}" disabled>
                </div>
            @else
                <div class="w-full mt-5{{ $errors->has('email') ? ' has-error' : '' }}">
                    <label for="email" class="label-normal-gray">{{ trans('oidc::ui.complete_profile.email') }}</label>
                    <input id="email" type="email" class="input-normal-gray" name="email" value="{{ old('email') }}" required
                           @if ($errors->has('email')) aria-invalid="true" aria-describedby="error-email-msg" @endif>

                    @if ($errors->has('email'))
                        <span id="error-email-msg" class="error-text" role="alert">
                            {{ $errors->first('email') }}
                        </span>
                    @endif
                </div>
            @endif

            <div class="mt-5 text-center">
                <button type="submit" class="btn-normal">
                    {{ trans('oidc::ui.complete_profile.submit') }}
                </button>
            </div>
        </form>
    </div>
@endsection
