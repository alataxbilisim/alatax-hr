<?php

/**
 * Bildirim olay kataloğu (4C).
 * group: kullanıcı tercih kategorisi (approvals|requests|tasks|documents|security)
 * panel: frontend_urls anahtarı (company|portal)
 * path: SPA yolu ({id}, {process_id} yer tutucuları)
 * email_default: tercihsiz varsayılan e-posta (hatırlatma = false)
 * force: güvenlik — tercihle kapatılamaz
 * variables: şablon yer tutucuları ({{name}})
 */
return [

    'events' => [

        'approval.requested' => [
            'group' => 'approvals',
            'panel' => 'company',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.approval_requested_title',
            'body_key' => 'messages.notifications.approval_requested_body',
            'variables' => ['user', 'entity', 'date', 'step'],
        ],

        'approval.approved' => [
            'group' => 'approvals',
            'panel' => 'portal',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.approval_approved_title',
            'body_key' => 'messages.notifications.approval_approved_body',
            'variables' => ['user', 'entity', 'date'],
        ],

        'approval.rejected' => [
            'group' => 'approvals',
            'panel' => 'portal',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.approval_rejected_title',
            'body_key' => 'messages.notifications.approval_rejected_body',
            'variables' => ['user', 'entity', 'date', 'reason'],
        ],

        'approval.returned' => [
            'group' => 'approvals',
            'panel' => 'portal',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.approval_returned_title',
            'body_key' => 'messages.notifications.approval_returned_body',
            'variables' => ['user', 'entity', 'date', 'reason'],
        ],

        'approval.reminder' => [
            'group' => 'approvals',
            'panel' => 'company',
            'path' => '/leaves',
            'email_default' => false,
            'title_key' => 'messages.notifications.approval_reminder_title',
            'body_key' => 'messages.notifications.approval_reminder_body',
            'variables' => ['user', 'entity', 'date', 'step', 'days'],
        ],

        'approval.escalated' => [
            'group' => 'approvals',
            'panel' => 'company',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.approval_escalated_title',
            'body_key' => 'messages.notifications.approval_escalated_body',
            'variables' => ['user', 'entity', 'date', 'step', 'days'],
        ],

        'leave.approved' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.leave_approved_title',
            'body_key' => 'messages.notifications.leave_approved_body',
            'variables' => ['user', 'entity', 'date'],
        ],

        'leave.rejected' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.leave_rejected_title',
            'body_key' => 'messages.notifications.leave_rejected_body',
            'variables' => ['user', 'entity', 'date', 'reason'],
        ],

        'leave.cancelled' => [
            'group' => 'requests',
            'panel' => 'company',
            'path' => '/leaves',
            'email_default' => true,
            'title_key' => 'messages.notifications.leave_cancelled_title',
            'body_key' => 'messages.notifications.leave_cancelled_body',
            'variables' => ['user', 'entity', 'date'],
        ],

        'expense.approved' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/expenses',
            'email_default' => true,
            'title_key' => 'messages.notifications.expense_approved_title',
            'body_key' => 'messages.notifications.expense_approved_body',
            'variables' => ['user', 'entity', 'date'],
        ],

        'expense.rejected' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/expenses',
            'email_default' => true,
            'title_key' => 'messages.notifications.expense_rejected_title',
            'body_key' => 'messages.notifications.expense_rejected_body',
            'variables' => ['user', 'entity', 'date', 'reason'],
        ],

        'onboarding.task_assigned' => [
            'group' => 'tasks',
            'panel' => 'company',
            'path' => '/onboarding/processes/{process_id}',
            'email_default' => true,
            'title_key' => 'messages.notifications.onboarding_task_assigned_title',
            'body_key' => 'messages.notifications.onboarding_task_assigned_body',
            'variables' => ['user', 'entity', 'date', 'task', 'process'],
        ],

        'document.expiring' => [
            'group' => 'documents',
            'panel' => 'company',
            'path' => '/documents',
            'email_default' => true,
            'title_key' => 'messages.notifications.document_expiring_title',
            'body_key' => 'messages.notifications.document_expiring_body',
            'variables' => ['user', 'entity', 'date', 'days'],
        ],

        'asset.assigned' => [
            'group' => 'tasks',
            'panel' => 'portal',
            'path' => '/assets',
            'email_default' => true,
            'title_key' => 'messages.notifications.asset_assigned_title',
            'body_key' => 'messages.notifications.asset_assigned_body',
            'variables' => ['user', 'entity', 'date', 'asset'],
        ],

        'security.password_changed' => [
            'group' => 'security',
            'panel' => 'company',
            'path' => '/account/preferences',
            'email_default' => true,
            'force' => true,
            'title_key' => 'messages.notifications.security_password_changed_title',
            'body_key' => 'messages.notifications.security_password_changed_body',
            'variables' => ['user', 'date'],
        ],

        'security.two_factor_enabled' => [
            'group' => 'security',
            'panel' => 'company',
            'path' => '/account/preferences',
            'email_default' => true,
            'force' => true,
            'title_key' => 'messages.notifications.security_two_factor_enabled_title',
            'body_key' => 'messages.notifications.security_two_factor_enabled_body',
            'variables' => ['user', 'date'],
        ],

        'security.two_factor_disabled' => [
            'group' => 'security',
            'panel' => 'company',
            'path' => '/account/preferences',
            'email_default' => true,
            'force' => true,
            'title_key' => 'messages.notifications.security_two_factor_disabled_title',
            'body_key' => 'messages.notifications.security_two_factor_disabled_body',
            'variables' => ['user', 'date'],
        ],

        // Katalogda hazır — company-side HR onay / duyuru yayını yok (DUR)
        'request.approved' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/requests',
            'email_default' => true,
            'title_key' => 'messages.notifications.request_approved_title',
            'body_key' => 'messages.notifications.request_approved_body',
            'variables' => ['user', 'entity', 'date'],
        ],

        'request.rejected' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/requests',
            'email_default' => true,
            'title_key' => 'messages.notifications.request_rejected_title',
            'body_key' => 'messages.notifications.request_rejected_body',
            'variables' => ['user', 'entity', 'date', 'reason'],
        ],

        'announcement.published' => [
            'group' => 'tasks',
            'panel' => 'portal',
            'path' => '/announcements',
            'email_default' => true,
            'title_key' => 'messages.notifications.announcement_published_title',
            'body_key' => 'messages.notifications.announcement_published_body',
            'variables' => ['user', 'entity', 'date', 'title'],
        ],

    ],

];
