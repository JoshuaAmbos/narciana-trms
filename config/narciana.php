<?php

/*
| Settings specific to the Narciana Tenant and Rental Management System.
| Business rates (water, kWh, penalty...) are NOT here: they live in tbl_setting so the Owner can change them.
*/

return [

    // Initial passwords for the seeded staff accounts. Blank = generate a random one and print it once.
    'seed_passwords' => [
        'owner' => env('SEED_OWNER_PASSWORD'),
        'manager' => env('SEED_MANAGER_PASSWORD'),
        'caretaker' => env('SEED_CARETAKER_PASSWORD'),
    ],

    // Sidebar menu: [route name, label, roles that may see it].
    // Items whose route does not exist yet link to "#". Hiding by role starts in Module 2.
    'nav' => [
        ['dashboard', 'Dashboard', ['Owner', 'Manager', 'Caretaker']],
        ['rooms.index', 'Rooms & Inquiries', ['Owner', 'Manager', 'Caretaker']],
        // Caretaker sees this page only for appliances and visitors, not the MOA details.
        ['tenants.index', 'Tenants & MOA', ['Owner', 'Manager', 'Caretaker']],
        ['billing.index', 'Billing', ['Owner', 'Manager']],
        ['payments.index', 'Payments', ['Owner', 'Manager']],
        ['concerns.index', 'Concerns', ['Owner', 'Manager', 'Caretaker']],
        ['moveouts.index', 'Move-out Settlement', ['Owner', 'Manager']],
        ['reports.index', 'Reports', ['Owner', 'Manager']],
        ['users.index', 'User Accounts', ['Owner']],
        ['settings.index', 'Settings & Backup', ['Owner']],
    ],

];
