<?php
/**
 * Sistema de Instalação de Atualizações
 * Tech-Ponto - Sistema de Controle de Ponto Eletrônico
 * 
 * Este arquivo gerencia a instalação de atualizações com rollback automático
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backup_manager.php';
require_once dirname(__DIR__) . '/config/config.php';

class UpdateInstaller {
    private $backupManager;
    private $tempPath;
    private $currentBackupId;
    
    public function __construct() {
        $this->backupManager = new BackupManager();
        $this->tempPath = TEMP_PATH;
        
        // Garantir que o diretório temporário existe
        if (!is_dir($this->tempPath)) {
            mkdir($this->tempPath, 0755, true);
        }
        
        logUpdateOperation('UPDATE_INSTALLER', 'Inicializado', 'INFO');
    }
    
    /**
     * Instala atualização completa
     */
    public function installUpdate($downloadUrl, $version) {
        try {
            logUpdateOperation('UPDATE_INSTALLER', "Iniciando instalação da versão: {$version}", 'INFO');
            
            // 1. Criar backup antes da instalação
            $backupResult = $this->backupManager->createBackup("Backup antes da atualização para v{$version}");
            
            if (!$backupResult['success']) {
                throw new Exception('Falha ao criar backup: ' . $backupResult['error']);
            }
            
            $this->currentBackupId = $backupResult['backup_id'];
            
            // 2. Baixar atualização
            $zipFile = $this->downloadUpdate($downloadUrl, $version);
            
            // 3. Verificar integridade do download
            $this->verifyDownloadIntegrity($zipFile);
            
            // 4. Extrair atualização
            $extractedPath = $this->extractUpdate($zipFile);
            
            // 5. Aplicar atualizações
            $this->applyUpdate($extractedPath);
            
            // 6. Executar migrações de banco
            $this->runMigrations();
            
            // 7. Verificar integridade do sistema
            $this->verifySystemIntegrity();
            
            // 8. Limpar arquivos temporários
            $this->cleanupTempFiles($zipFile, $extractedPath);
            
            logUpdateOperation('UPDATE_INSTALLER', "Atualização instalada com sucesso: v{$version}", 'SUCCESS');
            
            return [
                'success' => true,
                'message' => "Atualização para v{$version} instalada com sucesso!",
                'data' => [
                    'version' => $version,
                    'backup_id' => $this->currentBackupId,
                    'installed_at' => date('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            logUpdateOperation('UPDATE_INSTALLER', 'Erro na instalação: ' . $e->getMessage(), 'ERROR');
            
            // Tentar rollback automático
            if ($this->currentBackupId) {
                $this->performRollback();
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'rollback_performed' => $this->currentBackupId ? true : false
            ];
        }
    }
    
    /**
     * Baixa atualização do GitHub
     */
    private function downloadUpdate($downloadUrl, $version) {
        $zipFile = $this->tempPath . "update_{$version}_" . time() . ".zip";
        
        logUpdateOperation('UPDATE_INSTALLER', "Baixando atualização: {$downloadUrl}", 'INFO');
        
        $headers = [
            'User-Agent: TechPonto-Updater/1.0'
        ];
        
        // Adicionar token se disponível
        $token = getUpdateConfig('github_token');
        if (!empty($token)) {
            $headers[] = "Authorization: token {$token}";
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => getUpdateConfig('update_timeout')
            ]
        ]);
        
        $zipContent = @file_get_contents($downloadUrl, false, $context);
        
        if ($zipContent === false) {
            throw new Exception('Falha ao baixar atualização do GitHub');
        }
        
        if (file_put_contents($zipFile, $zipContent) === false) {
            throw new Exception('Falha ao salvar arquivo de atualização');
        }
        
        logUpdateOperation('UPDATE_INSTALLER', "Download concluído: " . basename($zipFile), 'INFO');
        
        return $zipFile;
    }
    
    /**
     * Verifica integridade do download
     */
    private function verifyDownloadIntegrity($zipFile) {
        if (!file_exists($zipFile)) {
            throw new Exception('Arquivo de atualização não encontrado');
        }
        
        $fileSize = filesize($zipFile);
        if ($fileSize < 1024) { // Menos de 1KB
            throw new Exception('Arquivo de atualização muito pequeno (possivelmente corrompido)');
        }
        
        // Verificar se é um arquivo ZIP válido
        $zip = new ZipArchive();
        $result = $zip->open($zipFile, ZipArchive::CHECKCONS);
        
        if ($result !== TRUE) {
            throw new Exception('Arquivo de atualização corrompido ou inválido');
        }
        
        $zip->close();
        
        logUpdateOperation('UPDATE_INSTALLER', "Integridade verificada: " . $this->formatBytes($fileSize), 'INFO');
    }
    
    /**
     * Extrai arquivo de atualização
     */
    private function extractUpdate($zipFile) {
        $extractPath = $this->tempPath . "extracted_" . time() . "/";
        
        logUpdateOperation('UPDATE_INSTALLER', "Extraindo atualização...", 'INFO');
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== TRUE) {
            throw new Exception('Falha ao abrir arquivo de atualização');
        }
        
        if (!$zip->extractTo($extractPath)) {
            $zip->close();
            throw new Exception('Falha ao extrair arquivo de atualização');
        }
        
        $zip->close();
        
        logUpdateOperation('UPDATE_INSTALLER', "Extração concluída: {$extractPath}", 'INFO');
        
        return $extractPath;
    }
    
    /**
     * Aplica atualizações aos arquivos do sistema
     */
    private function applyUpdate($extractedPath) {
        logUpdateOperation('UPDATE_INSTALLER', "Aplicando atualizações...", 'INFO');
        
        // Encontrar diretório raiz da extração (GitHub cria subdiretório)
        $rootDirs = glob($extractedPath . '*', GLOB_ONLYDIR);
        if (empty($rootDirs)) {
            throw new Exception('Estrutura de atualização inválida');
        }
        
        $updateRoot = $rootDirs[0] . '/';
        
        // Verificar se contém estrutura esperada
        if (!is_dir($updateRoot . 'frontend') || !is_dir($updateRoot . 'backend')) {
            throw new Exception('Estrutura de atualização inválida (frontend/backend não encontrados)');
        }
        
        // Aplicar atualizações preservando arquivos críticos
        $this->updateFiles($updateRoot . 'frontend/', dirname(__DIR__) . '/frontend/');
        $this->updateFiles($updateRoot . 'backend/', dirname(__DIR__) . '/backend/');
        
        logUpdateOperation('UPDATE_INSTALLER', "Atualizações aplicadas com sucesso", 'INFO');
    }
    
    /**
     * Atualiza arquivos preservando configurações críticas
     */
    private function updateFiles($sourceDir, $targetDir) {
        $criticalFiles = getUpdateConfig('critical_files');
        $criticalDirs = getUpdateConfig('critical_directories');
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = str_replace($sourceDir, '', $item->getPathname());
            $targetPath = $targetDir . $relativePath;
            
            // Verificar se é arquivo crítico
            $isCritical = false;
            foreach ($criticalFiles as $criticalFile) {
                if (strpos($relativePath, $criticalFile) !== false) {
                    $isCritical = true;
                    break;
                }
            }
            
            // Verificar se está em diretório crítico
            foreach ($criticalDirs as $criticalDir) {
                if (strpos($relativePath, $criticalDir) !== false) {
                    $isCritical = true;
                    break;
                }
            }
            
            // Pular arquivos críticos
            if ($isCritical) {
                logUpdateOperation('UPDATE_INSTALLER', "Preservando arquivo crítico: {$relativePath}", 'INFO');
                continue;
            }
            
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                // Criar diretório pai se não existir
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                
                if (!copy($item->getPathname(), $targetPath)) {
                    throw new Exception("Falha ao atualizar arquivo: {$relativePath}");
                }
            }
        }
    }
    
    /**
     * Executa migrações de banco de dados
     */
    private function runMigrations() {
        logUpdateOperation('UPDATE_INSTALLER', "Executando migrações de banco...", 'INFO');
        
        $migrationFiles = [
            'backend/migrations/auto_migrate.php',
            'backend/migrations/create_justificativas_manual.php'
        ];
        
        foreach ($migrationFiles as $migrationFile) {
            $fullPath = dirname(__DIR__) . '/' . $migrationFile;
            
            if (file_exists($fullPath)) {
                try {
                    include $fullPath;
                    logUpdateOperation('UPDATE_INSTALLER', "Migração executada: {$migrationFile}", 'INFO');
                } catch (Exception $e) {
                    logUpdateOperation('UPDATE_INSTALLER', "Erro na migração {$migrationFile}: " . $e->getMessage(), 'ERROR');
                    // Não falhar por causa de migrações opcionais
                }
            }
        }
    }
    
    /**
     * Verifica integridade do sistema após atualização
     */
    private function verifySystemIntegrity() {
        logUpdateOperation('UPDATE_INSTALLER', "Verificando integridade do sistema...", 'INFO');
        
        // Verificar se banco de dados está acessível
        try {
            $db = new PDO('sqlite:' . dirname(__DIR__) . '/data/techponto.db');
            $db->query('SELECT COUNT(*) FROM usuarios');
            logUpdateOperation('UPDATE_INSTALLER', "Banco de dados verificado", 'INFO');
        } catch (Exception $e) {
            throw new Exception('Falha na verificação do banco de dados: ' . $e->getMessage());
        }
        
        // Verificar se arquivos críticos existem
        $criticalFiles = getUpdateConfig('critical_files');
        foreach ($criticalFiles as $file) {
            $fullPath = dirname(__DIR__) . '/' . $file;
            if (!file_exists($fullPath)) {
                throw new Exception("Arquivo crítico não encontrado após atualização: {$file}");
            }
        }
        
        logUpdateOperation('UPDATE_INSTALLER', "Integridade do sistema verificada", 'SUCCESS');
    }
    
    /**
     * Executa rollback para versão anterior
     */
    private function performRollback() {
        if (!$this->currentBackupId) {
            logUpdateOperation('UPDATE_INSTALLER', "Nenhum backup disponível para rollback", 'ERROR');
            return false;
        }
        
        try {
            logUpdateOperation('UPDATE_INSTALLER', "Executando rollback para: {$this->currentBackupId}", 'INFO');
            
            $result = $this->backupManager->restoreBackup($this->currentBackupId);
            
            if ($result['success']) {
                logUpdateOperation('UPDATE_INSTALLER', "Rollback executado com sucesso", 'SUCCESS');
                return true;
            } else {
                logUpdateOperation('UPDATE_INSTALLER', "Falha no rollback: " . $result['error'], 'ERROR');
                return false;
            }
            
        } catch (Exception $e) {
            logUpdateOperation('UPDATE_INSTALLER', "Erro no rollback: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Limpa arquivos temporários
     */
    private function cleanupTempFiles($zipFile, $extractedPath) {
        logUpdateOperation('UPDATE_INSTALLER', "Limpando arquivos temporários...", 'INFO');
        
        // Remover arquivo ZIP
        if (file_exists($zipFile)) {
            unlink($zipFile);
        }
        
        // Remover diretório extraído
        if (is_dir($extractedPath)) {
            $this->deleteDirectory($extractedPath);
        }
        
        logUpdateOperation('UPDATE_INSTALLER', "Limpeza concluída", 'INFO');
    }
    
    /**
     * Remove diretório recursivamente
     */
    private function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Formata bytes em formato legível
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Obtém status da instalação
     */
    public function getInstallationStatus() {
        $statusFile = LOG_PATH . 'installation_status.json';
        
        if (file_exists($statusFile)) {
            return json_decode(file_get_contents($statusFile), true);
        }
        
        return [
            'status' => 'idle',
            'last_installation' => null,
            'current_version' => getUpdateConfig('current_version')
        ];
    }
}

// API Endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    
    $action = $_POST['action'] ?? '';
    $installer = new UpdateInstaller();
    
    switch ($action) {
        case 'install_update':
            $downloadUrl = $_POST['download_url'] ?? '';
            $version = $_POST['version'] ?? '';
            
            if (empty($downloadUrl) || empty($version)) {
                jsonResponse(false, 'URL de download e versão são obrigatórios');
            }
            
            $result = $installer->installUpdate($downloadUrl, $version);
            jsonResponse($result['success'], $result['message'] ?? $result['error'], $result);
            break;
            
        case 'get_status':
            $status = $installer->getInstallationStatus();
            jsonResponse(true, 'Status obtido com sucesso', $status);
            break;
            
        default:
            jsonResponse(false, 'Ação não reconhecida');
    }
}
?>
