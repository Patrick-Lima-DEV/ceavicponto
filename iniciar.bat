@echo off
CHCP 65001 > NUL
cls
title Tech-Ponto - Sistema de Controle de Ponto
echo ========================================
echo    🚀 Tech-Ponto - Sistema de Ponto
echo ========================================
echo.

REM Buscar projeto automaticamente
call :BUSCAR_PROJETO
if "%PROJETO_PATH%"=="" (
    echo ❌ Projeto não encontrado!
    pause
    exit /b 1
)

REM Navegar para o projeto e executar
cd /d "%PROJETO_PATH%"
echo ✅ Projeto: %PROJETO_PATH%
echo.

REM Instalar e iniciar sistema
call :EXECUTAR_SISTEMA
goto :EOF

:BUSCAR_PROJETO
set "PROJETO_PATH="
echo 🔍 Procurando projeto...

REM Pasta atual
if exist "backend\config\database.php" (
    set "PROJETO_PATH=%CD%"
    echo ✅ Encontrado na pasta atual
    exit /b 0
)

REM Caminho provável
set "CAMINHO=%USERPROFILE%\Área de Trabalho\bkp\ceavicponto"
if exist "%CAMINHO%\backend\config\database.php" (
    set "PROJETO_PATH=%CAMINHO%"
    echo ✅ Encontrado
    exit /b 0
)

REM Outros locais comuns
for %%c in (
    "%USERPROFILE%\Desktop\bkp\ceavicponto"
    "%USERPROFILE%\Desktop\ceavicponto"
    "%USERPROFILE%\Downloads\ceavicponto"
    "C:\ceavicponto"
) do (
    if exist "%%c\backend\config\database.php" (
        set "PROJETO_PATH=%%c"
        echo ✅ Encontrado em: %%c
        exit /b 0
    )
)

REM Entrada manual
echo.
echo ❌ Digite o caminho da pasta do projeto:
echo Exemplo: C:\Users\usuar\Área de Trabalho\bkp\ceavicponto
set /p "CAMINHO_MANUAL=Caminho: "

if exist "%CAMINHO_MANUAL%\backend\config\database.php" (
    set "PROJETO_PATH=%CAMINHO_MANUAL%"
    echo ✅ Encontrado!
    exit /b 0
)

echo ❌ Projeto não encontrado
exit /b 1

:EXECUTAR_SISTEMA
REM Verificar/Instalar PHP
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ⚠️ Instalando PHP...
    call :INSTALAR_PHP
    if %errorlevel% neq 0 exit /b 1
)

REM Verificar/Instalar Sistema
if exist "backend\data\techponto.db" (
    echo ✅ Sistema instalado
) else (
    echo ⚠️ Instalando sistema...
    call :INSTALAR_SISTEMA
    if %errorlevel% neq 0 exit /b 1
)

REM Iniciar servidor
call :INICIAR_SERVIDOR
exit /b 0

:INSTALAR_PHP
if exist "C:\php\php.exe" (
    set PATH=%PATH%;C:\php
    php --version >nul 2>&1
    if %errorlevel% equ 0 (
        echo ✅ PHP OK
        exit /b 0
    )
)

echo 📥 Baixando PHP...
powershell -Command "try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/php-8.2.12-Win32-vs16-x64.zip' -OutFile 'php.zip' -UseBasicParsing } catch { exit 1 }"

if not exist "php.zip" (
    echo ❌ Erro ao baixar PHP
    exit /b 1
)

echo 📂 Instalando PHP...
powershell -Command "try { Expand-Archive -Path 'php.zip' -DestinationPath 'C:\' -Force } catch { exit 1 }"

if exist "C:\php\php.exe" (
    set PATH=%PATH%;C:\php
    setx PATH "%PATH%;C:\php" /M >nul 2>&1
    del php.zip >nul 2>&1
    echo ✅ PHP instalado
    exit /b 0
) else (
    echo ❌ Erro na instalação do PHP
    exit /b 1
)

:INSTALAR_SISTEMA
echo 🔧 Instalando sistema...

if not exist "backend\data" mkdir "backend\data"
if not exist "backend\logs" mkdir "backend\logs"

if exist "backend\config\database.php" (
    echo 📊 Criando banco de dados...
    php -f "backend\config\database.php"
    if %errorlevel% neq 0 (
        echo ❌ Erro ao criar banco
        exit /b 1
    )
) else (
    echo ❌ Arquivo database.php não encontrado
    exit /b 1
)

if exist "backend\migrations\auto_migrate.php" (
    echo 🔄 Aplicando atualizações...
    php -f "backend\migrations\auto_migrate.php"
)

echo ✅ Sistema instalado!
exit /b 0

:INICIAR_SERVIDOR
echo 🚀 Iniciando servidor...

REM Encontrar porta disponível
set PORTA=8000
:LOOP_PORTA
netstat -an | findstr ":%PORTA% " >nul 2>&1
if %errorlevel% equ 0 (
    set /a PORTA+=1
    if %PORTA% lss 8010 goto LOOP_PORTA
    echo ❌ Nenhuma porta disponível
    exit /b 1
)

echo.
echo ========================================
echo   🌐 SISTEMA TECH-PONTO INICIADO
echo ========================================
echo.
echo 🌐 Acesse: http://localhost:%PORTA%
echo.
echo 👨‍💼 ADMINISTRADOR:
echo    Login: admin
echo    Senha: admin123
echo.
echo 👤 FUNCIONÁRIO:
echo    CPF: 12345678901
echo    PIN: 1234
echo.
echo ⚡ Abrindo navegador automaticamente...
echo 🛑 Pressione Ctrl+C para parar o servidor
echo.

timeout /t 3 /nobreak >nul
start http://localhost:%PORTA%

echo 🚀 Servidor rodando na porta %PORTA%...
php -S localhost:%PORTA% -t .

echo.
echo 🛑 Servidor parado
pause
exit /b 0