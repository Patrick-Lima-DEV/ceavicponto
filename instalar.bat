@echo off
CHCP 65001 > NUL
cls
echo ========================================
echo    Tech-Ponto - Instalacao Automatica
echo ========================================
echo.

REM Adicionar pausa no início para debug
echo Pressione qualquer tecla para continuar a instalacao...
pause >nul
echo.

REM Verificar se está executando como administrador
echo Verificando privilegios de administrador...
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo.
    echo ⚠️  AVISO: Nao esta executando como Administrador
    echo.
    echo Para instalacao completa, execute como Administrador:
    echo 1. Clique com botao direito em "instalar.bat"
    echo 2. Selecione "Executar como administrador"
    echo.
    echo Continuando com instalacao limitada...
    echo Pressione qualquer tecla para continuar...
    pause >nul
    echo.
) else (
    echo ✅ Executando como Administrador
    echo.
)

REM Verificar se PHP está instalado
echo [ETAPA 1/6] Verificando PHP...
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ PHP nao encontrado. Instalando automaticamente...
    echo.
    
    REM Verificar conexão com internet
    echo Testando conexao com internet...
    ping -n 1 8.8.8.8 >nul 2>&1
    if %errorlevel% neq 0 (
        echo ❌ ERRO: Sem conexao com internet!
        echo.
        echo SOLUCAO MANUAL:
        echo 1. Conecte-se a internet
        echo 2. OU baixe PHP manualmente de: https://windows.php.net/download/
        echo 3. Extraia para C:\php
        echo 4. Adicione C:\php ao PATH do sistema
        echo.
        echo Pressione qualquer tecla para sair...
        pause >nul
        exit /b 1
    )
    echo ✅ Conexao OK
    echo.
    
    REM Tentar baixar PHP usando PowerShell
    echo Baixando PHP 8.2.12 (pode demorar alguns minutos)...
    powershell -Command "try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/php-8.2.12-Win32-vs16-x64.zip' -OutFile 'php.zip' -UseBasicParsing; Write-Host 'Download concluido' } catch { Write-Host 'Erro no download:' $_.Exception.Message; exit 1 }"
    
    if not exist "php.zip" (
        echo ❌ ERRO: Falha ao baixar PHP!
        echo.
        echo SOLUCAO MANUAL:
        echo 1. Baixe PHP de: https://windows.php.net/download/
        echo 2. Extraia para C:\php
        echo 3. Adicione C:\php ao PATH do sistema
        echo 4. Execute este script novamente
        echo.
        echo Pressione qualquer tecla para sair...
        pause >nul
        exit /b 1
    )
    
    echo ✅ PHP baixado com sucesso
    echo.
    echo Extraindo PHP para C:\php...
    
    REM Remover pasta antiga se existir
    if exist "C:\php" (
        echo Removendo instalacao anterior...
        rmdir /s /q "C:\php" 2>nul
    )
    
    powershell -Command "try { Expand-Archive -Path 'php.zip' -DestinationPath 'C:\' -Force; Write-Host 'Extracao concluida' } catch { Write-Host 'Erro na extracao:' $_.Exception.Message; exit 1 }"
    
    if exist "C:\php\php.exe" (
        echo ✅ PHP extraido com sucesso!
        
        REM Adicionar ao PATH
        echo Configurando PATH do sistema...
        setx PATH "%PATH%;C:\php" /M >nul 2>&1
        if %errorlevel% neq 0 (
            echo ⚠️  AVISO: Falha ao configurar PATH automaticamente
            echo Adicione C:\php ao PATH manualmente
        ) else (
            echo ✅ PATH configurado
        )
        
        REM Atualizar PATH da sessão atual
        set PATH=%PATH%;C:\php
        
        REM Verificar se funcionou
        C:\php\php.exe --version >nul 2>&1
        if %errorlevel% equ 0 (
            echo ✅ PHP configurado corretamente!
        ) else (
            echo ⚠️  AVISO: PHP instalado mas pode precisar reiniciar
        )
    ) else (
        echo ❌ ERRO: Falha ao extrair PHP!
        echo Verifique se tem permissoes para escrever em C:\
        echo.
        echo Pressione qualquer tecla para sair...
        pause >nul
        exit /b 1
    )
    
    REM Limpar arquivo temporário
    if exist "php.zip" del "php.zip"
    echo.
) else (
    echo ✅ PHP encontrado!
    for /f "tokens=*" %%i in ('php --version ^| findstr "PHP"') do echo %%i
    echo.
)

REM Verificar extensões PHP necessárias
echo [ETAPA 2/6] Verificando extensoes PHP...
php -m | findstr "pdo_sqlite" >nul
if %errorlevel% neq 0 (
    echo ❌ AVISO: Extensao PDO SQLite nao encontrada
    echo O sistema pode nao funcionar corretamente
) else (
    echo ✅ PDO SQLite OK
)

php -m | findstr "json" >nul
if %errorlevel% neq 0 (
    echo ❌ AVISO: Extensao JSON nao encontrada
    echo O sistema pode nao funcionar corretamente
) else (
    echo ✅ JSON OK
)
echo.

REM Criar estrutura de diretórios
echo [ETAPA 3/6] Criando estrutura de diretorios...
if not exist "backend" (
    echo ❌ ERRO: Pasta 'backend' nao encontrada!
    echo Certifique-se de estar na pasta raiz do projeto Tech-Ponto
    echo.
    echo Pressione qualquer tecla para sair...
    pause >nul
    exit /b 1
)

if not exist "backend\data" (
    mkdir "backend\data"
    echo ✅ Diretorio backend\data criado
) else (
    echo ✅ Diretorio backend\data existe
)

if not exist "backend\logs" (
    mkdir "backend\logs"
    echo ✅ Diretorio backend\logs criado
) else (
    echo ✅ Diretorio backend\logs existe
)
echo.

REM Inicializar banco de dados
echo [ETAPA 4/6] Inicializando banco de dados...
if not exist "backend\config\database.php" (
    echo ❌ ERRO: Arquivo database.php nao encontrado!
    echo Verifique se todos os arquivos do projeto estao presentes
    echo.
    echo Pressione qualquer tecla para sair...
    pause >nul
    exit /b 1
)

echo Executando script de inicializacao...
php -f "backend\config\database.php"
if %errorlevel% neq 0 (
    echo ❌ ERRO: Falha ao inicializar banco de dados!
    echo.
    echo Verificando permissoes de escrita...
    
    REM Tentar criar arquivo de teste
    echo test > "backend\data\test.txt" 2>nul
    if exist "backend\data\test.txt" (
        del "backend\data\test.txt"
        echo ✅ Permissoes de escrita OK
        echo.
        echo Tentando novamente com diagnostico...
        php -f "backend\config\database.php"
        if %errorlevel% neq 0 (
            echo ❌ ERRO: Falha persistente no banco de dados!
            echo.
            echo Execute 'verificar.bat' para diagnostico completo
            echo.
            echo Pressione qualquer tecla para sair...
            pause >nul
            exit /b 1
        )
    ) else (
        echo ❌ ERRO: Sem permissao de escrita em backend\data
        echo Execute como Administrador ou verifique permissoes
        echo.
        echo Pressione qualquer tecla para sair...
        pause >nul
        exit /b 1
    )
) else (
    echo ✅ Banco de dados inicializado com sucesso!
)
echo.

REM Aplicar migrações automáticas
echo [ETAPA 5/6] Aplicando migracoes automaticas...
if exist "backend\migrations\auto_migrate.php" (
    echo Executando migracoes...
    php -f "backend\migrations\auto_migrate.php"
    if %errorlevel% equ 0 (
        echo ✅ Migracoes automaticas aplicadas com sucesso!
    ) else (
        echo ⚠️  AVISO: Falha ao aplicar migracoes automaticas
        echo Sistema pode funcionar sem elas
    )
) else (
    echo ⚠️  Nenhuma migracao automatica encontrada
)
echo.

REM Testar sistema
echo [ETAPA 6/6] Testando sistema...
echo Testando conexao com banco...
php -r "
try {
    \$pdo = new PDO('sqlite:backend/data/techponto.db');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM usuarios');
    \$count = \$stmt->fetchColumn();
    echo '✅ Banco OK - ' . \$count . ' usuarios encontrados' . PHP_EOL;
    
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM usuarios WHERE tipo = \"admin\"');
    \$adminCount = \$stmt->fetchColumn();
    echo '✅ Administradores: ' . \$adminCount . PHP_EOL;
    
    \$stmt = \$pdo->query('SELECT COUNT(*) FROM departamentos');
    \$deptCount = \$stmt->fetchColumn();
    echo '✅ Departamentos: ' . \$deptCount . PHP_EOL;
    
    exit(0);
} catch (Exception \$e) {
    echo '❌ ERRO: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if %errorlevel% equ 0 (
    echo ✅ Sistema testado com sucesso!
) else (
    echo ❌ AVISO: Problemas detectados no sistema
    echo Execute 'verificar.bat' para diagnostico detalhado
)
echo.

REM Criar backup inicial
echo Criando backup inicial...
if exist "backend\data\techponto.db" (
    set "data_backup=%date:~-4,4%%date:~-10,2%%date:~-7,2%"
    copy "backend\data\techponto.db" "backend\data\techponto_backup_%data_backup%.db" >nul 2>&1
    if %errorlevel% equ 0 (
        echo ✅ Backup criado: techponto_backup_%data_backup%.db
    ) else (
        echo ⚠️  AVISO: Falha ao criar backup
    )
) else (
    echo ⚠️  AVISO: Banco nao encontrado para backup
)

echo.
echo ========================================
echo    INSTALACAO CONCLUIDA COM SUCESSO!
echo ========================================
echo.
echo 🎉 O sistema Tech-Ponto foi instalado com sucesso!
echo.
echo 👥 USUARIOS PADRAO:
echo - Admin: admin / admin123
echo - Funcionario: CPF 12345678901 / PIN 1234
echo.
echo 🚀 PROXIMOS PASSOS:
echo 1. Execute 'iniciar.bat' para iniciar o sistema
echo 2. Acesse http://localhost:8000
echo 3. Teste o login admin e funcionario
echo.
echo 📝 OBSERVACOES:
echo - Se houver problemas, execute 'verificar.bat'
echo - Execute como Administrador se necessario
echo - Logs estao em backend\logs\
echo.
echo ✅ Instalacao concluida! Pressione qualquer tecla para sair...
pause >nul