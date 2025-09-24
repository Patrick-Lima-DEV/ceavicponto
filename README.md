# 🕒 Tech-Ponto - Sistema de Controle de Ponto Eletrônico

Sistema completo de controle de ponto eletrônico desenvolvido para pequenas e médias empresas, com interface moderna, funcionalidades avançadas e **sistema de atualizações automáticas via GitHub**.

## 📋 Características Principais

- ✅ **Interface Moderna**: Design responsivo e intuitivo
- ✅ **Autenticação Segura**: Login admin tradicional + PIN para funcionários
- ✅ **Cálculos em PHP**: Lógica de apuração unificada no backend
- ✅ **Banco SQLite**: Sistema local, sem dependência de internet
- ✅ **Auditoria Completa**: Log de todas as ações administrativas
- ✅ **Rate Limiting**: Proteção contra ataques de força bruta
- ✅ **Validações Robustas**: Sanitização e validação de todos os inputs
- ✅ **Prepared Statements**: Proteção contra SQL Injection
- ✅ **Gestão de Registros**: Edição e inserção manual de pontos
- ✅ **Relatórios Avançados**: Visualização e filtros por funcionário/período
- ✅ **Cartão de Ponto**: Relatório profissional em HTML/PDF
- ✅ **Configurações da Empresa**: Personalização completa
- ✅ **🔄 Sistema de Atualizações**: Atualizações automáticas via GitHub
- ✅ **🛡️ Sistema Inteligente**: Verificação de sessão baseada em atividade

## 🚀 Instalação Rápida

### Pré-requisitos
- PHP 8.2+ (com extensões: PDO, SQLite3, JSON)
- Navegador web moderno
- Windows/Linux/macOS
- Git (para sistema de atualizações)

### Instalação
1. **Clone/Baixe** o projeto
2. **Navegue** até a pasta do projeto
3. **Execute** o servidor PHP:
   ```bash
   php -S localhost:8000 -t .
   ```
4. **Acesse** no navegador: `http://localhost:8000`

### Primeiro Acesso
- **Login Admin**: `admin` / `admin123`
- **Altere** a senha padrão imediatamente
- **Configure** os dados da empresa em "Configurações"

## 🏗️ Arquitetura do Sistema

### **Estrutura Unificada (PHP Puro)**
```
┌─────────────────────────────────────┐
│           FRONTEND                  │
│  HTML + CSS + JavaScript           │
│  (Interface + Validações)          │
├─────────────────────────────────────┤
│           BACKEND                   │
│  PHP 8.2 + SQLite                  │
│  (API + Dados + Cálculos + PDF)    │
├─────────────────────────────────────┤
│           DEPLOY                    │
│  php -S localhost:8000             │
│  (1 comando, 1 servidor)           │
└─────────────────────────────────────┘
```

### **Benefícios da Unificação:**
- **6x menos uso de RAM** (100MB → 15MB)
- **Manutenção 10x mais simples**
- **Deploy instantâneo** (1 comando)
- **Zero complexidade** desnecessária
- **Performance perfeita** para 3-4 usuários

## 📁 Estrutura do Projeto

```
ceavicponto/
├── 📁 frontend/                    # Interface do usuário
│   ├── 📄 *.html                   # Páginas web
│   ├── 📁 css/                     # Estilos
│   └── 📁 js/                      # JavaScript
├── 📁 backend/                     # Servidor PHP
│   ├── 📁 api/                     # APIs PHP
│   ├── 📁 config/                  # Configurações
│   ├── 📁 data/                    # Banco SQLite
│   ├── 📁 updates/                 # Sistema de atualizações
│   └── 📁 migrations/              # Migrações
└── 📄 *.bat                        # Scripts Windows
```

## 🔧 Funcionalidades Detalhadas

### **1. Controle de Ponto**
- **Registro automático** por PIN
- **Edição manual** de registros
- **Validação de horários** (entrada, almoço, volta, saída)
- **Cálculo automático** de horas trabalhadas
- **Detecção de faltas/extras**

### **2. Gerenciamento de Usuários**
- **Criação/edição** de funcionários
- **Atribuição de departamentos**
- **Configuração de grupos de jornada**
- **Controle de status** (ativo/inativo)
- **Reset de senhas/PINs**

### **3. Relatórios**
- **Relatório mensal** completo
- **Filtros por funcionário/período**
- **Exportação** em HTML/PDF
- **Cartão de Ponto** profissional
- **Totais e estatísticas**

### **4. Configurações**
- **Dados da empresa** (nome, CNPJ, endereço)
- **Logo personalizado**
- **Informações de contato**
- **Preview em tempo real**

### **5. Segurança**
- **Autenticação** robusta
- **Rate limiting** (3 tentativas/minuto)
- **Auditoria** completa
- **Validação** de inputs
- **Prepared statements**
- **Sistema inteligente** de verificação de sessão

## 🔄 Sistema de Atualizações Automáticas

### **Características de Segurança**
- ✅ **Backup Automático**: Cria backup antes de cada atualização
- ✅ **Rollback Automático**: Reverte em caso de erro
- ✅ **Verificação de Integridade**: Valida arquivos antes da instalação
- ✅ **Preservação de Dados**: Mantém banco de dados e configurações
- ✅ **Logs Completos**: Registra todas as operações
- ✅ **Confirmação Admin**: Requer confirmação do administrador

### **Como Funciona**
1. **Modificar código** e alterar versão em `backend/updates/config.php`
2. **Commit e push** para GitHub
3. **Criar release** no GitHub com tag (ex: `v2.0.1`)
4. **Sistema detecta** automaticamente a nova versão
5. **Admin confirma** instalação no painel
6. **Backup automático** + instalação + verificação

### **Configuração Inicial**
```php
// backend/updates/config.php
define('GITHUB_REPO_OWNER', 'seuusuario'); // Seu usuário GitHub
define('GITHUB_REPO_NAME', 'ceavicponto'); // Nome do repositório
define('CURRENT_VERSION', '2.0.1'); // Versão atual
```

### **Como Usar**
1. Acesse o painel admin → Aba "Atualizações"
2. Clique em "Verificar Agora"
3. Se houver atualização, clique em "Instalar"
4. Confirme a instalação

## 🧠 Sistema Inteligente de Sessão

### **Características**
- ✅ **Verificação por Atividade**: Só verifica quando há interação do usuário
- ✅ **Modal com Countdown**: Aviso antes do redirecionamento
- ✅ **Avisos Discretos**: Notificação 10 minutos antes da expiração
- ✅ **Renovação de Sessão**: Botão para renovar sem logout
- ✅ **Página Funcionário Especial**: Permanece ativa mesmo com sessão expirada
- ✅ **Logs de Auditoria**: Registra todas as verificações

### **Benefícios**
- **Não perde trabalho**: Usuário não é redirecionado inesperadamente
- **Mais seguro**: Timeout de 4 horas em vez de 8
- **Melhor UX**: Avisos elegantes e não intrusivos
- **Controle manual**: Botão para verificar sessão quando necessário

## 🎯 Cálculos de Ponto

### **Lógica Unificada em PHP:**
```php
// Cálculo de horas trabalhadas (com almoço)
function calcularHorasTrabalhadas($entrada, $saidaAlmoco, $voltaAlmoco, $saida)

// Cálculo de saldo diário (faltas/extras)
function calcularSaldoDiario($horasTrabalhadas, $cargaDiaria = '08:00')

// Processamento completo de dados
function gerarRelatorioCompleto($usuario, $pontos, $dataInicio, $dataFim)
```

### **Características:**
- **Carga diária padrão**: 8 horas
- **Tolerância**: 10 minutos
- **Cálculo de almoço**: Automático
- **Detecção de faltas**: Baseada na carga diária
- **Horas extras**: Acima da carga + tolerância

## 📊 Relatórios Disponíveis

### **1. Relatório Mensal**
- **Visualização** por funcionário
- **Filtros** por período
- **Totais** de horas trabalhadas
- **Detecção** de faltas/extras
- **Exportação** em HTML

### **2. Cartão de Ponto**
- **Formato profissional** tradicional
- **Dados da empresa** configuráveis
- **Informações do funcionário**
- **Registros diários** detalhados
- **Totais mensais** precisos
- **Assinaturas** para validação

## 🔐 Segurança e Auditoria

### **Medidas de Segurança:**
- **Rate limiting**: 3 tentativas/minuto por IP
- **Validação de inputs**: Sanitização completa
- **Prepared statements**: Proteção SQL Injection
- **Sessões seguras**: Controle de acesso
- **Logs de auditoria**: Todas as ações registradas
- **Sistema inteligente**: Verificação baseada em atividade

### **Logs de Auditoria:**
- **Login/logout** de usuários
- **Criação/edição** de registros
- **Alterações** de configurações
- **Ações administrativas**
- **Tentativas de acesso** não autorizado
- **Verificações de sessão** inteligentes

## 🚀 Comandos de Uso

### **Iniciar Sistema:**
```bash
php -S localhost:8000 -t .
```

### **Backup do Banco:**
```bash
copy backend\data\techponto.db backup_$(date).db
```

### **Restore do Banco:**
```bash
copy backup_20250917.db backend\data\techponto.db
```

### **Verificar Sintaxe PHP:**
```bash
php -l backend\api\admin.php
```

### **Sistema de Atualizações:**
```bash
# Configurar repositório
git remote add origin https://github.com/seuusuario/ceavicponto.git

# Criar nova versão
git add .
git commit -m "Nova funcionalidade v2.0.1"
git push origin master

# Criar release no GitHub (via interface web)
```

## 📈 Métricas de Performance

### **Sistema Unificado (PHP Puro):**
- **RAM**: ~15MB (vs 100MB híbrido)
- **CPU**: Mínimo
- **Deploy**: 1 comando
- **Manutenção**: 1 tecnologia
- **Complexidade**: Baixa

### **Capacidade:**
- **Usuários simultâneos**: 3-4 (ideal para pequenas empresas)
- **Funcionários**: Até 10
- **Registros**: Ilimitados (SQLite)
- **Performance**: Excelente para uso local

## 🛠️ Manutenção

### **Logs do Sistema:**
- **Auditoria**: `backend/logs/audit.log`
- **Erros**: `backend/logs/error.log`
- **Atualizações**: `backend/updates/logs/`
- **Acesso**: Logs do servidor PHP

### **Backup Recomendado:**
- **Banco de dados**: `backend/data/techponto.db`
- **Configurações**: `backend/config/`
- **Logs**: `backend/logs/`
- **Backups automáticos**: `backend/updates/backups/`

### **Atualizações:**
- **Automáticas**: Via sistema de atualizações
- **Manuais**: Substituir arquivos
- **Banco**: Executar migrações
- **Configurações**: Manter backups

## 🆘 Solução de Problemas

### **Erro 403 (Proibido):**
- Verificar se está logado
- Confirmar permissões de admin
- Verificar sessão ativa

### **Erro 500 (Servidor):**
- Verificar logs de erro
- Confirmar sintaxe PHP: `php -l arquivo.php`
- Verificar permissões de arquivo

### **Banco não encontrado:**
- Verificar se `backend/data/techponto.db` existe
- Executar migrações se necessário
- Restaurar backup se disponível

### **Servidor não inicia:**
- Verificar se porta 8000 está livre
- Confirmar instalação do PHP
- Verificar permissões de diretório

### **Sistema de Atualizações:**
- Verificar conectividade com GitHub
- Confirmar configuração do repositório
- Verificar logs em `backend/updates/logs/`
- Restaurar backup se necessário

## 📞 Suporte

### **Em caso de problemas:**
1. **Verificar logs**: `backend/logs/` e `backend/updates/logs/`
2. **Testar sintaxe**: `php -l arquivo.php`
3. **Reiniciar servidor**: Parar e iniciar novamente
4. **Restaurar backup**: Se disponível
5. **Verificar sistema de atualizações**: Logs e configurações

### **Informações do Sistema:**
- **Versão PHP**: 8.2+
- **Banco**: SQLite 3
- **Arquitetura**: PHP Puro (unificada)
- **Sistema de Atualizações**: GitHub
- **Última atualização**: 24/09/2025

## 🎉 Histórico de Versões

### **v2.0.1 - Sistema de Atualizações (24/09/2025)**
- ✅ **Sistema de atualizações** via GitHub
- ✅ **Backup automático** antes de atualizações
- ✅ **Rollback automático** em caso de erro
- ✅ **Verificação de integridade** de arquivos
- ✅ **Logs completos** de operações
- ✅ **Interface admin** para gerenciar atualizações

### **v2.0 - Unificação PHP (17/09/2025)**
- ✅ **Unificação completa** em PHP
- ✅ **Remoção** de código Python
- ✅ **Simplificação** da arquitetura
- ✅ **Otimização** de recursos
- ✅ **Performance** melhorada
- ✅ **Sistema inteligente** de sessão

### **v1.0 - Sistema Híbrido**
- ✅ **Controle de ponto** básico
- ✅ **Interface** moderna
- ✅ **Autenticação** segura
- ✅ **Relatórios** funcionais

---

## 🏆 Conclusão

**Tech-Ponto v2.0.1** é um sistema completo, moderno e eficiente para controle de ponto eletrônico, ideal para pequenas e médias empresas que precisam de uma solução local, segura e fácil de manter.

**Características principais:**
- 🚀 **Performance otimizada** (6x mais leve)
- 🔧 **Manutenção simplificada** (1 tecnologia)
- 🛡️ **Segurança robusta** (auditoria completa)
- 📊 **Relatórios profissionais** (cartão de ponto)
- ⚡ **Deploy instantâneo** (1 comando)
- 🔄 **Atualizações automáticas** (via GitHub)
- 🧠 **Sistema inteligente** (verificação de sessão)

**Sistema pronto para produção!** ✅

---

*Desenvolvido com ❤️ para empresas que valorizam simplicidade e eficiência.*