<x-mail::message>
# Şifre Sıfırlama

Merhaba{{ isset($user) && $user->name ? ' '.$user->name : '' }},

Hesabınız için şifre sıfırlama talebi aldık. Aşağıdaki düğmeye tıklayarak yeni şifrenizi belirleyebilirsiniz.

<x-mail::button :url="$url" color="success">
Şifremi Sıfırla
</x-mail::button>

Bu bağlantı yaklaşık **{{ $expireMinutes }} dakika** geçerlidir. Talebi siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.

Saygılarımızla,<br>
{{ config('app.name') }}
</x-mail::message>
