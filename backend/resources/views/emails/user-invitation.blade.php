<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Daveti</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #10b981 0%, #06b6d4 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0;">Sistem Daveti</h1>
    </div>
    
    <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
        <p>Merhaba,</p>
        
        <p><strong>{{ $company->name }}</strong> sizi {{ config('app.name', 'Alatax HR') }} sistemine davet ediyor.</p>
        
        @if($role)
        <p>Size atanan rol: <strong>{{ $role }}</strong></p>
        @endif
        
        <p style="margin: 30px 0;">
            <a href="{{ $invitationUrl }}" 
               style="display: inline-block; background: #10b981; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold;">
                Daveti Kabul Et
            </a>
        </p>
        
        <p style="font-size: 12px; color: #666; margin-top: 30px;">
            Veya aşağıdaki linki tarayıcınıza kopyalayın:<br>
            <a href="{{ $invitationUrl }}" style="color: #10b981; word-break: break-all;">{{ $invitationUrl }}</a>
        </p>
        
        <p style="font-size: 12px; color: #999; margin-top: 30px; border-top: 1px solid #ddd; padding-top: 20px;">
            Bu davet 7 gün geçerlidir. Eğer bu daveti siz istemediyseniz, bu e-postayı görmezden gelebilirsiniz.
        </p>
    </div>
</body>
</html>

