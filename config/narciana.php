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

    // Sidebar menu: [route name, label, roles that may see it, Lucide icon name].
    // Items whose route does not exist yet link to "#". Hiding by role starts in Module 2.
    'nav' => [
        ['dashboard', 'Dashboard', ['Owner', 'Manager', 'Caretaker'], 'layout-dashboard'],
        ['rooms.index', 'Rooms & Inquiries', ['Owner', 'Manager', 'Caretaker'], 'door-open'],
        // Caretaker sees this page only for appliances and visitors, not the MOA details.
        ['tenants.index', 'Tenants & MOA', ['Owner', 'Manager', 'Caretaker'], 'users'],
        ['billing.index', 'Billing', ['Owner', 'Manager'], 'receipt'],
        ['payments.index', 'Payments', ['Owner', 'Manager'], 'wallet'],
        ['concerns.index', 'Concerns', ['Owner', 'Manager', 'Caretaker'], 'wrench'],
        ['moveouts.index', 'Move-out Settlement', ['Owner', 'Manager'], 'package'],
        ['reports.index', 'Reports', ['Owner', 'Manager'], 'chart-column'],
        ['users.index', 'User Accounts', ['Owner'], 'user-cog'],
        ['settings.index', 'Settings & Backup', ['Owner'], 'settings'],
    ],

];
