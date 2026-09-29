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

    // Sidebar menu: [route name, label, roles that may see it, Lucide icon name, optional extra route patterns to highlight on]
    // (same roles as 'role_access' below).
    // Items are hidden from roles not listed. Items whose route does not exist yet link to "#".
    'nav' => [
        ['dashboard', 'Dashboard', ['Owner', 'Manager', 'Caretaker'], 'layout-dashboard'],
        ['rooms.index', 'Rooms & Inquiries', ['Owner', 'Manager', 'Caretaker'], 'door-open', ['inquiries.*']],
        // Caretaker sees this page only for appliances and visitors, not the MOA details.
        ['tenants.index', 'Tenants & MOA', ['Owner', 'Manager', 'Caretaker'], 'users', ['leases.*']],
        ['billing.index', 'Billing', ['Owner', 'Manager'], 'receipt'],
        ['payments.index', 'Payments', ['Owner', 'Manager'], 'wallet'],
        ['concerns.index', 'Concerns', ['Owner', 'Manager', 'Caretaker'], 'wrench'],
        ['moveouts.index', 'Move-out Settlement', ['Owner', 'Manager'], 'package'],
        ['reports.index', 'Reports', ['Owner', 'Manager'], 'chart-column'],
        ['users.index', 'User Accounts', ['Owner'], 'user-cog'],
        ['settings.index', 'Settings & Backup', ['Owner'], 'settings'],
    ],

    // "Role access" table shown on the User Accounts screen (same as the roles table in the project rules).
    // [function, Owner, Manager, Caretaker]  - true = yes, false = no, string = limited access
    'role_access' => [
        ['Dashboard', true, true, 'Concerns, rooms only'],
        ['Rooms: add/edit rooms, rents', true, false, false],
        ['Rooms: view availability', true, true, true],
        ['Inquiries: log / update', true, true, true],
        ['Tenants and MOA, occupants', true, true, false],
        ['Appliances and visitors', true, true, true],
        ['Meter readings', true, true, false],
        ['Billing', true, true, false],
        ['Payments', true, true, false],
        ['Concerns: log / update', true, true, true],
        ['Move-out: notice, inspection, compute', true, true, false],
        ['Move-out: approve refund / release', true, false, false],
        ['Reports', true, true, false],
        ['User accounts', true, false, false],
        ['Settings (rates and rules)', true, false, false],
        ['Backup and restore', true, false, false],
        ['My password', true, true, true],
    ],

];
