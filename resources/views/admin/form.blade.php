@php($isNew = !$oidcProvider->exists)
@extends('layouts.app')
@section('title', trans($isNew ? 'oidc::ui.admin.form.sidename-new' : 'oidc::ui.admin.form.sidename-edit'))
@section('content')
    <div class="view-wrapper" data-view="plugin-oidc-form">
        <div class="mb-10 text-right">
            <a href="{{ route('plugin-oidc.admin.index') }}" title="{{ trans('oidc::ui.admin.form.back_overview') }}" class="btn-normal">{{ trans('oidc::ui.admin.form.back_overview') }}</a>
        </div>

        @if (session()->has('message'))
            <div class="success-message my-2">
                {{ session('message') }}
            </div>
        @endif

        @if (count($errors) > 0)
            <div class="error-message">
                {!! trans('messages.mistake') !!}<br /><br />
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ $isNew ? route('plugin-oidc.admin.create') : route('plugin-oidc.admin.update') }}" method="post">
            {{ csrf_field() }}
            @unless ($isNew)
                <input type="hidden" name="oidc_provider_id" value="{{ $oidcProvider->id }}" />
            @endunless

            <div class="grid grid-cols-1 md:grid-cols-2 md:gap-5">
                <div>
                    <label for="name" class="label-normal-gray">{{ trans('oidc::ui.admin.form.name') }}</label>
                    <input type="text" name="name" value="{{ old('name', $oidcProvider->name) }}" id="name" class="input-normal-gray" />
                </div>
                <div>
                    <label for="slug" class="label-normal-gray">{{ trans('oidc::ui.admin.form.slug') }}</label>
                    <input type="text" name="slug" value="{{ old('slug', $oidcProvider->slug) }}" id="slug" class="input-normal-gray" />
                </div>
            </div>
            <div>
                <label for="issuer" class="label-normal-gray">{{ trans('oidc::ui.admin.form.issuer') }}</label>
                <input type="text" name="issuer" value="{{ old('issuer', $oidcProvider->issuer) }}" id="issuer" class="input-normal-gray" placeholder="https://" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 md:gap-5">
                <div>
                    <label for="client_id" class="label-normal-gray">{{ trans('oidc::ui.admin.form.client_id') }}</label>
                    <input type="text" name="client_id" value="{{ old('client_id', $oidcProvider->client_id) }}" id="client_id" class="input-normal-gray" />
                </div>
                <div>
                    <label for="client_secret" class="label-normal-gray">{{ trans('oidc::ui.admin.form.client_secret') }}</label>
                    <input type="password" name="client_secret" value="" id="client_secret" class="input-normal-gray" autocomplete="off"
                           @unless ($isNew) placeholder="{{ trans('oidc::ui.admin.form.client_secret_keep') }}" @endunless />
                </div>
            </div>
            <div>
                <label for="scopes" class="label-normal-gray">{{ trans('oidc::ui.admin.form.scopes') }}</label>
                <input type="text" name="scopes" value="{{ old('scopes', $oidcProvider->scopes) }}" id="scopes" class="input-normal-gray" />
            </div>
            <div class="mt-5{{ $errors->has('extra_authorize_params') ? ' has-error' : '' }}">
                <label for="extra_authorize_params" class="label-normal-gray">{{ trans('oidc::ui.admin.form.extra_authorize_params') }}</label>
                <textarea name="extra_authorize_params" id="extra_authorize_params" class="input-normal-gray h-20">{{ old('extra_authorize_params', $oidcProvider->extra_authorize_params ? json_encode($oidcProvider->extra_authorize_params) : '') }}</textarea>
                <p class="text-sm text-gray-500">{{ trans('oidc::ui.admin.form.extra_authorize_params_help') }}</p>
            </div>
            <div class="mt-5">
                <label>
                    <input type="hidden" name="active" value="0">
                    <input type="checkbox" name="active" value="1" @checked(old('active', $oidcProvider->active))> {{ trans('oidc::ui.admin.form.active') }}
                </label>
            </div>

            <div class="mt-8">
                <h2 class="text-lg font-bold">{{ trans('oidc::ui.admin.form.appearance') }}</h2>
                <hr class="mt-1 mb-5" />

                <div class="grid grid-cols-1 md:grid-cols-2 md:gap-5">
                    <div>
                        <div>
                            <label for="button_label" class="label-normal-gray">{{ trans('oidc::ui.admin.form.button_label') }}</label>
                            <input type="text" name="button_label" value="{{ old('button_label', $oidcProvider->button_label) }}" id="button_label" class="input-normal-gray" />
                        </div>
                        <div class="mt-5">
                            <label for="button_color" class="label-normal-gray">{{ trans('oidc::ui.admin.form.button_color') }}</label>
                            <div class="flex items-center gap-2">
                                <input type="color" id="button_color_picker" value="{{ old('button_color', $oidcProvider->button_color) ?: '#4f46e5' }}" class="cursor-pointer" style="height: 2.5rem; width: 3.5rem; padding: 0; border: 0;" />
                                <input type="text" name="button_color" value="{{ old('button_color', $oidcProvider->button_color) }}" id="button_color" class="input-normal-gray" placeholder="#4F46E5" maxlength="7" />
                            </div>
                        </div>
                        <div class="mt-5">
                            <label for="icon" class="label-normal-gray">{{ trans('oidc::ui.admin.form.icon') }}</label>
                            <input type="text" name="icon" value="{{ old('icon', $oidcProvider->icon) }}" id="icon" class="input-normal-gray" placeholder="fa-brands fa-twitch" />
                            <p class="text-sm text-gray-500">{{ trans('oidc::ui.admin.form.icon_help') }}</p>
                        </div>
                    </div>
                    <div>
                        <span class="label-normal-gray">{{ trans('oidc::ui.admin.form.preview') }}</span>
                        <span id="oidc_button_preview" class="btn-normal inline-block">
                            <i id="oidc_button_preview_icon" class="hidden mr-2"></i>
                            <span id="oidc_button_preview_label"></span>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap sm:justify-end">
                <div class="mt-1 order-1 sm:order-2 w-full sm:w-1/5">
                    <button type="submit" class="btn-normal bg-emerald-500">{{ trans($isNew ? 'oidc::ui.admin.form.create' : 'oidc::ui.admin.form.save') }}</button>
                </div>
            </div>
        </form>
    </div>

    <script>
        (function () {
            var nameInput     = document.getElementById('name');
            var labelInput    = document.getElementById('button_label');
            var colorText     = document.getElementById('button_color');
            var colorPicker   = document.getElementById('button_color_picker');
            var iconInput     = document.getElementById('icon');
            var preview       = document.getElementById('oidc_button_preview');
            var previewIcon   = document.getElementById('oidc_button_preview_icon');
            var previewLabel  = document.getElementById('oidc_button_preview_label');
            var labelTemplate = @json(trans('oidc::ui.login_with', ['provider' => '__PROVIDER__']));

            function updatePreview() {
                var providerName = (labelInput.value.trim() || nameInput.value.trim() || 'Provider');
                previewLabel.textContent = labelTemplate.replace('__PROVIDER__', providerName);

                var color = colorText.value.trim();
                if (/^#[0-9A-Fa-f]{6}$/.test(color)) {
                    preview.style.backgroundColor = color;
                    colorPicker.value = color;
                } else {
                    preview.style.backgroundColor = '';
                }

                var iconClass = iconInput.value.trim();
                previewIcon.className = iconClass ? iconClass + ' mr-2' : 'hidden mr-2';
            }

            colorPicker.addEventListener('input', function () {
                colorText.value = colorPicker.value;
                updatePreview();
            });

            [nameInput, labelInput, colorText, iconInput].forEach(function (el) {
                el.addEventListener('input', updatePreview);
            });

            updatePreview();
        })();
    </script>
@endsection
