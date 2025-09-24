/**
 * Sistema de Gerenciamento de Atualizações
 * Tech-Ponto - Sistema de Controle de Ponto Eletrônico
 */

class UpdateManager {
    constructor() {
        this.currentUpdateInfo = null;
        this.updateInProgress = false;
        this.checkInterval = null;
        
        // Inicializar quando a página carregar
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.init());
        } else {
            this.init();
        }
    }
    
    init() {
        console.log('UpdateManager inicializado');
        
        // Carregar status inicial do sistema
        this.atualizarStatusSistema();
        
        // Verificar atualizações automaticamente a cada 24 horas
        this.startAutoCheck();
    }
    
    /**
     * Atualiza o status do sistema
     */
    async atualizarStatusSistema() {
        try {
            const response = await fetch('/backend/updates/update_checker.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=system_info'
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.updateSystemStatusDisplay(result.data);
            } else {
                console.error('Erro ao obter status do sistema:', result.message);
            }
            
        } catch (error) {
            console.error('Erro na requisição:', error);
            this.showError('Erro ao conectar com o servidor');
        }
    }
    
    /**
     * Atualiza a exibição do status do sistema
     */
    updateSystemStatusDisplay(systemInfo) {
        const versaoAtual = document.getElementById('versao-atual');
        const ultimaVerificacao = document.getElementById('ultima-verificacao');
        const statusSistema = document.getElementById('status-sistema');
        
        if (versaoAtual) {
            versaoAtual.textContent = systemInfo.current_version || 'Desconhecida';
        }
        
        if (ultimaVerificacao) {
            ultimaVerificacao.textContent = systemInfo.last_check || 'Nunca';
        }
        
        if (statusSistema) {
            statusSistema.textContent = 'Atualizado';
            statusSistema.className = 'badge badge-success';
        }
    }
    
    /**
     * Verifica se há atualizações disponíveis
     */
    async verificarAtualizacoes(force = false) {
        if (this.updateInProgress) {
            this.showWarning('Uma operação de atualização já está em andamento');
            return;
        }
        
        const btnVerificar = document.getElementById('btn-verificar');
        if (btnVerificar) {
            btnVerificar.disabled = true;
            btnVerificar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verificando...';
        }
        
        try {
            const url = force ? 
                '/backend/updates/update_checker.php?force=true' : 
                '/backend/updates/update_checker.php';
                
            const response = await fetch(url);
            const result = await response.json();
            
            if (result.success) {
                this.handleUpdateCheckResult(result.data);
            } else {
                this.showError('Erro ao verificar atualizações: ' + result.message);
            }
            
        } catch (error) {
            console.error('Erro na verificação:', error);
            this.showError('Erro ao conectar com o servidor de atualizações');
        } finally {
            if (btnVerificar) {
                btnVerificar.disabled = false;
                btnVerificar.innerHTML = '<i class="fas fa-search"></i> Verificar Agora';
            }
        }
    }
    
    /**
     * Processa o resultado da verificação de atualizações
     */
    handleUpdateCheckResult(data) {
        const updateResult = document.getElementById('update-result');
        const updateDetails = document.getElementById('update-details');
        
        if (data.update_available) {
            this.currentUpdateInfo = data;
            this.showUpdateAvailable(data);
        } else {
            this.showNoUpdatesAvailable();
        }
        
        // Atualizar status do sistema
        this.updateSystemStatusDisplay({
            current_version: data.current_version,
            last_check: data.last_check
        });
    }
    
    /**
     * Exibe quando há atualização disponível
     */
    showUpdateAvailable(updateInfo) {
        const updateResult = document.getElementById('update-result');
        const updateDetails = document.getElementById('update-details');
        const updateInfoContent = document.getElementById('update-info-content');
        
        if (updateResult) {
            updateResult.innerHTML = `
                <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;">
                        <i class="fas fa-download"></i>
                        Nova versão disponível!
                    </h4>
                    <p style="margin: 0;">
                        <strong>Versão atual:</strong> ${updateInfo.current_version}<br>
                        <strong>Nova versão:</strong> ${updateInfo.latest_version}<br>
                        <strong>Data de lançamento:</strong> ${new Date(updateInfo.release_info.published_at).toLocaleDateString('pt-BR')}
                    </p>
                    <div style="margin-top: 15px;">
                        <button class="btn btn-primary" onclick="updateManager.instalarAtualizacao()">
                            <i class="fas fa-download"></i>
                            Instalar Atualização
                        </button>
                        <button class="btn btn-outline-secondary" onclick="updateManager.verDetalhesAtualizacao()">
                            <i class="fas fa-info-circle"></i>
                            Ver Detalhes
                        </button>
                    </div>
                </div>
            `;
            updateResult.style.display = 'block';
        }
        
        if (updateDetails && updateInfoContent) {
            updateInfoContent.innerHTML = `
                <div class="release-info">
                    <h5>Notas da Versão:</h5>
                    <div class="release-notes" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 4px; padding: 15px; white-space: pre-wrap; font-family: inherit;">
                        ${updateInfo.release_info.body || 'Nenhuma informação disponível sobre esta versão.'}
                    </div>
                    <div style="margin-top: 15px;">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Tamanho estimado: ${updateInfo.release_info.size || 'N/A'}
                        </small>
                    </div>
                </div>
            `;
            updateDetails.style.display = 'block';
        }
    }
    
    /**
     * Exibe quando não há atualizações disponíveis
     */
    showNoUpdatesAvailable() {
        const updateResult = document.getElementById('update-result');
        const updateDetails = document.getElementById('update-details');
        
        if (updateResult) {
            updateResult.innerHTML = `
                <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;">
                        <i class="fas fa-check-circle"></i>
                        Sistema atualizado!
                    </h4>
                    <p style="margin: 0;">Você está usando a versão mais recente do sistema.</p>
                </div>
            `;
            updateResult.style.display = 'block';
        }
        
        if (updateDetails) {
            updateDetails.style.display = 'none';
        }
    }
    
    /**
     * Inicia o processo de instalação de atualização
     */
    instalarAtualizacao() {
        if (!this.currentUpdateInfo) {
            this.showError('Nenhuma informação de atualização disponível');
            return;
        }
        
        this.abrirModalConfirmacaoAtualizacao();
    }
    
    /**
     * Abre modal de confirmação de atualização
     */
    abrirModalConfirmacaoAtualizacao() {
        const modal = document.getElementById('updateConfirmModal');
        const infoDiv = document.getElementById('update-confirm-info');
        
        if (infoDiv && this.currentUpdateInfo) {
            infoDiv.innerHTML = `
                <div class="update-info">
                    <h5>Informações da Atualização:</h5>
                    <div class="info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px;">
                        <div>
                            <strong>Versão Atual:</strong><br>
                            <span class="badge badge-secondary">${this.currentUpdateInfo.current_version}</span>
                        </div>
                        <div>
                            <strong>Nova Versão:</strong><br>
                            <span class="badge badge-primary">${this.currentUpdateInfo.latest_version}</span>
                        </div>
                        <div>
                            <strong>Data de Lançamento:</strong><br>
                            ${new Date(this.currentUpdateInfo.release_info.published_at).toLocaleDateString('pt-BR')}
                        </div>
                        <div>
                            <strong>Tamanho:</strong><br>
                            ${this.currentUpdateInfo.release_info.size || 'N/A'}
                        </div>
                    </div>
                </div>
            `;
        }
        
        if (modal) {
            modal.style.display = 'block';
        }
    }
    
    /**
     * Fecha modal de confirmação de atualização
     */
    fecharModalConfirmacaoAtualizacao() {
        const modal = document.getElementById('updateConfirmModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }
    
    /**
     * Confirma e inicia a instalação da atualização
     */
    async confirmarInstalacaoAtualizacao() {
        if (!this.currentUpdateInfo) {
            this.showError('Nenhuma informação de atualização disponível');
            return;
        }
        
        this.updateInProgress = true;
        this.fecharModalConfirmacaoAtualizacao();
        
        // Mostrar progresso
        this.showInstallationProgress();
        
        try {
            const response = await fetch('/backend/updates/update_installer.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'install_update',
                    download_url: this.currentUpdateInfo.release_info.download_url,
                    version: this.currentUpdateInfo.latest_version
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showInstallationSuccess(result.data);
            } else {
                this.showInstallationError(result.error);
            }
            
        } catch (error) {
            console.error('Erro na instalação:', error);
            this.showInstallationError('Erro ao conectar com o servidor durante a instalação');
        } finally {
            this.updateInProgress = false;
        }
    }
    
    /**
     * Exibe progresso da instalação
     */
    showInstallationProgress() {
        const updateResult = document.getElementById('update-result');
        
        if (updateResult) {
            updateResult.innerHTML = `
                <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 15px 0;">
                        <i class="fas fa-spinner fa-spin"></i>
                        Instalando Atualização...
                    </h4>
                    <p style="margin: 0 0 15px 0;">Por favor, aguarde. Não feche esta página durante a instalação.</p>
                    <div class="progress" style="height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             style="width: 100%; background: #007bff; height: 100%;"></div>
                    </div>
                    <div style="margin-top: 10px; font-size: 12px; color: #6c757d;">
                        <i class="fas fa-info-circle"></i>
                        Esta operação pode levar alguns minutos...
                    </div>
                </div>
            `;
            updateResult.style.display = 'block';
        }
    }
    
    /**
     * Exibe sucesso da instalação
     */
    showInstallationSuccess(data) {
        const updateResult = document.getElementById('update-result');
        
        if (updateResult) {
            updateResult.innerHTML = `
                <div class="alert alert-success" style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 15px 0;">
                        <i class="fas fa-check-circle"></i>
                        Atualização Instalada com Sucesso!
                    </h4>
                    <p style="margin: 0 0 15px 0;">
                        O sistema foi atualizado para a versão <strong>${data.version}</strong>.
                        Um backup foi criado automaticamente: <code>${data.backup_id}</code>
                    </p>
                    <div class="alert alert-warning" style="background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 10px; border-radius: 4px; margin: 10px 0;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Importante:</strong> O sistema será reiniciado em 10 segundos para aplicar as mudanças.
                    </div>
                    <div id="restart-countdown" style="font-weight: bold; color: #dc3545;">
                        Reiniciando em: <span id="countdown">10</span> segundos
                    </div>
                </div>
            `;
            updateResult.style.display = 'block';
        }
        
        // Iniciar countdown para reinicialização
        this.startRestartCountdown();
    }
    
    /**
     * Exibe erro na instalação
     */
    showInstallationError(error) {
        const updateResult = document.getElementById('update-result');
        
        if (updateResult) {
            updateResult.innerHTML = `
                <div class="alert alert-danger" style="background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 4px;">
                    <h4 style="margin: 0 0 15px 0;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Erro na Instalação
                    </h4>
                    <p style="margin: 0 0 15px 0;">
                        <strong>Erro:</strong> ${error}
                    </p>
                    <div class="alert alert-info" style="background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 10px; border-radius: 4px; margin: 10px 0;">
                        <i class="fas fa-info-circle"></i>
                        <strong>Rollback automático:</strong> O sistema foi restaurado para a versão anterior automaticamente.
                    </div>
                    <button class="btn btn-outline-primary" onclick="updateManager.verificarAtualizacoes(true)">
                        <i class="fas fa-refresh"></i>
                        Tentar Novamente
                    </button>
                </div>
            `;
            updateResult.style.display = 'block';
        }
    }
    
    /**
     * Inicia countdown para reinicialização
     */
    startRestartCountdown() {
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            
            if (countdown <= 0) {
                clearInterval(timer);
                window.location.reload();
            }
        }, 1000);
    }
    
    /**
     * Carrega logs de atualização
     */
    async carregarLogsAtualizacao() {
        const logsContent = document.getElementById('logs-content');
        
        if (logsContent) {
            logsContent.innerHTML = `
                <div class="loading" style="text-align: center; padding: 20px; color: #6c757d;">
                    <i class="fas fa-spinner fa-spin"></i>
                    Carregando logs...
                </div>
            `;
        }
        
        try {
            // Simular carregamento de logs (implementar endpoint real)
            setTimeout(() => {
                if (logsContent) {
                    logsContent.innerHTML = `
                        <div class="log-entry" style="margin-bottom: 10px; padding: 5px; border-left: 3px solid #007bff;">
                            <span style="color: #6c757d;">[2024-01-15 14:30:25]</span>
                            <span style="color: #28a745;">[SUCCESS]</span>
                            <span style="color: #333;">Sistema inicializado com sucesso</span>
                        </div>
                        <div class="log-entry" style="margin-bottom: 10px; padding: 5px; border-left: 3px solid #ffc107;">
                            <span style="color: #6c757d;">[2024-01-15 14:30:20]</span>
                            <span style="color: #ffc107;">[INFO]</span>
                            <span style="color: #333;">Verificação de atualizações executada</span>
                        </div>
                        <div class="log-entry" style="margin-bottom: 10px; padding: 5px; border-left: 3px solid #28a745;">
                            <span style="color: #6c757d;">[2024-01-15 14:30:15]</span>
                            <span style="color: #28a745;">[SUCCESS]</span>
                            <span style="color: #333;">Backup criado: backup_2024-01-15_14-30-15_abc12345</span>
                        </div>
                    `;
                }
            }, 1000);
            
        } catch (error) {
            console.error('Erro ao carregar logs:', error);
            if (logsContent) {
                logsContent.innerHTML = `
                    <div class="log-entry" style="color: #dc3545; text-align: center; padding: 20px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        Erro ao carregar logs de atualização
                    </div>
                `;
            }
        }
    }
    
    /**
     * Inicia verificação automática de atualizações
     */
    startAutoCheck() {
        // Verificar a cada 24 horas (86400000 ms)
        this.checkInterval = setInterval(() => {
            this.verificarAtualizacoes(false);
        }, 86400000);
    }
    
    /**
     * Para verificação automática de atualizações
     */
    stopAutoCheck() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }
    
    /**
     * Exibe mensagem de erro
     */
    showError(message) {
        console.error('UpdateManager Error:', message);
        // Implementar notificação visual
    }
    
    /**
     * Exibe mensagem de aviso
     */
    showWarning(message) {
        console.warn('UpdateManager Warning:', message);
        // Implementar notificação visual
    }
}

// Funções globais para compatibilidade com HTML
let updateManager;

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    updateManager = new UpdateManager();
});

// Funções globais para os botões HTML
function verificarAtualizacoes() {
    if (updateManager) {
        updateManager.verificarAtualizacoes(true);
    }
}

function atualizarStatusSistema() {
    if (updateManager) {
        updateManager.atualizarStatusSistema();
    }
}

function carregarLogsAtualizacao() {
    if (updateManager) {
        updateManager.carregarLogsAtualizacao();
    }
}

function abrirModalBackups() {
    const modal = document.getElementById('backupsModal');
    if (modal) {
        modal.style.display = 'block';
        carregarListaBackups();
    }
}

function fecharModalBackups() {
    const modal = document.getElementById('backupsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function fecharModalConfirmacaoAtualizacao() {
    if (updateManager) {
        updateManager.fecharModalConfirmacaoAtualizacao();
    }
}

function confirmarInstalacaoAtualizacao() {
    if (updateManager) {
        updateManager.confirmarInstalacaoAtualizacao();
    }
}

// Funções para gerenciar backups
async function carregarListaBackups() {
    const backupsList = document.getElementById('backups-list');
    
    if (backupsList) {
        backupsList.innerHTML = `
            <div class="loading" style="text-align: center; padding: 40px; color: #6c757d;">
                <i class="fas fa-spinner fa-spin"></i>
                Carregando backups...
            </div>
        `;
    }
    
    try {
        const response = await fetch('/backend/updates/backup_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=list_backups'
        });
        
        const result = await response.json();
        
        if (result.success && backupsList) {
            if (result.data.length === 0) {
                backupsList.innerHTML = `
                    <div class="no-backups" style="text-align: center; padding: 40px; color: #6c757d;">
                        <i class="fas fa-database" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
                        <p>Nenhum backup encontrado</p>
                    </div>
                `;
            } else {
                let html = '<div class="backups-grid" style="display: grid; gap: 15px;">';
                
                result.data.forEach(backup => {
                    html += `
                        <div class="backup-item" style="border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background: white;">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                                <div>
                                    <h5 style="margin: 0; color: #495057;">${backup.id}</h5>
                                    <small class="text-muted">${backup.created_at}</small>
                                </div>
                                <div class="backup-actions">
                                    <button class="btn btn-sm btn-outline-danger" onclick="deletarBackup('${backup.id}')" title="Deletar">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <p style="margin: 0; color: #6c757d; font-size: 14px;">${backup.description}</p>
                            <div style="margin-top: 10px; font-size: 12px; color: #6c757d;">
                                <i class="fas fa-hdd"></i> Tamanho: ${backup.size}
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                backupsList.innerHTML = html;
            }
        } else {
            throw new Error(result.message || 'Erro ao carregar backups');
        }
        
    } catch (error) {
        console.error('Erro ao carregar backups:', error);
        if (backupsList) {
            backupsList.innerHTML = `
                <div class="error" style="text-align: center; padding: 40px; color: #dc3545;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Erro ao carregar lista de backups</p>
                </div>
            `;
        }
    }
}

async function criarBackupManual() {
    try {
        const response = await fetch('/backend/updates/backup_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=create_backup&description=Backup manual criado pelo administrador'
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Backup criado com sucesso!');
            carregarListaBackups();
        } else {
            alert('Erro ao criar backup: ' + result.error);
        }
        
    } catch (error) {
        console.error('Erro ao criar backup:', error);
        alert('Erro ao conectar com o servidor');
    }
}

async function deletarBackup(backupId) {
    if (!confirm('Tem certeza que deseja deletar este backup? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch('/backend/updates/backup_manager.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete_backup&backup_id=${backupId}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Backup deletado com sucesso!');
            carregarListaBackups();
        } else {
            alert('Erro ao deletar backup: ' + result.error);
        }
        
    } catch (error) {
        console.error('Erro ao deletar backup:', error);
        alert('Erro ao conectar com o servidor');
    }
}
