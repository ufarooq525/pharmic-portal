@echo off
rem One-time local setup on Windows with Laragon. Safe to run again.
setlocal
cd /d "%~dp0"
set "LARAGON=C:\laragon"

where php >nul 2>nul || for /d %%D in ("%LARAGON%\bin\php\php-8*") do set "PATH=%%D;%PATH%"
where php >nul 2>nul || (echo PHP not found. Start Laragon first. & goto :fail)
php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);" || (echo Laravel 13 needs PHP 8.3 or newer. Switch PHP in Laragon: Menu ^> PHP ^> Version. & goto :fail)

set "COMPOSER=composer"
where composer >nul 2>nul || set "COMPOSER=php "%LARAGON%\bin\composer\composer.phar""

if not exist .env copy .env.example .env >nul

echo == Installing PHP packages
call %COMPOSER% install --no-interaction --prefer-dist || goto :fail

echo == Creating the MySQL database pharmic_portal (Laragon MySQL must be running)
php -r "$e=parse_ini_file('.env');$p=new PDO('mysql:host='.$e['DB_HOST'].';port='.$e['DB_PORT'],$e['DB_USERNAME'],$e['DB_PASSWORD']??'');$p->exec('CREATE DATABASE IF NOT EXISTS `'.$e['DB_DATABASE'].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');" || goto :fail

findstr /r /c:"^APP_KEY=base64" .env >nul || php artisan key:generate --force || goto :fail

echo == Creating tables and loading products, distributors and reps
php artisan migrate --force || goto :fail
php artisan db:seed --force || goto :fail
if not exist public\storage php artisan storage:link

echo.
echo Done. Open http://pharmic-portal.test (Laragon: Menu ^> Reload if the address does not open)
echo Sign in: owner@example.com / change-me-now  (change it after signing in)
pause
exit /b 0

:fail
echo.
echo Setup stopped. Copy the error above and send it to Claude.
pause
exit /b 1
