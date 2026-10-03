<?php

return [
    'brand' => 'HawiTech',
    'back' => 'Go back',
    'home' => 'Home page',
    'login' => 'Sign in again',
    'reload' => 'Refresh the page',
    'codes' => [
        '403' => [
            'title' => 'You do not have access to this page',
            'message' => 'Your account does not have permission to open this page. Ask the shop owner to grant you the required permission.',
        ],
        '404' => [
            'title' => 'Page not found',
            'message' => 'The page you are looking for does not exist or was moved.',
        ],
        '419' => [
            'title' => 'The page has expired',
            'message' => 'Your session or the form expired. Refresh the page and try again.',
        ],
        '429' => [
            'title' => 'Too many requests',
            'message' => 'Please wait a moment and try again.',
        ],
        '500' => [
            'title' => 'Something went wrong',
            'message' => 'An unexpected error occurred. Your data was not lost. Please try again in a moment.',
        ],
        '503' => [
            'title' => 'We will be right back',
            'message' => 'The system is being updated. Please try again in a few minutes.',
        ],
    ],
];
