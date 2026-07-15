<?php

/**
 * Bildirim olay kataloğu (4C-1).
 * group: kullanıcı e-posta tercihi (approvals|requests|tasks)
 * panel: frontend_urls anahtarı (company|portal)
 * path: SPA yolu ({id}, {process_id} yer tutucuları)
 */
return [

    'events' => [

        'approval.requested' => [
            'group' => 'approvals',
            'panel' => 'company',
            'path' => '/leaves',
            'title_key' => 'messages.notifications.approval_requested_title',
            'body_key' => 'messages.notifications.approval_requested_body',
        ],

        'approval.approved' => [
            'group' => 'approvals',
            'panel' => 'portal',
            'path' => '/leaves',
            'title_key' => 'messages.notifications.approval_approved_title',
            'body_key' => 'messages.notifications.approval_approved_body',
        ],

        'approval.rejected' => [
            'group' => 'approvals',
            'panel' => 'portal',
            'path' => '/leaves',
            'title_key' => 'messages.notifications.approval_rejected_title',
            'body_key' => 'messages.notifications.approval_rejected_body',
        ],

        'approval.returned' => [
            'group' => 'approvals',
            'panel' => 'portal',
            'path' => '/leaves',
            'title_key' => 'messages.notifications.approval_returned_title',
            'body_key' => 'messages.notifications.approval_returned_body',
        ],

        'leave.approved' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/leaves',
            'title_key' => 'messages.notifications.leave_approved_title',
            'body_key' => 'messages.notifications.leave_approved_body',
        ],

        'leave.rejected' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/leaves',
            'title_key' => 'messages.notifications.leave_rejected_title',
            'body_key' => 'messages.notifications.leave_rejected_body',
        ],

        'expense.approved' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/expenses',
            'title_key' => 'messages.notifications.expense_approved_title',
            'body_key' => 'messages.notifications.expense_approved_body',
        ],

        'expense.rejected' => [
            'group' => 'requests',
            'panel' => 'portal',
            'path' => '/expenses',
            'title_key' => 'messages.notifications.expense_rejected_title',
            'body_key' => 'messages.notifications.expense_rejected_body',
        ],

        'onboarding.task_assigned' => [
            'group' => 'tasks',
            'panel' => 'company',
            'path' => '/onboarding/processes/{process_id}',
            'title_key' => 'messages.notifications.onboarding_task_assigned_title',
            'body_key' => 'messages.notifications.onboarding_task_assigned_body',
        ],

        'document.expiring' => [
            'group' => 'documents',
            'panel' => 'company',
            'path' => '/documents',
            'title_key' => 'messages.notifications.document_expiring_title',
            'body_key' => 'messages.notifications.document_expiring_body',
        ],

    ],

];
