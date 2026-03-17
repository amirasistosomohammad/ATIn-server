@echo off
echo Fixing Sanctum: updating composer.lock and installing laravel/sanctum...
cd /d "%~dp0"
composer update laravel/sanctum --no-interaction
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo If that failed, try: composer update
    exit /b 1
)
echo.
echo Done. Run: php artisan config:clear
php artisan config:clear
echo.
echo You can now run: php artisan serve
pause
