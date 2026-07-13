<x-mail::message>
# {{ __('messages.mail.employee_invitation_heading') }}

@if(!empty($companyLogoUrl))
<div style="text-align:center;margin-bottom:16px;">
<img src="{{ $companyLogoUrl }}" alt="{{ $company?->name }}" style="max-height:48px;max-width:180px;">
</div>
@endif

{{ __('messages.mail.hello_name', ['name' => $user->name]) }},

@if($company)
{!! __('messages.mail.employee_invitation_intro', ['company' => $company->name, 'app' => config('app.name')]) !!}
@else
{{ __('messages.mail.employee_invitation_intro_generic', ['app' => config('app.name')]) }}
@endif

@if($temporaryPassword)
{{ __('messages.mail.employee_invitation_credentials') }}

- **{{ __('messages.mail.employee_invitation_email') }}:** {{ $user->email }}
- **{{ __('messages.mail.employee_invitation_temp_password') }}:** `{{ $temporaryPassword }}`

<x-mail::button :url="$loginUrl" color="success">
{{ __('messages.mail.employee_invitation_login') }}
</x-mail::button>

{{ __('messages.mail.employee_invitation_change_hint') }}
@endif

@if($inviteUrl)
@if(!$temporaryPassword)
{{ __('messages.mail.employee_invitation_set_password_hint') }}
@endif

<x-mail::button :url="$inviteUrl">
{{ __('messages.mail.employee_invitation_accept') }}
</x-mail::button>
@endif

{{ __('messages.mail.reset_password_salutation') }},<br>
{{ config('app.name') }}
</x-mail::message>
