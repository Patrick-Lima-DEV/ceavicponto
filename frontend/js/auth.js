// Configuração da API
const API_BASE = '../backend/api';

// Funções de Autenticação
function preencherDemo(login, senha) {
    document.getElementById('login').value = login;
    document.getElementById('senha').value = senha;
}

function mostrarNotificacao(mensagem, tipo = 'success') {
    // Remove notificações existentes
    const existentes = document.querySelectorAll('.notification');
    existentes.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification ${tipo}`;
    notification.innerHTML = `
        <i class="fas fa-${tipo === 'success' ? 'check' : tipo === 'error' ? 'times' : 'exclamation-triangle'}"></i>
        ${mensagem}
    `;
    
    document.body.appendChild(notification);
    
    // Mostrar com animação
    setTimeout(() => notification.classList.add('show'), 100);
    
    // Remover após 4 segundos
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 4000);
}

// Modal de confirmação para expiração de sessão
function mostrarModalExpiracaoSessao(mensagem) {
    // Remove modais existentes
    const existentes = document.querySelectorAll('.modal-expiracao');
    existentes.forEach(m => m.remove());
    
    const modal = document.createElement('div');
    modal.className = 'modal-expiracao';
    modal.innerHTML = `
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Sessão Expirada</h3>
            </div>
            <div class="modal-body">
                <p>${mensagem}</p>
                <p>Você será redirecionado para a tela de login em <span id="countdown">10</span> segundos.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" onclick="window.location.href='login.html'">
                    <i class="fas fa-sign-in-alt"></i> Ir para Login
                </button>
                <button class="btn btn-secondary" onclick="this.closest('.modal-expiracao').remove()">
                    <i class="fas fa-times"></i> Fechar
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Countdown automático
    let countdown = 10;
    const countdownElement = modal.querySelector('#countdown');
    const countdownInterval = setInterval(() => {
        countdown--;
        countdownElement.textContent = countdown;
        if (countdown <= 0) {
            clearInterval(countdownInterval);
            window.location.href = 'login.html';
        }
    }, 1000);
    
    // Adicionar estilos CSS se não existirem
    if (!document.querySelector('#modal-expiracao-styles')) {
        const styles = document.createElement('style');
        styles.id = 'modal-expiracao-styles';
        styles.textContent = `
            .modal-expiracao {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                animation: fadeIn 0.3s ease;
            }
            
            .modal-expiracao .modal-content {
                background: white;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 90%;
                animation: slideIn 0.3s ease;
            }
            
            .modal-expiracao .modal-header {
                padding: 20px 20px 0;
                border-bottom: 1px solid #eee;
            }
            
            .modal-expiracao .modal-header h3 {
                margin: 0;
                color: #e74c3c;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .modal-expiracao .modal-body {
                padding: 20px;
            }
            
            .modal-expiracao .modal-body p {
                margin: 0 0 10px 0;
                line-height: 1.5;
            }
            
            .modal-expiracao .modal-footer {
                padding: 0 20px 20px;
                display: flex;
                gap: 10px;
                justify-content: flex-end;
            }
            
            .modal-expiracao .btn {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 5px;
            }
            
            .modal-expiracao .btn-primary {
                background: #3498db;
                color: white;
            }
            
            .modal-expiracao .btn-secondary {
                background: #95a5a6;
                color: white;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes slideIn {
                from { transform: translateY(-50px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
}

// Aviso de expiração de sessão
function mostrarAvisoExpiracaoSessao(tempoRestante) {
    const minutos = Math.ceil(tempoRestante / (60 * 1000));
    
    // Remove avisos existentes
    const existentes = document.querySelectorAll('.aviso-expiracao');
    existentes.forEach(a => a.remove());
    
    const aviso = document.createElement('div');
    aviso.className = 'aviso-expiracao';
    aviso.innerHTML = `
        <div class="aviso-content">
            <i class="fas fa-clock"></i>
            <span>Sua sessão expira em ${minutos} minutos. <a href="#" onclick="renovarSessao()">Renovar sessão</a></span>
            <button onclick="this.closest('.aviso-expiracao').remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(aviso);
    
    // Adicionar estilos CSS se não existirem
    if (!document.querySelector('#aviso-expiracao-styles')) {
        const styles = document.createElement('style');
        styles.id = 'aviso-expiracao-styles';
        styles.textContent = `
            .aviso-expiracao {
                position: fixed;
                top: 20px;
                right: 20px;
                background: #f39c12;
                color: white;
                padding: 15px 20px;
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
                z-index: 9999;
                animation: slideInRight 0.3s ease;
                max-width: 350px;
            }
            
            .aviso-expiracao .aviso-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .aviso-expiracao a {
                color: white;
                text-decoration: underline;
                font-weight: bold;
            }
            
            .aviso-expiracao button {
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                margin-left: auto;
                padding: 5px;
            }
            
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styles);
    }
    
    // Auto-remover após 30 segundos
    setTimeout(() => {
        if (aviso.parentNode) {
            aviso.remove();
        }
    }, 30000);
}

// Função para renovar sessão
async function renovarSessao() {
    try {
        // Para funcionários, usar endpoint específico se disponível
        let endpoint = 'auth.php?action=status';
        
        // Verificar se é funcionário e usar endpoint específico
        const user = localStorage.getItem('user');
        if (user) {
            try {
                const userData = JSON.parse(user);
                if (userData.tipo === 'funcionario') {
                    // Para funcionários, apenas atualizar o tempo local
                    // pois eles não têm sessão no servidor como admin
                    localStorage.setItem('login_time', Date.now());
                    avisoExpiracaoMostrado = false;
                    
                    // Remover avisos
                    document.querySelectorAll('.aviso-expiracao').forEach(a => a.remove());
                    
                    mostrarNotificacao('Sessão renovada com sucesso!', 'success');
                    return;
                }
            } catch (error) {
                console.log('Erro ao parsear dados do usuário:', error);
            }
        }
        
        // Para admins, verificar no servidor
        const response = await fazerRequisicao(endpoint);
        if (response.success) {
            // Atualizar tempo de login
            localStorage.setItem('login_time', Date.now());
            avisoExpiracaoMostrado = false;
            
            // Remover avisos
            document.querySelectorAll('.aviso-expiracao').forEach(a => a.remove());
            
            mostrarNotificacao('Sessão renovada com sucesso!', 'success');
        } else {
            mostrarNotificacao('Erro ao renovar sessão. Faça login novamente.', 'error');
        }
    } catch (error) {
        console.error('Erro ao renovar sessão:', error);
        mostrarNotificacao('Erro ao renovar sessão. Verifique sua conexão.', 'error');
    }
}

function mostrarLoading(elemento, ativo = true) {
    const btn = typeof elemento === 'string' ? document.querySelector(elemento) : elemento;
    
    if (ativo) {
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.dataset.originalText = originalText;
        btn.innerHTML = `
            <div class="loading">
                <div class="spinner"></div>
                Processando...
            </div>
        `;
    } else {
        btn.disabled = false;
        btn.innerHTML = btn.dataset.originalText;
        delete btn.dataset.originalText;
    }
}

async function fazerRequisicao(url, options = {}) {
    try {
        const response = await fetch(`${API_BASE}/${url}`, {
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...options.headers
            },
            ...options
        });
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            
            if (!response.ok) {
                // Se é erro de autenticação, usar modal de confirmação
                if (response.status === 403 || response.status === 401) {
                    localStorage.removeItem('user');
                    localStorage.removeItem('csrf_token');
                    localStorage.removeItem('login_time');
                    if (!window.location.pathname.includes('login.html')) {
                        mostrarModalExpiracaoSessao('Sessão expirada. Faça login novamente.');
                        return;
                    }
                }
                throw new Error(data.message || 'Erro na requisição');
            }
            
            return data;
        } else {
            // Se não for JSON, pode ser HTML de erro
            const text = await response.text();
            console.error('Resposta não é JSON:', text);
            throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
        }
        
    } catch (error) {
        console.error('Erro na requisição:', error);
        throw error;
    }
}

async function realizarLogin(loginData) {
    try {
        const response = await fazerRequisicao('login.php', {
            method: 'POST',
            body: JSON.stringify(loginData)
        });
        
        if (response.success) {
            // Salvar dados do usuário no localStorage (persiste após F5)
            localStorage.setItem('user', JSON.stringify(response.data.user));
            localStorage.setItem('csrf_token', response.data.csrf_token);
            localStorage.setItem('login_time', Date.now());
            
            // Redirecionar baseado no tipo de usuário
            if (response.data.user.tipo === 'admin') {
                window.location.href = 'admin.html';
            } else {
                window.location.href = 'dashboard.html';
            }
        } else {
            throw new Error(response.message);
        }
    } catch (error) {
        mostrarNotificacao(error.message, 'error');
        throw error;
    }
}

function logout() {
    if (confirm('Deseja realmente sair do sistema?')) {
        // Limpar dados da sessão
        localStorage.removeItem('user');
        localStorage.removeItem('csrf_token');
        localStorage.removeItem('login_time');
        
        // Fazer logout no servidor
        fazerRequisicao('logout.php', { method: 'POST' })
            .catch(console.error)
            .finally(() => {
                window.location.href = 'login.html';
            });
    }
}

async function verificarAutenticacao() {
    // Usar o novo sistema inteligente de verificação
    const sessaoValida = await verificarSessaoInteligente(true);
    
    if (!sessaoValida) {
        return null;
    }
    
    try {
        const user = localStorage.getItem('user');
        const userData = JSON.parse(user);
        return userData;
    } catch (error) {
        console.error('Erro ao parsear dados do usuário:', error);
        localStorage.clear();
        return null;
    }
}

async function verificarTipoUsuario(tipoRequerido) {
    const user = await verificarAutenticacao();
    
    if (!user || user.tipo !== tipoRequerido) {
        mostrarNotificacao('Acesso negado para este tipo de usuário', 'error');
        
        // Redirecionar para a página apropriada
        setTimeout(() => {
            if (user?.tipo === 'admin') {
                window.location.href = 'admin.html';
            } else if (user?.tipo === 'funcionario') {
                window.location.href = 'dashboard.html';
            } else {
                window.location.href = 'login.html';
            }
        }, 2000);
        
        return false;
    }
    
    return true;
}

// Event Listeners
document.addEventListener('DOMContentLoaded', function() {
    // Se estiver na página de login
    if (document.getElementById('loginForm')) {
        // Verificar se já está logado
        const user = localStorage.getItem('user');
        const loginTime = localStorage.getItem('login_time');
        
        if (user && loginTime) {
            // Verificar se login não expirou (8 horas)
            const tempoExpiracao = 8 * 60 * 60 * 1000; // 8 horas em millisegundos
            if (Date.now() - parseInt(loginTime) <= tempoExpiracao) {
                try {
                    const userData = JSON.parse(user);
                    if (userData.tipo === 'admin') {
                        window.location.href = 'admin.html';
                    } else {
                        window.location.href = 'dashboard.html';
                    }
                    return;
                } catch (error) {
                    localStorage.clear();
                }
            } else {
                localStorage.clear();
            }
        }
        
        // Configurar formulário de login
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const loginBtn = document.querySelector('.btn-login');
            mostrarLoading(loginBtn);
            
            const formData = new FormData(this);
            const loginData = {
                login: formData.get('login'),
                senha: formData.get('senha')
            };
            
            try {
                await realizarLogin(loginData);
            } catch (error) {
                console.error('Erro no login:', error);
            } finally {
                mostrarLoading(loginBtn, false);
            }
        });
    }
    
    // Se estiver nas outras páginas, verificar autenticação
    // Exceto login.html e funcionario.html (que tem comportamento especial)
    else if (!window.location.pathname.includes('login.html') && 
             !window.location.pathname.includes('funcionario.html')) {
        verificarAutenticacao().catch(console.error);
    }
});

// Sistema inteligente de verificação de sessão baseado em atividade do usuário
let ultimaVerificacaoSessao = 0;
let avisoExpiracaoMostrado = false;
const INTERVALO_VERIFICACAO = 5 * 60 * 1000; // 5 minutos
const TEMPO_AVISO_EXPIRACAO = 10 * 60 * 1000; // 10 minutos antes de expirar

// Função para verificar sessão de forma inteligente
async function verificarSessaoInteligente(forcarVerificacao = false) {
    const agora = Date.now();
    
    // Só verifica se passou o intervalo ou se forçado
    if (!forcarVerificacao && (agora - ultimaVerificacaoSessao) < INTERVALO_VERIFICACAO) {
        return true;
    }
    
    ultimaVerificacaoSessao = agora;
    
    // Log de auditoria para verificação de sessão
    console.log(`[AUDIT] Verificação de sessão iniciada - ${new Date().toISOString()}`);
    
    const user = localStorage.getItem('user');
    const loginTime = localStorage.getItem('login_time');
    
    if (!user || !loginTime) {
        console.log(`[AUDIT] Sessão não encontrada`);
        
        // Comportamento especial para página de funcionário
        if (window.location.pathname.includes('funcionario.html')) {
            console.log(`[AUDIT] Página funcionário - mantendo ativa sem sessão`);
            // Não mostrar modal nem redirecionar - página permanece ativa
            return false; // Sessão inválida, mas página permanece ativa
        } else {
            // Para outras páginas, redirecionar normalmente
            console.log(`[AUDIT] Redirecionando para login`);
            if (!window.location.pathname.includes('login.html')) {
                mostrarModalExpiracaoSessao('Sessão não encontrada. Faça login novamente.');
            }
            return false;
        }
    }
    
    // Verificar se login não expirou (4 horas - reduzido de 8 para melhor segurança)
    const tempoExpiracao = 4 * 60 * 60 * 1000; // 4 horas em millisegundos
    const tempoRestante = tempoExpiracao - (agora - parseInt(loginTime));
    
    if (tempoRestante <= 0) {
        console.log(`[AUDIT] Sessão expirada por timeout`);
        
        // Comportamento especial para página de funcionário
        if (window.location.pathname.includes('funcionario.html')) {
            console.log(`[AUDIT] Página funcionário - mantendo ativa com aviso`);
            mostrarAvisoExpiracaoSessao(0); // Mostrar aviso de sessão expirada
            return false; // Sessão inválida, mas página permanece ativa
        } else {
            // Para outras páginas, redirecionar normalmente
            localStorage.clear();
            mostrarModalExpiracaoSessao('Sessão expirada. Faça login novamente.');
            return false;
        }
    }
    
    // Mostrar aviso se restam menos de 10 minutos
    if (tempoRestante <= TEMPO_AVISO_EXPIRACAO && !avisoExpiracaoMostrado) {
        avisoExpiracaoMostrado = true;
        mostrarAvisoExpiracaoSessao(tempoRestante);
    }
    
    // Verificar sessão no servidor apenas se necessário
    try {
        const response = await fazerRequisicao('auth.php?action=status');
        if (!response.success) {
            console.log(`[AUDIT] Sessão expirada no servidor`);
            
            // Comportamento especial para página de funcionário
            if (window.location.pathname.includes('funcionario.html')) {
                console.log(`[AUDIT] Página funcionário - mantendo ativa com aviso`);
                mostrarAvisoExpiracaoSessao(0); // Mostrar aviso de sessão expirada
                return false; // Sessão inválida, mas página permanece ativa
            } else {
                // Para outras páginas, redirecionar normalmente
                localStorage.clear();
                mostrarModalExpiracaoSessao('Sessão expirada no servidor. Faça login novamente.');
                return false;
            }
        }
        console.log(`[AUDIT] Verificação de sessão concluída com sucesso`);
        return true;
    } catch (error) {
        console.error('Erro ao verificar sessão:', error);
        // Em caso de erro de rede, não forçar logout
        return true;
    }
}

// Event listeners para atividade do usuário
let timeoutAtividade;
function resetarTimeoutAtividade() {
    clearTimeout(timeoutAtividade);
    timeoutAtividade = setTimeout(() => {
        // Só verificar se não estiver na página de funcionário sem sessão
        if (!window.location.pathname.includes('funcionario.html') || 
            (localStorage.getItem('user') && localStorage.getItem('login_time'))) {
            verificarSessaoInteligente();
        }
    }, INTERVALO_VERIFICACAO);
}

// Detectar atividade do usuário
['click', 'keypress', 'scroll', 'mousemove'].forEach(evento => {
    document.addEventListener(evento, resetarTimeoutAtividade, { passive: true });
});

// Verificação inicial após 5 minutos de inatividade (apenas se houver sessão)
if (!window.location.pathname.includes('funcionario.html') || 
    (localStorage.getItem('user') && localStorage.getItem('login_time'))) {
    resetarTimeoutAtividade();
}

// Exportar funções globalmente
window.preencherDemo = preencherDemo;
window.logout = logout;
window.mostrarNotificacao = mostrarNotificacao;
window.fazerRequisicao = fazerRequisicao;
window.verificarAutenticacao = verificarAutenticacao;
window.verificarTipoUsuario = verificarTipoUsuario;
window.mostrarLoading = mostrarLoading;
window.verificarSessaoInteligente = verificarSessaoInteligente;
window.renovarSessao = renovarSessao;
window.mostrarModalExpiracaoSessao = mostrarModalExpiracaoSessao;
window.mostrarAvisoExpiracaoSessao = mostrarAvisoExpiracaoSessao;