<?php
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$pdo = $database->getConnection();

echo "🏗️ Criando tabelas de justificativas manualmente...\n\n";

// 1. Criar tabela tipos_justificativa
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tipos_justificativa (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            codigo VARCHAR(10) NOT NULL UNIQUE,
            nome VARCHAR(100) NOT NULL,
            descricao TEXT,
            abate_falta BOOLEAN DEFAULT 1,
            bloqueia_ponto BOOLEAN DEFAULT 0,
            ativo BOOLEAN DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✅ Tabela tipos_justificativa criada\n";
} catch (Exception $e) {
    echo "❌ Erro ao criar tipos_justificativa: " . $e->getMessage() . "\n";
}

// 2. Inserir tipos padrão
try {
    $pdo->exec("
        INSERT OR IGNORE INTO tipos_justificativa (codigo, nome, descricao, abate_falta, bloqueia_ponto) VALUES
        ('FER', 'Férias', 'Período de férias do funcionário', 1, 1),
        ('ATM', 'Atestado Médico', 'Atestado médico com afastamento', 1, 0),
        ('AJP', 'Ausência Justificada Parcial', 'Ausência justificada em período parcial (manhã/tarde)', 1, 0),
        ('LIC', 'Licença CLT', 'Licenças previstas na CLT (falecimento, casamento, paternidade, etc.)', 1, 0),
        ('FOL', 'Folga Autorizada', 'Folga autorizada pelo gestor', 1, 0)
    ");
    echo "✅ Tipos de justificativa inseridos\n";
} catch (Exception $e) {
    echo "❌ Erro ao inserir tipos: " . $e->getMessage() . "\n";
}

// 3. Criar tabela justificativas
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS justificativas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            funcionario_id INTEGER NOT NULL,
            tipo_justificativa_id INTEGER NOT NULL,
            data_inicio DATE NOT NULL,
            data_fim DATE,
            periodo_parcial VARCHAR(20) DEFAULT 'integral' CHECK (periodo_parcial IN ('manha', 'tarde', 'integral')),
            motivo TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'ativa' CHECK (status IN ('ativa', 'cancelada', 'expirada')),
            criado_por INTEGER NOT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            atualizado_por INTEGER,
            atualizado_em DATETIME,
            FOREIGN KEY (funcionario_id) REFERENCES usuarios(id),
            FOREIGN KEY (tipo_justificativa_id) REFERENCES tipos_justificativa(id),
            FOREIGN KEY (criado_por) REFERENCES usuarios(id),
            FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
        )
    ");
    echo "✅ Tabela justificativas criada\n";
} catch (Exception $e) {
    echo "❌ Erro ao criar justificativas: " . $e->getMessage() . "\n";
}

// 4. Criar tabela justificativas_log
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS justificativas_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            justificativa_id INTEGER NOT NULL,
            acao VARCHAR(20) NOT NULL CHECK (acao IN ('criada', 'editada', 'cancelada', 'excluida')),
            dados_anteriores TEXT,
            dados_novos TEXT,
            usuario_id INTEGER NOT NULL,
            data_acao DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            FOREIGN KEY (justificativa_id) REFERENCES justificativas(id),
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        )
    ");
    echo "✅ Tabela justificativas_log criada\n";
} catch (Exception $e) {
    echo "❌ Erro ao criar justificativas_log: " . $e->getMessage() . "\n";
}

// 5. Criar índices
$indexes = [
    'idx_justificativas_funcionario' => 'CREATE INDEX IF NOT EXISTS idx_justificativas_funcionario ON justificativas(funcionario_id)',
    'idx_justificativas_data' => 'CREATE INDEX IF NOT EXISTS idx_justificativas_data ON justificativas(data_inicio, data_fim)',
    'idx_justificativas_status' => 'CREATE INDEX IF NOT EXISTS idx_justificativas_status ON justificativas(status)',
    'idx_justificativas_log_justificativa' => 'CREATE INDEX IF NOT EXISTS idx_justificativas_log_justificativa ON justificativas_log(justificativa_id)',
    'idx_justificativas_log_data' => 'CREATE INDEX IF NOT EXISTS idx_justificativas_log_data ON justificativas_log(data_acao)'
];

foreach ($indexes as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ Índice $name criado\n";
    } catch (Exception $e) {
        echo "❌ Erro ao criar índice $name: " . $e->getMessage() . "\n";
    }
}

echo "\n🎉 Migração de justificativas concluída!\n";
?>
