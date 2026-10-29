{{--
    The consent screen of a Claude connection (U-43), shown by Passport after the owner signed in. It is a plain page
    in the guest look, not a Livewire page: the two buttons post to Passport's approve and deny routes. The OAuth
    `state` is kept by Passport in the session, so the forms only carry the client and the one-time auth token.
--}}
@php
    $redirectUri = (string) $request->query('redirect_uri', '');
    $returnsTo = $redirectUri === '' ? null : (parse_url($redirectUri, PHP_URL_HOST) ?: $redirectUri);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.head', ['title' => __('mcp.authorize.title')])
</head>
<body>
    <main class="ui-guest">
        <div class="ui-guest-card ui-pane ui-glass-strong">
            <x-ui.brand />

            <div class="flex flex-col gap-4">
                <h1 class="ui-m-0">{{ __('mcp.authorize.heading', ['name' => $client->name]) }}</h1>
                <p class="ui-hint ui-m-0">{{ __('mcp.authorize.lead') }}</p>

                <div class="ui-note flex flex-col gap-1">
                    <span>{{ __('mcp.authorize.read_only') }}</span>
                    <span>{{ __('mcp.authorize.no_delete') }}</span>
                </div>

                <p class="ui-hint ui-m-0">
                    {{ __('mcp.authorize.signed_in_as') }} <strong>{{ $user->email }}</strong>
                    @if ($returnsTo)
                        <br>{{ __('mcp.authorize.returns_to') }} <strong>{{ $returnsTo }}</strong>
                    @endif
                </p>

                <div class="flex gap-3">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="{{ $request->query('state', '') }}">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <x-ui.button type="submit" class="w-full">{{ __('mcp.authorize.deny') }}</x-ui.button>
                    </form>

                    <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="state" value="{{ $request->query('state', '') }}">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <x-ui.button type="submit" variant="primary" class="w-full">{{ __('mcp.authorize.approve') }}</x-ui.button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
