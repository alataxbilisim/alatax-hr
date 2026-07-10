<x-mail::message>
# {{ __('messages.mail.invitation_heading') }}

{{ __('messages.mail.hello') }},

{!! __('messages.mail.invitation_intro', ['company' => $company->name, 'app' => config('app.name')]) !!}

@if($role)
{!! __('messages.mail.invitation_role', ['role' => $role]) !!}
@endif

<x-mail::button :url="$invitationUrl" color="success">
{{ __('messages.mail.invitation_action') }}
</x-mail::button>

{{ __('messages.mail.invitation_copy') }}

{{ $invitationUrl }}

{{ __('messages.mail.invitation_expire') }}

{{ __('messages.mail.reset_password_salutation') }},<br>
{{ config('app.name') }}
</x-mail::message>
