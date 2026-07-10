<x-mail::message>
# Portal Daveti

Merhaba {{ $user->name }},

@if($company)
**{{ $company->name }}** sizi {{ config('app.name') }} personel self-servis portalına davet ediyor.
@else
{{ config('app.name') }} personel self-servis portalına davet edildiniz.
@endif

Geçici giriş bilgileriniz:

- **E-posta:** {{ $user->email }}
- **Geçici şifre:** `{{ $temporaryPassword }}`

<x-mail::button :url="$loginUrl" color="success">
Portala Giriş Yap
</x-mail::button>

@if($inviteUrl)
Daveti kabul edip kendi şifrenizi belirlemek için:

<x-mail::button :url="$inviteUrl">
Daveti Kabul Et
</x-mail::button>
@endif

Güvenliğiniz için ilk girişten sonra şifrenizi değiştirmenizi öneririz.

Saygılarımızla,<br>
{{ config('app.name') }}
</x-mail::message>
