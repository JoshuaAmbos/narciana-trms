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
    // Items are hidden from roles not listed (LayoutTest opens every visible link for each role).
    'nav' => [
        // Order (decision 19): what users check most and what needs action first.
        ['dashboard', 'Dashboard', ['Owner', 'Manager', 'Caretaker'], 'layout-dashboard'],
        ['bills.index', 'Billing', ['Owner', 'Manager'], 'receipt', ['meter-readings.*']],
        ['payments.index', 'Payments', ['Owner', 'Manager'], 'wallet'],
        // Caretaker sees this page only for appliances and visitors, not the MOA details.
        ['tenants.index', 'Tenants & MOA', ['Owner', 'Manager', 'Caretaker'], 'users', ['leases.*']],
        ['concerns.index', 'Concerns', ['Owner', 'Manager', 'Caretaker'], 'wrench'],
        ['rooms.index', 'Rooms', ['Owner', 'Manager', 'Caretaker'], 'door-open'],
        ['inquiries.index', 'Inquiries', ['Owner', 'Manager', 'Caretaker'], 'clipboard-list'],
        ['moveouts.index', 'Move-out Settlement', ['Owner', 'Manager'], 'package'],
        ['reports.index', 'Reports', ['Owner', 'Manager'], 'chart-column'],
        ['users.index', 'User Accounts', ['Owner'], 'user-cog'],
        ['settings.index', 'Settings & Backup', ['Owner'], 'settings', ['backups.*']],
        ['audit-log.index', 'Audit Log', ['Owner'], 'scroll-text'],
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
        ['Audit log (view only)', true, false, false],
        ['My password', true, true, true],
    ],

    // Backups (Module 9b): plain mysqldump / mysql, run by BackupService.
    // XAMPP:     BACKUP_MYSQLDUMP_PATH=C:/xampp/mysql/bin/mysqldump.exe  BACKUP_MYSQL_PATH=C:/xampp/mysql/bin/mysql.exe
    // Namecheap: usually just "mysqldump" and "mysql" (they are on the PATH).
    'backup' => [
        'mysqldump' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
        'mysql' => env('BACKUP_MYSQL_PATH', 'mysql'),
        'path' => storage_path('app/backups'), // not public: files are only reachable through the Owner's Download button
        'keep' => 30,                          // newest backups kept; older ones are deleted after each new backup
        // Laravel's own tables that are not worth backing up (restoring sessions would also clash with the restore itself).
        'skip_tables' => ['sessions', 'cache', 'cache_locks'],
    ],

];
