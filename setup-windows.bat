@echo off
rem One-time local setup on Windows with Laragon. Safe to run again. Output is also saved to setup.log.
setlocal
cd /d "%~dp0"
if /i not "%~1"=="--logged" (
    call "%~f0" --logged > setup.log 2>&1
    type setup.log
    pause
    exit /b
)
set "LARAGON=C:\laragon"

where php >nul 2>nul || for /d %%D in ("%LARAGON%\bin\php\php-8*") do set "PATH=%%D;%PATH%"
where php >nul 2>nul || (echo PHP not found. Start Laragon first. & goto :fail)
php -r "exit(version_compare(PHP_VERSION, '8.3.0', '>=') ? 0 : 1);" || (echo Laravel 13 needs PHP 8.3 or newer. Switch PHP in Laragon: Menu ^> PHP ^> Version. & goto :fail)

set "COMPOSER_CMD=composer"
where composer >nul 2>nul || set "COMPOSER_CMD=php %LARAGON%\bin\composer\composer.phar"

if not exist .env copy .env.example .env >nul

echo == Installing PHP packages
call %COMPOSER_CMD% install --no-interaction --prefer-dist --no-progress --optimize-autoloader || goto :fail

findstr /r /c:"^APP_KEY=base64" .env >nul || php artisan key:generate --force || goto :fail

echo == Database: creating or updating tables (first run also loads products, distributors and reps)
rem --force also creates the MySQL database when it does not exist yet
php artisan migrate --force || goto :fail
rem Seed only once: running this again later must not reset passwords or rates.
if not exist storage\app\.seeded (
    php artisan db:seed --force || goto :fail
    echo seeded> storage\app\.seeded
)
if not exist public\storage php artisan storage:link

echo == Speed: caching config, routes, events and views
php artisan optimize:clear >nul
php artisan optimize || goto :fail

echo.
echo Done. Open http://pharmic-portal.test (Laragon: Menu ^> Reload if the address does not open)
echo Sign in: owner@example.com / change-me-now  (change it after signing in)
exit /b 0

:fail
echo.
echo Setup stopped. Copy the error above and send it to Claude.
exit /b 1
