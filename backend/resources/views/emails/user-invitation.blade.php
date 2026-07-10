<x-mail::message>
# Sistem Daveti

Merhaba,

**{{ $company->name }}** sizi {{ config('app.name') }} sistemine davet ediyor.

@if($role)
Size atanan rol: **{{ $role }}**
@endif

<x-mail::button :url="$invitationUrl" color="success">
Daveti Kabul Et
</x-mail::button>

Veya şu bağlantıyı tarayıcınıza yapıştırın:

{{ $invitationUrl }}

Bu davet 7 gün geçerlidir. Daveti siz istemediyseniz bu e-postayı yok sayabilirsiniz.

Saygılarımızla,<br>
{{ config('app.name') }}
</x-mail::message>
