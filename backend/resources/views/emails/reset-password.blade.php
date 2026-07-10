<x-mail::message>
# {{ __('messages.mail.reset_password_heading') }}

@if(isset($user) && $user->name)
{{ __('messages.mail.hello_name', ['name' => $user->name]) }},
@else
{{ __('messages.mail.hello') }},
@endif

{{ __('messages.mail.reset_password_intro') }}

<x-mail::button :url="$url" color="success">
{{ __('messages.mail.reset_password_action') }}
</x-mail::button>

{!! __('messages.mail.reset_password_expire', ['minutes' => $expireMinutes]) !!}

{{ __('messages.mail.reset_password_salutation') }},<br>
{{ config('app.name') }}
</x-mail::message>
