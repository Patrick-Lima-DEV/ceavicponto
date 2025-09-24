<?php
/**
 * Sistema de Backup Automático
 * Tech-Ponto - Sistema de Controle de Ponto Eletrônico
 * 
 * Este arquivo gerencia backups automáticos antes de atualizações
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/config.php';

class BackupManager {
    private $backupPath;
    private $maxBackups;
    
    public function __construct() {
        $this->backupPath = BACKUP_PATH;
        $this->maxBackups = getUpdateConfig('max_backup_files');
        
        // Garantir que o diretório de backup existe
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
        
        logUpdateOperation('BACKUP_MANAGER', 'Inicializado', 'INFO');
    }
    
    /**
     * Cria backup completo do sistema antes de atualização
     */
    public function createBackup($description = 'Backup automático antes de atualização') {
        try {
            $backupId = $this->generateBackupId();
            $backupDir = $this->backupPath . $backupId . '/';
            
            logUpdateOperation('BACKUP_MANAGER', "Iniciando backup: {$backupId}", 'INFO');
            
            // Criar diretório do backup
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception('Falha ao criar diretório de backup');
            }
            
            // Backup do banco de dados
            $this->backupDatabase($backupDir);
            
            // Backup dos arquivos críticos
            $this->backupCriticalFiles($backupDir);
            
            // Backup das configurações
            $this->backupConfigurations($backupDir);
            
            // Criar arquivo de informações do backup
            $this->createBackupInfo($backupDir, $backupId, $description);
            
            // Limpar backups antigos
            $this->cleanOldBackups();
            
            logUpdateOperation('BACKUP_MANAGER', "Backup criado com sucesso: {$backupId}", 'SUCCESS');
            
            return [
                'success' => true,
                'backup_id' => $backupId,
                'backup_path' => $backupDir,
                'message' => 'Backup criado com sucesso'
            ];
            
        } catch (Exception $e) {
            logUpdateOperation('BACKUP_MANAGER', 'Erro ao criar backup: ' . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Restaura backup específico
     */
    public function restoreBackup($backupId) {
        try {
            $backupDir = $this->backupPath . $backupId . '/';
            
            if (!is_dir($backupDir)) {
                throw new Exception("Backup não encontrado: {$backupId}");
            }
            
            logUpdateOperation('BACKUP_MANAGER', "Iniciando restauração: {$backupId}", 'INFO');
            
            // Verificar integridade do backup
            if (!$this->verifyBackupIntegrity($backupDir)) {
                throw new Exception('Backup corrompido ou inválido');
            }
            
            // Restaurar banco de dados
            $this->restoreDatabase($backupDir);
            
            // Restaurar arquivos críticos
            $this->restoreCriticalFiles($backupDir);
            
            // Restaurar configurações
            $this->restoreConfigurations($backupDir);
            
            logUpdateOperation('BACKUP_MANAGER', "Backup restaurado com sucesso: {$backupId}", 'SUCCESS');
            
            return [
                'success' => true,
                'message' => 'Backup restaurado com sucesso'
            ];
            
        } catch (Exception $e) {
            logUpdateOperation('BACKUP_MANAGER', 'Erro ao restaurar backup: ' . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Lista backups disponíveis
     */
    public function listBackups() {
        $backups = [];
        $backupDirs = glob($this->backupPath . '*', GLOB_ONLYDIR);
        
        foreach ($backupDirs as $backupDir) {
            $backupId = basename($backupDir);
            $infoFile = $backupDir . '/backup_info.json';
            
            if (file_exists($infoFile)) {
                $info = json_decode(file_get_contents($infoFile), true);
                $backups[] = [
                    'id' => $backupId,
                    'created_at' => $info['created_at'],
                    'description' => $info['description'],
                    'size' => $this->getBackupSize($backupDir),
                    'path' => $backupDir
                ];
            }
        }
        
        // Ordenar por data (mais recente primeiro)
        usort($backups, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $backups;
    }
    
    /**
     * Remove backup específico
     */
    public function deleteBackup($backupId) {
        try {
            $backupDir = $this->backupPath . $backupId . '/';
            
            if (!is_dir($backupDir)) {
                throw new Exception("Backup não encontrado: {$backupId}");
            }
            
            $this->deleteDirectory($backupDir);
            
            logUpdateOperation('BACKUP_MANAGER', "Backup removido: {$backupId}", 'INFO');
            
            return [
                'success' => true,
                'message' => 'Backup removido com sucesso'
            ];
            
        } catch (Exception $e) {
            logUpdateOperation('BACKUP_MANAGER', 'Erro ao remover backup: ' . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup do banco de dados
     */
    private function backupDatabase($backupDir) {
        $dbPath = dirname(__DIR__) . '/data/techponto.db';
        $backupDbPath = $backupDir . 'techponto.db';
        
        if (file_exists($dbPath)) {
            if (!copy($dbPath, $backupDbPath)) {
                throw new Exception('Falha ao fazer backup do banco de dados');
            }
        }
    }
    
    /**
     * Backup dos arquivos críticos
     */
    private function backupCriticalFiles($backupDir) {
        $criticalFiles = getUpdateConfig('critical_files');
        
        foreach ($criticalFiles as $file) {
            $sourcePath = dirname(__DIR__) . '/' . $file;
            $backupPath = $backupDir . 'files/' . dirname($file) . '/';
            
            if (file_exists($sourcePath)) {
                if (!is_dir($backupPath)) {
                    mkdir($backupPath, 0755, true);
                }
                
                if (!copy($sourcePath, $backupPath . basename($file))) {
                    throw new Exception("Falha ao fazer backup do arquivo: {$file}");
                }
            }
        }
    }
    
    /**
     * Backup das configurações
     */
    private function backupConfigurations($backupDir) {
        $configDir = $backupDir . 'config/';
        mkdir($configDir, 0755, true);
        
        // Backup das configurações do sistema
        $configFiles = [
            'backend/config/config.php',
            'backend/config/database.php',
            'backend/config/security.php',
            'backend/updates/config.php'
        ];
        
        foreach ($configFiles as $configFile) {
            $sourcePath = dirname(__DIR__) . '/' . $configFile;
            if (file_exists($sourcePath)) {
                copy($sourcePath, $configDir . basename($configFile));
            }
        }
    }
    
    /**
     * Cria arquivo de informações do backup
     */
    private function createBackupInfo($backupDir, $backupId, $description) {
        $info = [
            'backup_id' => $backupId,
            'created_at' => date('Y-m-d H:i:s'),
            'description' => $description,
            'system_info' => [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'current_version' => getUpdateConfig('current_version')
            ],
            'files_backed_up' => getUpdateConfig('critical_files'),
            'directories_backed_up' => getUpdateConfig('critical_directories')
        ];
        
        file_put_contents($backupDir . 'backup_info.json', json_encode($info, JSON_PRETTY_PRINT), LOCK_EX);
    }
    
    /**
     * Verifica integridade do backup
     */
    private function verifyBackupIntegrity($backupDir) {
        $infoFile = $backupDir . 'backup_info.json';
        
        if (!file_exists($infoFile)) {
            return false;
        }
        
        $info = json_decode(file_get_contents($infoFile), true);
        
        if (!$info || !isset($info['backup_id'])) {
            return false;
        }
        
        // Verificar se arquivos críticos existem
        $dbFile = $backupDir . 'techponto.db';
        if (!file_exists($dbFile)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Restaura banco de dados
     */
    private function restoreDatabase($backupDir) {
        $backupDbPath = $backupDir . 'techponto.db';
        $dbPath = dirname(__DIR__) . '/data/techponto.db';
        
        if (file_exists($backupDbPath)) {
            if (!copy($backupDbPath, $dbPath)) {
                throw new Exception('Falha ao restaurar banco de dados');
            }
        }
    }
    
    /**
     * Restaura arquivos críticos
     */
    private function restoreCriticalFiles($backupDir) {
        $filesDir = $backupDir . 'files/';
        
        if (is_dir($filesDir)) {
            $this->copyDirectory($filesDir, dirname(__DIR__) . '/');
        }
    }
    
    /**
     * Restaura configurações
     */
    private function restoreConfigurations($backupDir) {
        $configDir = $backupDir . 'config/';
        
        if (is_dir($configDir)) {
            $configFiles = glob($configDir . '*.php');
            
            foreach ($configFiles as $configFile) {
                $targetFile = dirname(__DIR__) . '/config/' . basename($configFile);
                copy($configFile, $targetFile);
            }
        }
    }
    
    /**
     * Gera ID único para o backup
     */
    private function generateBackupId() {
        return 'backup_' . date('Y-m-d_H-i-s') . '_' . substr(md5(uniqid()), 0, 8);
    }
    
    /**
     * Obtém tamanho do backup
     */
    private function getBackupSize($backupDir) {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($backupDir));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $this->formatBytes($size);
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
     * Limpa backups antigos
     */
    private function cleanOldBackups() {
        $backups = $this->listBackups();
        
        if (count($backups) > $this->maxBackups) {
            $backupsToDelete = array_slice($backups, $this->maxBackups);
            
            foreach ($backupsToDelete as $backup) {
                $this->deleteBackup($backup['id']);
            }
        }
    }
    
    /**
     * Copia diretório recursivamente
     */
    private function copyDirectory($source, $destination) {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $relativePath = str_replace($source, '', $item->getPathname());
            $target = $destination . $relativePath;
            
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                copy($item, $target);
            }
        }
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
}

// API Endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireAdmin();
    
    $action = $_POST['action'] ?? '';
    $backupManager = new BackupManager();
    
    switch ($action) {
        case 'create_backup':
            $description = $_POST['description'] ?? 'Backup manual';
            $result = $backupManager->createBackup($description);
            jsonResponse($result['success'], $result['message'] ?? $result['error'], $result);
            break;
            
        case 'list_backups':
            $backups = $backupManager->listBackups();
            jsonResponse(true, 'Backups listados com sucesso', $backups);
            break;
            
        case 'restore_backup':
            $backupId = $_POST['backup_id'] ?? '';
            if (empty($backupId)) {
                jsonResponse(false, 'ID do backup não fornecido');
            }
            $result = $backupManager->restoreBackup($backupId);
            jsonResponse($result['success'], $result['message'] ?? $result['error'], $result);
            break;
            
        case 'delete_backup':
            $backupId = $_POST['backup_id'] ?? '';
            if (empty($backupId)) {
                jsonResponse(false, 'ID do backup não fornecido');
            }
            $result = $backupManager->deleteBackup($backupId);
            jsonResponse($result['success'], $result['message'] ?? $result['error'], $result);
            break;
            
        default:
            jsonResponse(false, 'Ação não reconhecida');
    }
}
?>
