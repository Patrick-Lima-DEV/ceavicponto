# 🔄 Sistema de Atualizações - Tech-Ponto

## 📋 Visão Geral

O sistema de atualizações do Tech-Ponto permite atualizar o sistema de forma segura e automática via GitHub, mantendo todos os dados e configurações preservados.

## 🛡️ Características de Segurança

- ✅ **Backup Automático**: Cria backup antes de cada atualização
- ✅ **Rollback Automático**: Reverte em caso de erro
- ✅ **Verificação de Integridade**: Valida arquivos antes da instalação
- ✅ **Preservação de Dados**: Mantém banco de dados e configurações
- ✅ **Logs Completos**: Registra todas as operações
- ✅ **Confirmação Admin**: Requer confirmação do administrador

## 🚀 Configuração Inicial

### 1. Configurar Repositório GitHub

```bash
# Criar repositório no GitHub
git init
git add .
git commit -m "Versão inicial 2.0.0"
git remote add origin https://github.com/SEU_USUARIO/ceavicponto.git
git push -u origin main
```

### 2. Configurar Sistema de Atualizações

Edite o arquivo `backend/updates/config.php`:

```php
// Configurações do GitHub
define('GITHUB_REPO_OWNER', 'seuusuario'); // Seu usuário GitHub
define('GITHUB_REPO_NAME', 'ceavicponto'); // Nome do repositório
define('GITHUB_TOKEN', ''); // Token para repositórios privados (opcional)

// Configurações do Sistema
define('CURRENT_VERSION', '2.0.0'); // Versão atual
```

### 3. Criar Token GitHub (Opcional - para repositórios privados)

1. Acesse: GitHub → Settings → Developer settings → Personal access tokens
2. Clique em "Generate new token"
3. Selecione escopo `repo` (para repositórios privados)
4. Copie o token e cole em `GITHUB_TOKEN`

## 📦 Como Criar Atualizações

### 1. Preparar Nova Versão

```bash
# Fazer alterações no código
# Testar localmente
# Commit das mudanças
git add .
git commit -m "Nova funcionalidade X"
```

### 2. Criar Release no GitHub

```bash
# Criar tag para nova versão
git tag v2.1.0
git push origin v2.1.0
```

### 3. Criar Release no GitHub

1. Acesse o repositório no GitHub
2. Clique em "Releases" → "Create a new release"
3. Selecione a tag `v2.1.0`
4. Adicione título: "Tech-Ponto v2.1.0"
5. Adicione descrição das mudanças
6. Clique em "Publish release"

## 🔧 Como Usar o Sistema

### 1. Acessar Painel de Atualizações

1. Faça login como administrador
2. Acesse a aba "Atualizações"
3. Visualize o status atual do sistema

### 2. Verificar Atualizações

1. Clique em "Verificar Agora"
2. O sistema verifica o GitHub automaticamente
3. Se houver atualização, será exibida

### 3. Instalar Atualização

1. Clique em "Instalar Atualização"
2. Confirme a operação no modal
3. Aguarde o processo automático:
   - Backup automático
   - Download da atualização
   - Instalação
   - Migração do banco
   - Verificação de integridade

### 4. Gerenciar Backups

1. Clique em "Ver Backups"
2. Visualize todos os backups disponíveis
3. Crie backups manuais se necessário
4. Delete backups antigos para economizar espaço

## 📁 Estrutura de Arquivos

```
backend/updates/
├── config.php              # Configurações do sistema
├── update_checker.php      # Verificação de atualizações
├── backup_manager.php      # Gerenciamento de backups
├── update_installer.php    # Instalação de atualizações
├── backups/                # Backups automáticos
├── temp/                   # Arquivos temporários
└── logs/                   # Logs do sistema
```

## 🔍 Monitoramento e Logs

### Logs de Atualização

- **Localização**: `backend/updates/logs/`
- **Formato**: `update_YYYY-MM-DD.log`
- **Conteúdo**: Todas as operações de atualização

### Logs de Backup

- **Localização**: `backend/updates/backups/`
- **Formato**: `backup_YYYY-MM-DD_HH-MM-SS_XXXXXXXX/`
- **Conteúdo**: Backup completo do sistema

## ⚠️ Arquivos Críticos Preservados

O sistema preserva automaticamente:

- `backend/data/techponto.db` (banco de dados)
- `backend/config/config.php` (configurações)
- `backend/config/database.php` (configuração do banco)
- `backend/config/security.php` (configurações de segurança)
- `backend/updates/config.php` (configurações de atualização)
- `logo/` (logos da empresa)

## 🚨 Solução de Problemas

### Erro: "Falha ao conectar com GitHub"

**Causa**: Problema de conectividade ou repositório privado sem token

**Solução**:
1. Verificar conexão com internet
2. Configurar token GitHub se repositório for privado
3. Verificar se repositório existe e é acessível

### Erro: "Backup corrompido"

**Causa**: Falha na criação do backup

**Solução**:
1. Verificar permissões de escrita em `backend/updates/backups/`
2. Verificar espaço em disco disponível
3. Executar como administrador se necessário

### Erro: "Falha na instalação"

**Causa**: Problema durante a instalação

**Solução**:
1. O sistema faz rollback automático
2. Verificar logs em `backend/updates/logs/`
3. Tentar novamente após verificar conectividade

### Sistema não inicia após atualização

**Causa**: Problema na migração ou arquivos corrompidos

**Solução**:
1. Restaurar backup manualmente
2. Verificar logs de erro
3. Contatar suporte se necessário

## 🔒 Segurança

### Medidas Implementadas

- **Validação de Integridade**: Verifica arquivos antes da instalação
- **Backup Automático**: Sempre cria backup antes de atualizar
- **Rollback Automático**: Reverte em caso de erro
- **Logs Completos**: Registra todas as operações
- **Confirmação Admin**: Requer confirmação do administrador

### Recomendações

1. **Teste em Ambiente de Desenvolvimento**: Sempre teste atualizações antes de aplicar em produção
2. **Backup Manual**: Crie backup manual antes de atualizações importantes
3. **Monitoramento**: Verifique logs regularmente
4. **Token Seguro**: Mantenha token GitHub seguro e não compartilhado

## 📊 API Endpoints

### Verificar Atualizações
```
GET /backend/updates/update_checker.php
GET /backend/updates/update_checker.php?force=true
```

### Instalar Atualização
```
POST /backend/updates/update_installer.php
Content-Type: application/x-www-form-urlencoded

action=install_update&download_url=URL&version=VERSION
```

### Gerenciar Backups
```
POST /backend/updates/backup_manager.php
Content-Type: application/x-www-form-urlencoded

action=create_backup|list_backups|restore_backup|delete_backup
```

## 🎯 Próximos Passos

1. **Configurar repositório GitHub**
2. **Testar sistema localmente**
3. **Criar primeira atualização**
4. **Configurar verificação automática**
5. **Monitorar logs regularmente**

## 📞 Suporte

Em caso de problemas:

1. Verificar logs em `backend/updates/logs/`
2. Verificar permissões de arquivos
3. Testar conectividade com GitHub
4. Restaurar backup se necessário

---

**Sistema de Atualizações Tech-Ponto v2.0**  
*Desenvolvido com segurança e confiabilidade em mente* 🛡️
