# Narciana Tenant and Rental Management System

  

Web app for Narciana House Space Rental (Laravel 12 + Blade + MySQL/MariaDB), used in Chrome on an Android tablet.

  
  

## Local setup (Windows + XAMPP)

  

1. Start **MySQL** in the XAMPP Control Panel.

2. Create the databases (once):

```

C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE narciana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE narciana_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

```

3. Install and configure:

```

composer install

copy .env.example .env

php artisan key:generate

```

4. Create the tables and the staff accounts + default settings:

```

php artisan migrate

php artisan db:seed

```

Set `SEED_OWNER_PASSWORD` etc. in `.env` first, or copy the random passwords the seeder prints.

5. Sample rooms for testing only (never on the live hosting):

```

php artisan db:seed --class=DemoSeeder

```

6. Run: `php artisan serve`, then open http://127.0.0.1:8000

  

## Local setup (Ubuntu / Linux)

1. Start **MySQL** (using LAMPP or native MySQL):
   * For LAMPP:
     ```
     sudo /opt/lampp/lampp startmysql
     ```
   * For native MySQL:
     ```
     sudo systemctl start mysql
     ```

2. Create the databases (once):
   * For LAMPP:
     ```
     /opt/lampp/bin/mysql -u root -e "CREATE DATABASE narciana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE narciana_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
     ```
   * For native MySQL:
     ```
     sudo mysql -e "CREATE DATABASE narciana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE narciana_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
     ```

3. Install and configure:
```
composer install npm install cp .env.example .env php artisan key:generate
```

4. Create the tables and the staff accounts + default settings:
```
php artisan migrate
php artisan db:seed
```
Set `SEED_OWNER_PASSWORD` etc. in `.env` first, or copy the random passwords the seeder prints.

5. Sample rooms for testing only (never on the live hosting):
```
php artisan db:seed --class=DemoSeeder
```

6. Run: `composer run dev` (or `php artisan serve`), then open http://127.0.0.1:8000


## Tests

  

```

php artisan test

```

Tests use the separate `narciana_test` database and wipe it on every run.