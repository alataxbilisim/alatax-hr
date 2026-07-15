<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Uygulama mesajları (API + Mailable)
    |--------------------------------------------------------------------------
    */

    'mail' => [
        'reset_password_subject' => ':app — Şifre Sıfırlama',
        'reset_password_heading' => 'Şifre Sıfırlama',
        'reset_password_intro' => 'Hesabınız için şifre sıfırlama talebi aldık. Aşağıdaki düğmeye tıklayarak yeni şifrenizi belirleyebilirsiniz.',
        'reset_password_action' => 'Şifremi Sıfırla',
        'reset_password_expire' => 'Bu bağlantı yaklaşık **:minutes dakika** geçerlidir. Talebi siz oluşturmadıysanız bu e-postayı yok sayabilirsiniz.',
        'reset_password_salutation' => 'Saygılarımızla',

        'invitation_subject' => ':company — Sistem Daveti',
        'invitation_heading' => 'Sistem Daveti',
        'invitation_intro' => '**:company** sizi :app sistemine davet ediyor.',
        'invitation_role' => 'Size atanan rol: **:role**',
        'invitation_action' => 'Daveti Kabul Et',
        'invitation_copy' => 'Veya şu bağlantıyı tarayıcınıza yapıştırın:',
        'invitation_expire' => 'Bu davet 7 gün geçerlidir. Daveti siz istemediyseniz bu e-postayı yok sayabilirsiniz.',

        'employee_invitation_subject' => ':company — Portal Daveti',
        'employee_invitation_heading' => 'Portal Daveti',
        'employee_invitation_intro' => '**:company** sizi :app personel self-servis portalına davet ediyor.',
        'employee_invitation_intro_generic' => ':app personel self-servis portalına davet edildiniz.',
        'employee_invitation_credentials' => 'Geçici giriş bilgileriniz:',
        'employee_invitation_email' => 'E-posta',
        'employee_invitation_temp_password' => 'Geçici şifre',
        'employee_invitation_login' => 'Portala Giriş Yap',
        'employee_invitation_accept' => 'Daveti Kabul Et',
        'employee_invitation_change_hint' => 'Güvenliğiniz için ilk girişten sonra şifrenizi değiştirmenizi öneririz.',
        'employee_invitation_set_password_hint' => 'Hesabınızı etkinleştirmek için aşağıdaki bağlantıdan şifrenizi belirleyin.',

        'admin_reset_subject' => ':app — Şifreniz Sıfırlandı',
        'admin_reset_heading' => 'Şifreniz Sıfırlandı',
        'admin_reset_intro' => 'Yöneticiniz hesabınızın şifresini sıfırladı. Yeni giriş bilgileriniz:',
        'admin_reset_new_password' => 'Yeni şifre',
        'admin_reset_login' => 'Giriş Yap',
        'admin_reset_hint' => 'Güvenliğiniz için giriş yaptıktan sonra şifrenizi değiştirmenizi öneririz. Bu işlemi siz talep etmediyseniz lütfen yöneticinizle iletişime geçin.',

        'hello_name' => 'Merhaba :name',
        'hello' => 'Merhaba',
    ],

    'notifications' => [
        'mail_action' => 'Panele Git',
        'entity_leave' => 'İzin talebi',
        'entity_expense' => 'Masraf talebi',

        'approval_requested_title' => 'Onay bekleniyor',
        'approval_requested_body' => ':entity için onayınız bekleniyor (:step).',

        'approval_approved_title' => 'Talebiniz onaylandı',
        'approval_approved_body' => ':entity onaylandı (:date).',

        'approval_rejected_title' => 'Talebiniz reddedildi',
        'approval_rejected_body' => ':entity reddedildi. :reason',

        'approval_returned_title' => 'Talebiniz iade edildi',
        'approval_returned_body' => ':entity iade edildi. :reason',

        'leave_approved_title' => 'İzin talebiniz onaylandı',
        'leave_approved_body' => 'İzin talebiniz :date tarihinde onaylandı.',

        'leave_rejected_title' => 'İzin talebiniz reddedildi',
        'leave_rejected_body' => 'İzin talebiniz reddedildi. :reason',

        'expense_approved_title' => 'Masraf talebiniz onaylandı',
        'expense_approved_body' => 'Masraf talebiniz :date tarihinde onaylandı.',

        'expense_rejected_title' => 'Masraf talebiniz reddedildi',
        'expense_rejected_body' => 'Masraf talebiniz reddedildi. :reason',

        'onboarding_task_assigned_title' => 'Yeni onboarding görevi',
        'onboarding_task_assigned_body' => 'Size görev atandı: :task (:process).',

        'document_expiring_title' => 'Süreli evrak uyarısı',
        'document_expiring_body' => '":entity" belgesinin süresi :days gün içinde doluyor (:date).',
    ],

];
