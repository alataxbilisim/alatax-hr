<x-mail::message>
# {{ $heading }}

@if($recipientName)
{{ __('messages.mail.hello_name', ['name' => $recipientName]) }},
@else
{{ __('messages.mail.hello') }},
@endif

{{ $body }}

<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>

{{ __('messages.mail.reset_password_salutation') }},<br>
{{ config('app.name') }}
</x-mail::message>
