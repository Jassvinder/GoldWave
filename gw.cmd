@echo off
rem GoldWave helper - runs the frontend/backend tools directly, so it keeps
rem working while Windows Smart App Control blocks pnpm.exe (pnpm 12 ships a
rem native exe). Usage from the project root:  .\gw build   (see "gw help").
setlocal
cd /d "%~dp0"

set "VP=node_modules\.bin\vp.cmd"
set "DEPLOY_HOST=root@76.13.191.232"
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
if /i "%CMD%"=="deploy" goto deploy
if /i "%CMD%"=="deploy-setup" goto deploy_setup
if /i "%CMD%"=="deploy-rollback" goto deploy_rollback
if /i "%CMD%"=="deploy-status" goto deploy_status
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

rem --- Live server releases (Docs/DEPLOYMENT.md section 0). The server side is deploy\server-deploy.sh.
:deploy
rem Only committed code goes live: the package is "git archive HEAD" (minus export-ignore paths) + public/build.
git status --short | findstr . >nul && (
    git status --short
    echo.
    echo These changes are NOT committed and will NOT be deployed.
    choice /m "Deploy only the committed code"
    if errorlevel 2 goto end
)
call "%VP%" build || goto end
for /f %%i in ('git rev-parse --short HEAD') do set "SHA=%%i"
set "CODE=%TEMP%\goldwave-code.tar"
set "PKG=%TEMP%\goldwave-%SHA%.tar.gz"
git archive --format=tar -o "%CODE%" HEAD || goto end
"%SystemRoot%\System32\tar.exe" -czf "%PKG%" "@%CODE%" public/build || goto end
echo Uploading commit %SHA% ...
scp "%PKG%" deploy\server-deploy.sh %DEPLOY_HOST%:/root/ || goto end
ssh %DEPLOY_HOST% "bash /root/server-deploy.sh deploy /root/goldwave-%SHA%.tar.gz"
del "%CODE%" "%PKG%" 2>nul
goto end

:deploy_setup
scp deploy\server-deploy.sh %DEPLOY_HOST%:/root/ || goto end
ssh %DEPLOY_HOST% "bash /root/server-deploy.sh setup"
goto end

:deploy_rollback
ssh %DEPLOY_HOST% "bash /root/server-deploy.sh rollback"
goto end

:deploy_status
ssh %DEPLOY_HOST% "bash /root/server-deploy.sh status"
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
echo    Live server (committed code only):
echo    .\gw deploy           Build, upload and switch the site to a new release
echo    .\gw deploy-rollback  Switch back to the previous release (code only)
echo    .\gw deploy-status    Show the releases and which one is live
echo    .\gw deploy-setup     One time: convert the server to the releases layout
echo.

:end
endlocal & exit /b %ERRORLEVEL%
