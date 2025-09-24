<?php
class SecurityManager {
    private $db;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
    }
    
    /**
     * Rate Limiting - controla tentativas de login
     */
    public function checkRateLimit($ip, $usuario = null, $tipo = 'admin', $maxTentativas = 5, $janelaTempo = 300) {
        // Limpar tentativas antigas
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE created_at < datetime('now', '-{$janelaTempo} seconds')
        ");
        $stmt->execute();
        
        // Contar tentativas recentes
        $sql = "
            SELECT COUNT(*) FROM login_attempts 
            WHERE ip_address = ? AND tipo = ? AND sucesso = 0 
            AND created_at > datetime('now', '-{$janelaTempo} seconds')
        ";
        $params = [$ip, $tipo];
        
        if ($usuario) {
            $sql .= " AND usuario = ?";
            $params[] = $usuario;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $tentativas = $stmt->fetchColumn();
        
        if ($tentativas >= $maxTentativas) {
            $this->logAudit(null, 'RATE_LIMIT_EXCEEDED', 'login_attempts', null, [
                'ip' => $ip,
                'usuario' => $usuario,
                'tipo' => $tipo,
                'tentativas' => $tentativas
            ]);
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Registrar tentativa de login
     */
    public function logLoginAttempt($ip, $usuario, $tipo, $sucesso, $userAgent = null) {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, usuario, tipo, sucesso, created_at) 
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        
        return $stmt->execute([$ip, $usuario, $tipo, $sucesso ? 1 : 0]);
    }
    
    /**
     * Sistema de Audit Trail
     */
    public function logAudit($usuarioId, $acao, $tabela, $registroId = null, $dadosAntes = null, $dadosDepois = null) {
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs (
                usuario_id, acao, tabela, registro_id, 
                dados_antes, dados_depois, ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $usuarioId,
            $acao,
            $tabela,
            $registroId,
            is_array($dadosAntes) ? json_encode($dadosAntes) : $dadosAntes,
            is_array($dadosDepois) ? json_encode($dadosDepois) : $dadosDepois,
            $ip,
            $userAgent
        ]);
    }
    
    /**
     * Validar PIN de funcionário
     */
    public function validatePin($pin) {
        // PIN deve ter exatamente 4 dígitos numéricos
        if (!preg_match('/^\d{4}$/', $pin)) {
            return [
                'valid' => false,
                'message' => 'PIN deve ter exatamente 4 dígitos numéricos'
            ];
        }
        
        // Verificar se não é PIN muito óbvio
        $obviousPins = ['0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999', '1234', '4321'];
        if (in_array($pin, $obviousPins)) {
            return [
                'valid' => false,
                'message' => 'PIN muito simples. Escolha uma combinação mais segura.'
            ];
        }
        
        return ['valid' => true, 'message' => 'PIN válido'];
    }
    
    /**
     * Hash seguro do PIN
     */
    public function hashPin($pin) {
        $validation = $this->validatePin($pin);
        if (!$validation['valid']) {
            throw new Exception($validation['message']);
        }
        
        return password_hash($pin, PASSWORD_DEFAULT);
    }
    
    /**
     * Verificar PIN
     */
    public function verifyPin($pin, $hashedPin) {
        return password_verify($pin, $hashedPin);
    }
    
    /**
     * Sanitizar entrada de dados
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        // Remove tags HTML/PHP e caracteres especiais
        $input = strip_tags($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        
        return trim($input);
    }
    
    /**
     * Validar CPF
     */
    public function validateCPF($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        // Verifica se tem 11 dígitos
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }
        
        // Calcula os dígitos verificadores
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Obter IP real do cliente
     */
    public function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Gerar token CSRF
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validar token CSRF
     */
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Verificar força da senha admin
     */
    public function validateAdminPassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = 'Senha deve ter pelo menos 8 caracteres';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra maiúscula';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos uma letra minúscula';
        }
        
        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Senha deve conter pelo menos um número';
        }
        
        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\/?]/', $password)) {
            $errors[] = 'Senha deve conter pelo menos um caractere especial';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Verificar se usuário está ativo
     */
    public function isUserActive($userId) {
        $stmt = $this->db->prepare("SELECT ativo FROM usuarios WHERE id = ?");
        $stmt->execute([$userId]);
        
        return (bool) $stmt->fetchColumn();
    }
    
    /**
     * Obter informações de jornada ativa do usuário
     */
    public function getUserJornada($usuarioId) {
        // Primeiro verifica se há override ativo
        $stmt = $this->db->prepare("
            SELECT * FROM usuario_jornada_override 
            WHERE usuario_id = ? AND ativo = 1 
            AND data_inicio <= DATE('now') 
            AND (data_fim IS NULL OR data_fim >= DATE('now'))
            ORDER BY data_inicio DESC LIMIT 1
        ");
        $stmt->execute([$usuarioId]);
        $override = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($override) {
            return [
                'entrada_manha' => $override['entrada_manha'],
                'saida_almoco' => $override['saida_almoco'],
                'volta_almoco' => $override['volta_almoco'],
                'saida_tarde' => $override['saida_tarde'],
                'carga_diaria_minutos' => $override['carga_diaria_minutos'],
                'tolerancia_minutos' => $override['tolerancia_minutos'],
                'tipo' => 'override',
                'motivo' => $override['motivo']
            ];
        }
        
        // Se não há override, usa o grupo de jornada padrão
        $stmt = $this->db->prepare("
            SELECT gj.* FROM usuarios u 
            JOIN grupos_jornada gj ON u.grupo_jornada_id = gj.id 
            WHERE u.id = ? AND gj.ativo = 1
        ");
        $stmt->execute([$usuarioId]);
        $jornada = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($jornada) {
            return [
                'entrada_manha' => $jornada['entrada_manha'],
                'saida_almoco' => $jornada['saida_almoco'],
                'volta_almoco' => $jornada['volta_almoco'],
                'saida_tarde' => $jornada['saida_tarde'],
                'carga_diaria_minutos' => $jornada['carga_diaria_minutos'],
                'tolerancia_minutos' => $jornada['tolerancia_minutos'],
                'tipo' => 'padrao',
                'grupo_nome' => $jornada['nome']
            ];
        }
        
        return null;
    }
}
?>