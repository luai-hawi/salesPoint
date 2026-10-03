<?php

use App\Models\ActivityLog;

test('activity labels exist for all actions and subject types written in the reviewed area', function () {
    $pairs = [
        ['team_account', 'created'],
        ['team_account', 'updated'],
        ['team_account', 'deleted'],
        ['team_account', 'suspended'],
        ['team_account', 'reactivated'],
        ['team_account', 'password_reset'],
        ['team_account', 'logged_out'],
        ['staff_member', 'created'],
        ['staff_member', 'updated'],
        ['staff_member', 'deleted'],
        ['employee_payment', 'created'],
        ['employee_payment', 'updated'],
        ['employee_payment', 'deleted'],
        ['employee_device', 'revoked'],
        ['employee_credential', 'deleted'],
    ];

    foreach (['en', 'ar'] as $locale) {
        app()->setLocale($locale);

        foreach ($pairs as [$subjectType, $action]) {
            $log = new ActivityLog([
                'subject_type' => $subjectType,
                'action' => $action,
                'subject_label' => 'Example',
            ]);

            expect(__('activity.subjects.' . $subjectType))->not->toBe('activity.subjects.' . $subjectType)
                ->and(__('activity.actions.' . $action))->not->toBe('activity.actions.' . $action)
                ->and($log->describe())->not->toContain('activity.events.');
        }
    }
});
