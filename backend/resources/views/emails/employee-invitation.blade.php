<x-mail::message>
# {{ __('messages.mail.employee_invitation_heading') }}

{{ __('messages.mail.hello_name', ['name' => $user->name]) }},

@if($company)
{!! __('messages.mail.employee_invitation_intro', ['company' => $company->name, 'app' => config('app.name')]) !!}
@else
{{ __('messages.mail.employee_invitation_intro_generic', ['app' => config('app.name')]) }}
@endif

{{ __('messages.mail.employee_invitation_credentials') }}

- **{{ __('messages.mail.employee_invitation_email') }}:** {{ $user->email }}
- **{{ __('messages.mail.employee_invitation_temp_password') }}:** `{{ $temporaryPassword }}`

<x-mail::button :url="$loginUrl" color="success">
{{ __('messages.mail.employee_invitation_login') }}
</x-mail::button>

@if($inviteUrl)
<x-mail::button :url="$inviteUrl">
{{ __('messages.mail.employee_invitation_accept') }}
</x-mail::button>
@endif

{{ __('messages.mail.employee_invitation_change_hint') }}

{{ __('messages.mail.reset_password_salutation') }},<br>
{{ config('app.name') }}
</x-mail::message>
