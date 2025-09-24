<?php
/**
 * Sistema de Verificação de Atualizações
 * Tech-Ponto - Sistema de Controle de Ponto Eletrônico
 * 
 * Este arquivo verifica se há atualizações disponíveis no GitHub
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/config.php';

class UpdateChecker {
    private $config;
    private $lastCheckFile;
    
    public function __construct() {
        $this->config = [
            'repo_owner' => getUpdateConfig('github_repo_owner'),
            'repo_name' => getUpdateConfig('github_repo_name'),
            'token' => getUpdateConfig('github_token'),
            'current_version' => getUpdateConfig('current_version'),
            'check_interval' => getUpdateConfig('update_check_interval')
        ];
        
        $this->lastCheckFile = dirname(__DIR__) . '/updates/logs/last_check.json';
        
        logUpdateOperation('UPDATE_CHECKER', 'Inicializado', 'INFO');
    }
    
    /**
     * Verifica se há atualizações disponíveis
     */
    public function checkForUpdates($force = false) {
        try {
            // Verificar se deve fazer a verificação (respeitar intervalo)
            if (!$force && !$this->shouldCheckForUpdates()) {
                return [
                    'success' => true,
                    'message' => 'Verificação não necessária (intervalo não atingido)',
                    'data' => [
                        'update_available' => false,
                        'last_check' => $this->getLastCheckTime()
                    ]
                ];
            }
            
            logUpdateOperation('UPDATE_CHECKER', 'Iniciando verificação de atualizações', 'INFO');
            
            // Obter informações da última release
            $latestRelease = $this->getLatestRelease();
            
            if (!$latestRelease) {
                throw new Exception('Não foi possível obter informações da última release');
            }
            
            $latestVersion = $latestRelease['tag_name'];
            $currentVersion = $this->config['current_version'];
            
            // Comparar versões
            $updateAvailable = version_compare($currentVersion, $latestVersion, '<');
            
            // Salvar timestamp da última verificação
            $this->saveLastCheckTime();
            
            $result = [
                'update_available' => $updateAvailable,
                'current_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'last_check' => date('Y-m-d H:i:s'),
                'release_info' => $updateAvailable ? $latestRelease : null
            ];
            
            if ($updateAvailable) {
                logUpdateOperation('UPDATE_CHECKER', "Atualização disponível: {$currentVersion} -> {$latestVersion}", 'INFO');
                
                $result['release_info'] = [
                    'tag_name' => $latestRelease['tag_name'],
                    'name' => $latestRelease['name'],
                    'body' => $latestRelease['body'],
                    'published_at' => $latestRelease['published_at'],
                    'download_url' => $latestRelease['zipball_url'],
                    'size' => $this->getReleaseSize($latestRelease)
                ];
            } else {
                logUpdateOperation('UPDATE_CHECKER', 'Sistema atualizado', 'SUCCESS');
            }
            
            return [
                'success' => true,
                'message' => $updateAvailable ? 'Atualização disponível' : 'Sistema atualizado',
                'data' => $result
            ];
            
        } catch (Exception $e) {
            logUpdateOperation('UPDATE_CHECKER', 'Erro na verificação: ' . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'message' => 'Erro ao verificar atualizações: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }
    
    /**
     * Obtém informações da última release do GitHub
     */
    private function getLatestRelease() {
        $url = "https://api.github.com/repos/{$this->config['repo_owner']}/{$this->config['repo_name']}/releases/latest";
        
        $headers = [
            'User-Agent: TechPonto-Updater/1.0',
            'Accept: application/vnd.github.v3+json'
        ];
        
        // Adicionar token se disponível (para repositórios privados)
        if (!empty($this->config['token'])) {
            $headers[] = "Authorization: token {$this->config['token']}";
        }
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 30
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            throw new Exception('Falha ao conectar com a API do GitHub');
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Resposta inválida da API do GitHub');
        }
        
        if (isset($data['message'])) {
            throw new Exception('Erro da API: ' . $data['message']);
        }
        
        return $data;
    }
    
    /**
     * Verifica se deve fazer a verificação (respeitar intervalo)
     */
    private function shouldCheckForUpdates() {
        if (!file_exists($this->lastCheckFile)) {
            return true;
        }
        
        $lastCheck = json_decode(file_get_contents($this->lastCheckFile), true);
        
        if (!$lastCheck || !isset($lastCheck['timestamp'])) {
            return true;
        }
        
        $timeSinceLastCheck = time() - $lastCheck['timestamp'];
        
        return $timeSinceLastCheck >= $this->config['check_interval'];
    }
    
    /**
     * Salva timestamp da última verificação
     */
    private function saveLastCheckTime() {
        $data = [
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($this->lastCheckFile, json_encode($data), LOCK_EX);
    }
    
    /**
     * Obtém timestamp da última verificação
     */
    private function getLastCheckTime() {
        if (!file_exists($this->lastCheckFile)) {
            return null;
        }
        
        $data = json_decode(file_get_contents($this->lastCheckFile), true);
        
        return $data ? $data['date'] : null;
    }
    
    /**
     * Obtém tamanho estimado da release
     */
    private function getReleaseSize($release) {
        // Tentar obter tamanho dos assets
        if (isset($release['assets']) && !empty($release['assets'])) {
            $totalSize = 0;
            foreach ($release['assets'] as $asset) {
                $totalSize += $asset['size'];
            }
            return $this->formatBytes($totalSize);
        }
        
        // Estimativa baseada no tipo de release
        return '~2-5 MB';
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
     * Obtém informações do sistema atual
     */
    public function getSystemInfo() {
        return [
            'current_version' => $this->config['current_version'],
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'last_check' => $this->getLastCheckTime(),
            'update_config' => [
                'auto_update_enabled' => getUpdateConfig('enable_auto_update'),
                'admin_confirmation_required' => getUpdateConfig('require_admin_confirmation'),
                'rollback_enabled' => getUpdateConfig('enable_rollback')
            ]
        ];
    }
}

// API Endpoint - só executa se for chamado diretamente
if (basename($_SERVER['PHP_SELF']) === 'update_checker.php') {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Verificar autenticação admin
        requireAdmin();
        
        $force = isset($_GET['force']) && $_GET['force'] === 'true';
        
        $checker = new UpdateChecker();
        $result = $checker->checkForUpdates($force);
        
        jsonResponse($result['success'], $result['message'], $result['data']);
    }

    // Endpoint para informações do sistema
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'system_info') {
        requireAdmin();
        
        $checker = new UpdateChecker();
        $systemInfo = $checker->getSystemInfo();
        
        jsonResponse(true, 'Informações do sistema obtidas', $systemInfo);
    }
}
?>
