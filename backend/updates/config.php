<?php
/**
 * Arquivo de Configuração de Exemplo
 * Tech-Ponto - Sistema de Controle de Ponto Eletrônico
 * 
 * Copie este arquivo para config.php e configure as opções
 */

// ========================================
// CONFIGURAÇÕES DO GITHUB
// ========================================

// Seu usuário GitHub (ALTERE AQUI)
define('GITHUB_REPO_OWNER', 'Patrick-Lima-DEV');

// Nome do repositório (ALTERE AQUI)
define('GITHUB_REPO_NAME', 'ceavicponto');

// Token de acesso pessoal (deixe vazio para repositórios públicos)
// Para repositórios privados, crie um token em:
// GitHub → Settings → Developer settings → Personal access tokens
define('GITHUB_TOKEN', '');

// ========================================
// CONFIGURAÇÕES DO SISTEMA
// ========================================

// Versão atual do sistema
define('CURRENT_VERSION', '1.0.0');

// Intervalo entre verificações automáticas (em segundos)
// 86400 = 24 horas
define('UPDATE_CHECK_INTERVAL', 86400);

// Número máximo de backups a manter
define('MAX_BACKUP_FILES', 5);

// Timeout para download de atualizações (em segundos)
define('UPDATE_TIMEOUT', 300);

// ========================================
// CONFIGURAÇÕES DE SEGURANÇA
// ========================================

// Habilitar atualização automática (NÃO RECOMENDADO)
define('ENABLE_AUTO_UPDATE', false);

// Sempre requer confirmação do administrador
define('REQUIRE_ADMIN_CONFIRMATION', true);

// Habilitar rollback automático em caso de erro
define('ENABLE_ROLLBACK', true);

// ========================================
// CONFIGURAÇÕES DE LOG
// ========================================

// Registrar tentativas de atualização
define('LOG_UPDATE_ATTEMPTS', true);

// Registrar atualizações bem-sucedidas
define('LOG_UPDATE_SUCCESS', true);

// Registrar erros de atualização
define('LOG_UPDATE_ERRORS', true);

// Registrar operações de backup
define('LOG_BACKUP_OPERATIONS', true);

// ========================================
// CONFIGURAÇÕES DE NOTIFICAÇÃO
// ========================================

// Notificar quando atualização estiver disponível
define('NOTIFY_ON_UPDATE_AVAILABLE', true);

// Notificar quando atualização for bem-sucedida
define('NOTIFY_ON_UPDATE_SUCCESS', true);

// Notificar quando houver erro na atualização
define('NOTIFY_ON_UPDATE_ERROR', true);

// ========================================
// VALIDAÇÃO DE INTEGRIDADE
// ========================================

// Verificar integridade do download
define('VERIFY_DOWNLOAD_INTEGRITY', true);

// Verificar permissões de arquivos
define('VERIFY_FILE_PERMISSIONS', true);

// Verificar integridade do banco de dados
define('VERIFY_DATABASE_INTEGRITY', true);

// ========================================
// ARQUIVOS CRÍTICOS
// ========================================

// Arquivos que devem ser preservados durante atualizações
define('CRITICAL_FILES', [
    'backend/data/techponto.db',
    'backend/config/config.php',
    'backend/config/database.php',
    'backend/config/security.php',
    'backend/updates/config.php'
]);

// Diretórios que devem ser preservados durante atualizações
define('CRITICAL_DIRECTORIES', [
    'backend/data/',
    'backend/logs/',
    'backend/updates/',
    'logo/'
]);

// ========================================
// INSTRUÇÕES DE CONFIGURAÇÃO
// ========================================

/*
PASSOS PARA CONFIGURAR:

1. COPIE ESTE ARQUIVO:
   cp config.example.php config.php

2. CONFIGURE O GITHUB:
   - Defina GITHUB_REPO_OWNER com seu usuário
   - Defina GITHUB_REPO_NAME com o nome do repositório
   - Para repositórios privados, configure GITHUB_TOKEN

3. CONFIGURE A VERSÃO:
   - Defina CURRENT_VERSION com a versão atual

4. TESTE O SISTEMA:
   - Acesse: http://localhost:8000/backend/updates/test_system.php
   - Verifique se todos os testes passam

5. CRIE SEU REPOSITÓRIO GITHUB:
   git init
   git add .
   git commit -m "Versão inicial 2.0.0"
   git remote add origin https://github.com/SEU_USUARIO/ceavicponto.git
   git push -u origin main

6. CRIE SUA PRIMEIRA RELEASE:
   git tag v2.1.0
   git push origin v2.1.0
   # Depois crie a release no GitHub

7. TESTE AS ATUALIZAÇÕES:
   - Acesse o painel admin
   - Vá para a aba "Atualizações"
   - Clique em "Verificar Agora"

IMPORTANTE:
- Sempre teste em ambiente de desenvolvimento primeiro
- Mantenha backups regulares
- Monitore os logs de atualização
- Use tokens GitHub com permissões mínimas necessárias
*/

/**
 * Função para obter configuração
 */
function getUpdateConfig($key, $default = null) {
    $config = [
        'github_repo_owner' => GITHUB_REPO_OWNER,
        'github_repo_name' => GITHUB_REPO_NAME,
        'github_token' => GITHUB_TOKEN,
        'current_version' => CURRENT_VERSION,
        'update_check_interval' => UPDATE_CHECK_INTERVAL,
        'max_backup_files' => MAX_BACKUP_FILES,
        'update_timeout' => UPDATE_TIMEOUT,
        'enable_auto_update' => ENABLE_AUTO_UPDATE,
        'require_admin_confirmation' => REQUIRE_ADMIN_CONFIRMATION,
        'enable_rollback' => ENABLE_ROLLBACK,
        'critical_files' => CRITICAL_FILES,
        'critical_directories' => CRITICAL_DIRECTORIES
    ];
    
    return isset($config[$key]) ? $config[$key] : $default;
}

/**
 * Função para log de operações
 */
function logUpdateOperation($operation, $message, $level = 'INFO') {
    if (!LOG_UPDATE_ATTEMPTS && $level === 'INFO') return;
    if (!LOG_UPDATE_SUCCESS && $level === 'SUCCESS') return;
    if (!LOG_UPDATE_ERRORS && $level === 'ERROR') return;
    
    $logFile = dirname(__DIR__) . '/updates/logs/update_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] [{$operation}] {$message}" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * Função para limpar logs antigos
 */
function cleanOldLogs() {
    $logFiles = glob(dirname(__DIR__) . '/updates/logs/update_*.log');
    $maxAge = 30 * 24 * 60 * 60; // 30 dias
    
    foreach ($logFiles as $logFile) {
        if (filemtime($logFile) < (time() - $maxAge)) {
            unlink($logFile);
        }
    }
}

/**
 * Função para validar configurações
 */
function validateUpdateConfig() {
    $errors = [];
    
    // Verificar se os diretórios existem
    $backupPath = dirname(__DIR__) . '/updates/backups/';
    $tempPath = dirname(__DIR__) . '/updates/temp/';
    $logPath = dirname(__DIR__) . '/updates/logs/';
    
    if (!is_dir($backupPath)) {
        $errors[] = "Diretório de backup não existe: " . $backupPath;
    }
    
    if (!is_dir($tempPath)) {
        $errors[] = "Diretório temporário não existe: " . $tempPath;
    }
    
    if (!is_dir($logPath)) {
        $errors[] = "Diretório de logs não existe: " . $logPath;
    }
    
    // Verificar permissões de escrita
    if (!is_writable($backupPath)) {
        $errors[] = "Sem permissão de escrita no diretório de backup";
    }
    
    if (!is_writable($tempPath)) {
        $errors[] = "Sem permissão de escrita no diretório temporário";
    }
    
    if (!is_writable($logPath)) {
        $errors[] = "Sem permissão de escrita no diretório de logs";
    }
    
    // Verificar se arquivos críticos existem
    foreach (CRITICAL_FILES as $file) {
        $fullPath = dirname(__DIR__) . '/' . $file;
        if (!file_exists($fullPath)) {
            $errors[] = "Arquivo crítico não encontrado: " . $file;
        }
    }
    
    return $errors;
}
?>
