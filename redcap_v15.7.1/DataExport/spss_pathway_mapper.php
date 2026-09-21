<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Logging
Logging::logEvent("","","MANAGE",$project_id,"project_id = $project_id","Download SPSS Pathway Mapper file");

// Download as file
error_reporting(0);
header('Pragma: anytextexeptno-cache', true);
header("Content-type: application/bat");

header("Content-Disposition: attachment; filename=SPSS_Pathway_Mapper.bat");
?>
@echo off
for /f "delims=" %%D in ('powershell -NoProfile -Command "Get-Item -LiteralPath '%~dp0' | Select-Object -ExpandProperty FullName"') do set CurrentDir=%%D
for %%x in ("%CurrentDir%*.sps") do (
	echo Processing file: %%x
	type "%%x" | find /v "FILE HANDLE data1 NAME=" > "%CurrentDir%tmp.dat"
	call :sub "%%x"
	del "%%x"
	copy /b "%CurrentDir%insert.dat"+"%CurrentDir%tmp.dat" "%%x"
)
del "%CurrentDir%insert.dat" "%CurrentDir%tmp.dat"
cls
echo.
echo.
echo   STEP #1: Pathway mapping is COMPLETE!
echo.
echo.
echo   NOW...
echo.
echo   STEP #2: Double-click the *.sps file, which will open SPSS.
echo.
echo   STEP #3: Once SPSS has opened, choose Run-^>All from the top menu options.
echo.
pause
goto :EOF
:sub
    set fn0=%~n1
    set fn1=%fn0:_SPSS_2=_DATA_NOHDRS_2%
    powershell -Command "& {Set-Content -Path '%CurrentDir%insert.dat' -Value 'FILE HANDLE data1 NAME=\"%CurrentDir%%fn1%.csv\" LRECL=90000.' -Encoding UTF8}"
