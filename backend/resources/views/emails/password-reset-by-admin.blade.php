<x-mail::message>
# Şifreniz Sıfırlandı

Merhaba {{ $user->name }},

Yöneticiniz hesabınızın şifresini sıfırladı. Yeni giriş bilgileriniz:

- **E-posta:** {{ $user->email }}
- **Yeni şifre:** `{{ $newPassword }}`

<x-mail::button :url="$loginUrl" color="success">
Giriş Yap
</x-mail::button>

Güvenliğiniz için giriş yaptıktan sonra şifrenizi değiştirmenizi öneririz. Bu işlemi siz talep etmediyseniz lütfen yöneticinizle iletişime geçin.

Saygılarımızla,<br>
{{ config('app.name') }}
</x-mail::message>
