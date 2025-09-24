<?php
class Database {
    private $db;
    private $dbPath;
    
    public function __construct() {
        $this->dbPath = dirname(__DIR__) . '/data/techponto.db';
        $this->createDatabase();
    }
    
    private function createDatabase() {
        try {
            // Criar diretório data se não existir
            $dataDir = dirname($this->dbPath);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0777, true);
            }
            
            $this->db = new PDO('sqlite:' . $this->dbPath);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            $this->createTables();
            $this->insertDefaultData();
            
        } catch (PDOException $e) {
            die("Erro na conexão com o banco: " . $e->getMessage());
        }
    }
    
    private function createTables() {
        // Tabela de departamentos
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS departamentos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome VARCHAR(100) UNIQUE NOT NULL,
                codigo VARCHAR(20) UNIQUE NOT NULL,
                descricao TEXT,
                ativo BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tabela de grupos de jornada
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS grupos_jornada (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome VARCHAR(100) UNIQUE NOT NULL,
                codigo VARCHAR(20) UNIQUE NOT NULL,
                entrada_manha TIME NOT NULL DEFAULT '08:00:00',
                saida_almoco TIME NOT NULL DEFAULT '12:00:00',
                volta_almoco TIME NOT NULL DEFAULT '13:00:00',
                saida_tarde TIME NOT NULL DEFAULT '18:00:00',
                carga_diaria_minutos INTEGER NOT NULL DEFAULT 480,
                tolerancia_minutos INTEGER NOT NULL DEFAULT 10,
                intervalo_almoco_minutos INTEGER NOT NULL DEFAULT 60,
                ativo BOOLEAN DEFAULT 1,
                versao INTEGER DEFAULT 1,
                data_vigencia DATE DEFAULT (DATE('now')),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // Tabela de usuários reformulada
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nome VARCHAR(100) NOT NULL,
                cpf VARCHAR(11) UNIQUE,
                matricula VARCHAR(20) UNIQUE,
                login VARCHAR(50) UNIQUE,
                senha VARCHAR(255),
                pin VARCHAR(255),
                tipo VARCHAR(20) DEFAULT 'funcionario' CHECK (tipo IN ('funcionario', 'admin')),
                cargo VARCHAR(100),
                departamento_id INTEGER,
                grupo_jornada_id INTEGER,
                pin_reset BOOLEAN DEFAULT 0,
                ativo BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (departamento_id) REFERENCES departamentos(id),
                FOREIGN KEY (grupo_jornada_id) REFERENCES grupos_jornada(id)
            )
        ");

        // Tabela de override de jornada
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS usuario_jornada_override (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                motivo TEXT NOT NULL,
                data_inicio DATE NOT NULL,
                data_fim DATE,
                entrada_manha TIME,
                saida_almoco TIME,
                volta_almoco TIME,
                saida_tarde TIME,
                carga_diaria_minutos INTEGER,
                tolerancia_minutos INTEGER,
                ativo BOOLEAN DEFAULT 1,
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
                FOREIGN KEY (created_by) REFERENCES usuarios(id)
            )
        ");
        
        // Tabela de pontos (substitui registros)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS pontos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                data DATE NOT NULL,
                hora TIME NOT NULL,
                tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('entrada_manha', 'saida_almoco', 'volta_almoco', 'saida_tarde')),
                latitude DECIMAL(10,8),
                longitude DECIMAL(11,8),
                ip_address VARCHAR(45),
                user_agent TEXT,
                observacao TEXT,
                editado BOOLEAN DEFAULT 0,
                editado_em DATETIME,
                editado_por INTEGER,
                motivo_ajuste VARCHAR(50),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
                FOREIGN KEY (editado_por) REFERENCES usuarios(id),
                UNIQUE(usuario_id, data, tipo)
            )
        ");

        // Tabela de audit logs
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER,
                acao VARCHAR(50) NOT NULL,
                tabela VARCHAR(50) NOT NULL,
                registro_id INTEGER,
                dados_antes TEXT,
                dados_depois TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            )
        ");

        // Tabela de tentativas de login (rate limiting)
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                usuario VARCHAR(50),
                tipo VARCHAR(20) NOT NULL CHECK (tipo IN ('admin', 'funcionario')),
                sucesso BOOLEAN NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Tabela de configurações da empresa
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS configuracoes_empresa (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                chave VARCHAR(100) UNIQUE NOT NULL,
                valor TEXT,
                descricao TEXT,
                tipo VARCHAR(20) DEFAULT 'text',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Migração: Adicionar campos de auditoria à tabela pontos se não existirem
        $this->migratePontosTable();
        
        // Índices para otimização
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_pontos_usuario_data ON pontos(usuario_id, data)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_audit_logs_usuario ON audit_logs(usuario_id)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_login_attempts_ip ON login_attempts(ip_address, created_at)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_cpf ON usuarios(cpf)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_usuarios_matricula ON usuarios(matricula)");
        $this->db->exec("CREATE INDEX IF NOT EXISTS idx_pontos_editado ON pontos(editado, editado_em)");
    }
    
    private function migratePontosTable() {
        try {
            // Verificar se os campos de auditoria já existem
            $stmt = $this->db->prepare("PRAGMA table_info(pontos)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $existingColumns = array_column($columns, 'name');
            
            // Adicionar campos que não existem
            if (!in_array('observacao', $existingColumns)) {
                $this->db->exec("ALTER TABLE pontos ADD COLUMN observacao TEXT");
            }
            
            if (!in_array('editado', $existingColumns)) {
                $this->db->exec("ALTER TABLE pontos ADD COLUMN editado BOOLEAN DEFAULT 0");
            }
            
            if (!in_array('editado_em', $existingColumns)) {
                $this->db->exec("ALTER TABLE pontos ADD COLUMN editado_em DATETIME");
            }
            
            if (!in_array('editado_por', $existingColumns)) {
                $this->db->exec("ALTER TABLE pontos ADD COLUMN editado_por INTEGER");
                $this->db->exec("CREATE INDEX IF NOT EXISTS idx_pontos_editado_por ON pontos(editado_por)");
            }
            
            if (!in_array('motivo_ajuste', $existingColumns)) {
                $this->db->exec("ALTER TABLE pontos ADD COLUMN motivo_ajuste VARCHAR(50)");
            }
            
            if (!in_array('tempo_ajustado_minutos', $existingColumns)) {
                $this->db->exec("ALTER TABLE pontos ADD COLUMN tempo_ajustado_minutos INTEGER DEFAULT 0");
            }
            
        } catch (PDOException $e) {
            // Ignorar erros de migração se os campos já existem
            error_log("Migração de pontos: " . $e->getMessage());
        }
    }
    
    private function insertDefaultData() {
        // Verificar se já existem dados
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuarios WHERE tipo = 'admin'");
        $stmt->execute();
        
        if ($stmt->fetchColumn() == 0) {
            // Inserir departamento padrão
            $this->db->exec("
                INSERT INTO departamentos (nome, codigo, descricao) 
                VALUES ('Administrativo', 'ADM', 'Departamento Administrativo Geral')
            ");
            
            // Inserir grupo de jornada padrão
            $this->db->exec("
                INSERT INTO grupos_jornada (nome, codigo, entrada_manha, saida_almoco, volta_almoco, saida_tarde, carga_diaria_minutos, tolerancia_minutos, intervalo_almoco_minutos) 
                VALUES ('Padrão 8h', 'PAD8H', '08:00:00', '12:00:00', '13:00:00', '18:00:00', 480, 10, 60)
            ");
            
            // Inserir administrador padrão
            $adminSenha = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (nome, login, senha, tipo, departamento_id, grupo_jornada_id) 
                VALUES ('Administrador Sistema', 'admin', ?, 'admin', 1, 1)
            ");
            $stmt->execute([$adminSenha]);
            
            // Inserir funcionário de exemplo
            $funcPin = password_hash('1234', PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (nome, cpf, matricula, pin, cargo, departamento_id, grupo_jornada_id, tipo) 
                VALUES ('João Silva Santos', '12345678901', '001', ?, 'Auxiliar Administrativo', 1, 1, 'funcionario')
            ");
            $stmt->execute([$funcPin]);
            
            // Inserir log de criação inicial
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (usuario_id, acao, tabela, dados_depois) 
                VALUES (1, 'CRIAR', 'usuarios', 'Sistema inicializado com dados padrão')
            ");
            $stmt->execute();
            
            // Inserir configurações padrão da empresa
            $configsEmpresa = [
                ['empresa_nome', 'Tech-Ponto Sistemas', 'Nome da empresa'],
                ['empresa_cnpj', '00.000.000/0001-00', 'CNPJ da empresa'],
                ['empresa_endereco', 'Rua da Inovação, 123 - Centro', 'Endereço completo da empresa'],
                ['empresa_cidade', 'São Paulo - SP', 'Cidade e estado da empresa'],
                ['empresa_telefone', '(11) 1234-5678', 'Telefone de contato da empresa'],
                ['empresa_email', 'contato@techponto.com', 'E-mail de contato da empresa'],
                ['empresa_logo', '', 'Caminho do logo da empresa (upload local)']
            ];
            
            $stmt = $this->db->prepare("
                INSERT OR IGNORE INTO configuracoes_empresa (chave, valor, descricao) 
                VALUES (?, ?, ?)
            ");
            
            foreach ($configsEmpresa as $config) {
                $stmt->execute($config);
            }
        }
    }
    
    public function getConnection() {
        return $this->db;
    }
}
?>