<?php

/*
 * Plan comparison shown to academies and on the platform plan screen.
 *
 * "included" capabilities come with every plan. "flags" differ per plan and are stored in
 * plan_features, so the platform admin turns them on or off for each plan. Limits
 * (players, branches, staff accounts) and the migration and support text are plan columns.
 */
return [
    'included' => [
        'Applications and player records',
        'Programmes, cycles and sessions',
        'Attendance',
        'Manual invoices, payments and expenses',
        'Dashboards and CSV exports',
        'Public academy website and advanced branding',
        'Team roles and audit history',
        'Paystack online payments',
        'Instalment and payment plans',
        'Reviews and form builder',
        'Coupons and referrals',
        'Store and inventory',
        'Scholarships and sponsorships',
    ],

    'flags' => [
        'api_access' => 'API access',
        'custom_domain' => 'Custom domain',
    ],
];
