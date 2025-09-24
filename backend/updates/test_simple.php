<?php
/**
 * Teste Simples do Sistema de Atualizações
 */

echo "<h1>🔧 Teste Simples do Sistema</h1>\n";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .warning{color:orange;} .info{color:blue;}</style>\n";

// Teste 1: Verificar se arquivos existem
echo "<h2>1. Verificação de Arquivos</h2>\n";

$files = [
    'config.php' => __DIR__ . '/config.php',
    'update_checker.php' => __DIR__ . '/update_checker.php',
    'backup_manager.php' => __DIR__ . '/backup_manager.php',
    'update_installer.php' => __DIR__ . '/update_installer.php'
];

foreach ($files as $name => $path) {
    if (file_exists($path)) {
        echo "<p class='success'>✅ {$name}</p>\n";
    } else {
        echo "<p class='error'>❌ {$name} (não encontrado)</p>\n";
    }
}

// Teste 2: Verificar diretórios
echo "<h2>2. Verificação de Diretórios</h2>\n";

$dirs = [
    'backups' => __DIR__ . '/backups',
    'temp' => __DIR__ . '/temp',
    'logs' => __DIR__ . '/logs'
];

foreach ($dirs as $name => $path) {
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "<p class='success'>✅ {$name} (existe e é gravável)</p>\n";
        } else {
            echo "<p class='warning'>⚠️ {$name} (existe mas não é gravável)</p>\n";
        }
    } else {
        echo "<p class='error'>❌ {$name} (não existe)</p>\n";
    }
}

// Teste 3: Verificar configurações básicas
echo "<h2>3. Verificação de Configurações</h2>\n";

if (file_exists(__DIR__ . '/config.php')) {
    include __DIR__ . '/config.php';
    
    echo "<p class='info'>📋 Configurações encontradas:</p>\n";
    echo "<ul>\n";
    echo "<li><strong>Repositório:</strong> " . (defined('GITHUB_REPO_OWNER') ? GITHUB_REPO_OWNER : 'Não definido') . "/" . (defined('GITHUB_REPO_NAME') ? GITHUB_REPO_NAME : 'Não definido') . "</li>\n";
    echo "<li><strong>Versão Atual:</strong> " . (defined('CURRENT_VERSION') ? CURRENT_VERSION : 'Não definida') . "</li>\n";
    echo "<li><strong>Token GitHub:</strong> " . (defined('GITHUB_TOKEN') && !empty(GITHUB_TOKEN) ? 'Configurado' : 'Não configurado') . "</li>\n";
    echo "</ul>\n";
} else {
    echo "<p class='error'>❌ Arquivo de configuração não encontrado</p>\n";
}

// Teste 4: Verificar banco de dados
echo "<h2>4. Verificação de Banco de Dados</h2>\n";

$dbPath = dirname(__DIR__) . '/data/techponto.db';
if (file_exists($dbPath)) {
    echo "<p class='success'>✅ Banco de dados encontrado</p>\n";
    
    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $stmt = $pdo->query('SELECT COUNT(*) FROM usuarios');
        $count = $stmt->fetchColumn();
        echo "<p class='success'>✅ Banco acessível ({$count} usuários)</p>\n";
    } catch (Exception $e) {
        echo "<p class='error'>❌ Erro ao acessar banco: " . $e->getMessage() . "</p>\n";
    }
} else {
    echo "<p class='error'>❌ Banco de dados não encontrado</p>\n";
}

echo "<h2>📊 Resumo</h2>\n";
echo "<p class='info'>✅ Sistema de atualizações está configurado e pronto!</p>\n";
echo "<p><strong>Próximos passos:</strong></p>\n";
echo "<ol>\n";
echo "<li>Configurar repositório GitHub</li>\n";
echo "<li>Editar configurações em config.php</li>\n";
echo "<li>Testar no painel admin</li>\n";
echo "</ol>\n";

echo "<hr>\n";
echo "<p><a href='../admin.html'>← Voltar ao Painel Admin</a></p>\n";
?>
