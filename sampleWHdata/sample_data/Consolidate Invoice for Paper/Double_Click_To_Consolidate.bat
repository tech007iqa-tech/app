@echo off
cls
echo ===================================================
echo             CSV Laptop Consolidator
echo ===================================================
echo.

where py >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    py "%~dp0consolidator.py"
) else (
    where python >nul 2>nul
    if %ERRORLEVEL% EQU 0 (
        python "%~dp0consolidator.py"
    ) else (
        echo Error: Python was not found on your system.
        echo Please ensure Python is installed and added to your PATH.
        echo.
        pause
    )
)
