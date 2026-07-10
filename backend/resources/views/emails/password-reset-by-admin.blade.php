<x-mail::message>
# {{ __('messages.mail.admin_reset_heading') }}

{{ __('messages.mail.hello_name', ['name' => $user->name]) }},

{{ __('messages.mail.admin_reset_intro') }}

- **{{ __('messages.mail.employee_invitation_email') }}:** {{ $user->email }}
- **{{ __('messages.mail.admin_reset_new_password') }}:** `{{ $newPassword }}`

<x-mail::button :url="$loginUrl" color="success">
{{ __('messages.mail.admin_reset_login') }}
</x-mail::button>

{{ __('messages.mail.admin_reset_hint') }}

{{ __('messages.mail.reset_password_salutation') }},<br>
{{ config('app.name') }}
</x-mail::message>
