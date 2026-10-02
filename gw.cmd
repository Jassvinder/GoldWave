@echo off
rem GoldWave helper - runs the frontend/backend tools directly, so it keeps
rem working while Windows Smart App Control blocks pnpm.exe (pnpm 12 ships a
rem native exe). Usage from the project root:  .\gw build   (see "gw help").
setlocal
cd /d "%~dp0"

set "VP=node_modules\.bin\vp.cmd"
set "CMD=%~1"
if "%CMD%"=="" set "CMD=help"

if /i "%CMD%"=="build" goto build
if /i "%CMD%"=="dev" goto dev
if /i "%CMD%"=="types" goto types
if /i "%CMD%"=="check" goto check
if /i "%CMD%"=="front" goto front
if /i "%CMD%"=="test" goto test
if /i "%CMD%"=="php" goto php
if /i "%CMD%"=="all" goto all
if /i "%CMD%"=="queue" goto queue
if /i "%CMD%"=="schedule" goto schedule
goto help

:build
call "%VP%" build
goto end

:dev
call "%VP%" dev
goto end

:types
node node_modules\typescript\bin\tsc --noEmit && echo TypeScript: OK
goto end

:check
rem With file paths it only touches those files; without, it formats the whole project.
shift
call "%VP%" check --fix %1 %2 %3 %4 %5 %6 %7 %8 %9
goto end

:front
node node_modules\typescript\bin\tsc --noEmit || goto end
echo TypeScript: OK
call "%VP%" build
goto end

:test
shift
php artisan test %1 %2 %3 %4 %5 %6 %7 %8 %9
goto end

:php
call vendor\bin\pint.bat --dirty || goto end
call vendor\bin\phpstan.bat analyse --memory-limit=1G
goto end

:all
node node_modules\typescript\bin\tsc --noEmit || goto end
echo TypeScript: OK
call vendor\bin\pint.bat --dirty || goto end
call vendor\bin\phpstan.bat analyse --memory-limit=1G || goto end
php artisan test || goto end
call "%VP%" build
goto end

:queue
rem Processes queued work (report exports, notifications, broadcasts). Keep it open; Ctrl+C stops it.
php artisan queue:work --tries=3
goto end

:schedule
rem Runs due scheduled jobs every minute (draws, booster payouts, EMI reminders...). Keep it open.
php artisan schedule:work
goto end

:help
echo.
echo  GoldWave helper   (run from the project root:  .\gw ^<command^>)
echo.
echo    .\gw build            Build the frontend (same as "pnpm run build")
echo    .\gw dev              Start the Vite dev server
echo    .\gw types            TypeScript check
echo    .\gw check [files]    Lint + format (only the given files if any)
echo    .\gw front            TypeScript check, then build
echo    .\gw test [filter]    Run Pest tests, e.g.  .\gw test --filter=Payout
echo    .\gw php              Pint (changed files) + Larastan
echo    .\gw all              Everything: types, Pint, Larastan, tests, build
echo.
echo    Keep these running in their own terminal while using the app:
echo    .\gw queue            Background worker - report exports, notifications
echo    .\gw schedule         Scheduler - draws, booster payouts, EMI reminders
echo.

:end
endlocal & exit /b %ERRORLEVEL%
