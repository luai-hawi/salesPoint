<?php

return [
    'actions' => [
        'created' => 'created',
        'updated' => 'updated',
        'deleted' => 'deleted',
        'held' => 'put on hold',
        'resumed' => 'resumed',
        'checked_in' => 'checked in',
        'checked_out' => 'checked out',
        'login' => 'signed in',
        'suspended' => 'suspended',
        'reactivated' => 'reactivated',
        'password_reset' => 'reset the password for',
        'logged_out' => 'signed out',
        'revoked' => 'revoked',
    ],

    'subjects' => [
        'bill' => 'bill',
        'customer_payment' => 'customer payment',
        'supplier_payment' => 'supplier payment',
        'purchase_bill' => 'purchase bill',
        'expense' => 'expense',
        'capital_entry' => 'capital entry',
        'customer' => 'customer',
        'supplier' => 'supplier',
        'product' => 'product',
        'employee_payment' => 'staff payment',
        'employee_device' => 'employee device',
        'employee_credential' => 'employee credential',
        'staff_member' => 'staff member',
        'team_account' => 'team account',
        'cash_movement' => 'cash movement',
        'day_closing' => 'day closing',
        'held_bill' => 'held bill',
        'kitchen_order' => 'kitchen order',
    ],

    'events' => [
        'generic' => ':action :subject :label',
        'bill' => [
            'created' => 'Created bill :label for :amount',
            'updated' => 'Changed bill :label (total now :amount)',
            'deleted' => 'Deleted bill :label (:amount)',
        ],
        'customer_payment' => [
            'created' => 'Recorded a customer movement of :amount for :label',
            'updated' => 'Changed a customer movement for :label (now :amount)',
            'deleted' => 'Deleted a customer movement of :amount for :label',
        ],
        'supplier_payment' => [
            'created' => 'Recorded a supplier payment of :amount for :label',
            'updated' => 'Changed a supplier payment for :label (now :amount)',
            'deleted' => 'Deleted a supplier payment of :amount for :label',
        ],
        'purchase_bill' => [
            'created' => 'Created purchase bill :label for :amount',
            'updated' => 'Changed purchase bill :label (total now :amount)',
            'deleted' => 'Deleted purchase bill :label (:amount)',
        ],
        'expense' => [
            'created' => 'Added expense ":label" of :amount',
            'updated' => 'Changed expense ":label" (now :amount)',
            'deleted' => 'Deleted expense ":label" of :amount',
        ],
        'capital_entry' => [
            'created' => 'Added a capital entry of :amount',
            'updated' => 'Changed a capital entry (now :amount)',
            'deleted' => 'Deleted a capital entry of :amount',
        ],
        'customer' => [
            'created' => 'Added customer :label',
            'updated' => 'Changed customer :label',
            'deleted' => 'Deleted customer :label',
        ],
        'supplier' => [
            'created' => 'Added supplier :label',
            'updated' => 'Changed supplier :label',
            'deleted' => 'Deleted supplier :label',
        ],
        'product' => [
            'created' => 'Added product :label',
            'updated' => 'Changed product :label',
            'deleted' => 'Deleted product :label',
        ],
        'employee_payment' => [
            'created' => 'Paid staff member :label an amount of :amount',
            'updated' => 'Changed a payment to :label (now :amount)',
            'deleted' => 'Deleted a payment of :amount to :label',
        ],
        'employee_device' => [
            'revoked' => 'Revoked an employee device for :label',
        ],
        'employee_credential' => [
            'deleted' => 'Deleted an employee credential for :label',
        ],
        'staff_member' => [
            'created' => 'Added staff member :label',
            'updated' => 'Changed staff member :label',
            'deleted' => 'Removed staff member :label',
        ],
        'team_account' => [
            'created' => 'Created team account :label',
            'updated' => 'Changed team account :label',
            'deleted' => 'Deleted team account :label',
            'suspended' => 'Suspended team account :label',
            'reactivated' => 'Reactivated team account :label',
            'password_reset' => 'Reset the password for team account :label',
            'logged_out' => 'Signed out team account :label',
        ],
        'cash_movement' => [
            'created' => 'Recorded cash movement ":label" for :amount',
            'updated' => 'Updated cash movement ":label" (now :amount)',
            'deleted' => 'Deleted cash movement ":label" for :amount',
        ],
        'day_closing' => [
            'created' => 'Saved day closing :label with variance :amount',
            'updated' => 'Updated day closing :label with variance :amount',
            'deleted' => 'Deleted day closing :label',
        ],
        'held_bill' => [
            'held' => 'Put a bill on hold (:label)',
            'resumed' => 'Resumed held bill :label',
            'deleted' => 'Discarded held bill :label',
        ],
        'kitchen_order' => [
            'created' => 'Sent order :label to the kitchen',
            'updated' => 'Updated kitchen order :label',
        ],
    ],

    'ui' => [
        'title' => 'Activity log',
        'empty' => 'No activity recorded yet.',
        'actor' => 'Who',
        'when' => 'When',
        'what' => 'What',
        'amount' => 'Amount',
    ],
];
