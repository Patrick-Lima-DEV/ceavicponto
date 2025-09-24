// Classe para cálculos avançados de tempo
class TimeCalculator {
    constructor() {
        this.toleranciaMinutos = 10; // Padrão
    }
    
    // Converter string de tempo para minutos
    timeToMinutes(timeString) {
        if (!timeString || timeString === '--') return 0;
        const parts = timeString.split(':');
        const hours = parseInt(parts[0]);
        const minutes = parseInt(parts[1]);
        const seconds = parts[2] ? parseInt(parts[2]) : 0;
        
        // Converter segundos para minutos (exato, sem arredondamento)
        const totalMinutes = hours * 60 + minutes + (seconds / 60);
        return totalMinutes;
    }
    
    // Converter minutos para string de tempo
    minutesToTime(totalMinutes) {
        if (totalMinutes < 0) {
            return '-' + this.minutesToTime(Math.abs(totalMinutes));
        }
        
        const hours = Math.floor(totalMinutes / 60);
        const minutes = Math.floor(totalMinutes % 60);
        
        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }
    
    // Calcular diferença entre dois horários
    calcularDiferenca(horaInicio, horaFim) {
        const inicioMinutos = this.timeToMinutes(horaInicio);
        const fimMinutos = this.timeToMinutes(horaFim);
        
        let diferenca = fimMinutos - inicioMinutos;
        
        // Se a hora final for menor que a inicial (passou da meia-noite)
        if (diferenca < 0) {
            diferenca += 24 * 60; // Adicionar 24 horas em minutos
        }
        
        return diferenca;
    }
    
    // Calcular horas trabalhadas em um dia
    calcularHorasTrabalhadas(registros) {
        const tipos = {};
        
        // Organizar registros por tipo
        registros.forEach(registro => {
            tipos[registro.tipo] = registro.hora;
        });
        
        let totalMinutos = 0;
        
        // Mapear tipos para nomes corretos
        const entrada = tipos.entrada_manha || tipos.entrada;
        const saidaAlmoco = tipos.saida_almoco || tipos.almoco_saida;
        const voltaAlmoco = tipos.volta_almoco || tipos.almoco_volta;
        const saida = tipos.saida_tarde || tipos.saida;
        
        // Calcular período da manhã (entrada até saída para almoço)
        if (entrada && saidaAlmoco) {
            totalMinutos += this.calcularDiferenca(entrada, saidaAlmoco);
        }
        
        // Calcular período da tarde (volta do almoço até saída)
        if (voltaAlmoco && saida) {
            totalMinutos += this.calcularDiferenca(voltaAlmoco, saida);
        }
        
        // Se não tem intervalo de almoço, calcular direto
        if (!saidaAlmoco && !voltaAlmoco && entrada && saida) {
            totalMinutos = this.calcularDiferenca(entrada, saida);
        }
        
        // Se só tem entrada, calcular até agora
        if (entrada && !saida && !saidaAlmoco) {
            const agora = new Date();
            const horaAtual = `${agora.getHours().toString().padStart(2, '0')}:${agora.getMinutes().toString().padStart(2, '0')}:00`;
            totalMinutos = this.calcularDiferenca(entrada, horaAtual);
        }
        
        // Se está no meio do expediente (saiu para almoço mas não voltou)
        if (entrada && saidaAlmoco && !voltaAlmoco) {
            totalMinutos = this.calcularDiferenca(entrada, saidaAlmoco);
        }
        
        // Se voltou do almoço mas não saiu
        if (entrada && saidaAlmoco && voltaAlmoco && !saida) {
            const agora = new Date();
            const horaAtual = `${agora.getHours().toString().padStart(2, '0')}:${agora.getMinutes().toString().padStart(2, '0')}:00`;
            
            totalMinutos = this.calcularDiferenca(entrada, saidaAlmoco) + 
                          this.calcularDiferenca(voltaAlmoco, horaAtual);
        }
        
        return this.minutesToTime(totalMinutos);
    }
    
    // Calcular saldo com base em horários cadastrados vs batidas reais
    calcularSaldoJornada(batidasReais, horariosCadastrados, dataRegistro = null) {
        const batidas = {};
        batidasReais.forEach(registro => {
            batidas[registro.tipo] = registro.hora;
        });
        
        // Verificar se é domingo (folga) ou sábado (meio período)
        if (dataRegistro) {
            // Corrigir problema de timezone - usar mesmo método do admin.js
            const [ano, mes, dia] = dataRegistro.split('-');
            const data = new Date(parseInt(ano), parseInt(mes) - 1, parseInt(dia));
            const diaSemana = data.getDay(); // 0 = domingo, 1 = segunda, etc.
            
            if (diaSemana === 0) { // Domingo
                return {
                    saldo: 0,
                    saldoFormatado: '00:00:00',
                    status: 'normal',
                    tipo: 'domingo_folga',
                    totalFaltas: 0,
                    totalExtras: 0,
                    detalhes: ['Domingo - Folga']
                };
            }
            
            if (diaSemana === 6) { // Sábado - meio período
                // Para sábados, usar carga de 4 horas (240 min) em vez de 8 horas
                const cargaSabadoMinutos = 240; // 4 horas
                const toleranciaSabado = 5; // 5 minutos de tolerância para sábados
                
                // Calcular horas trabalhadas apenas com entrada e saída
                const entrada = batidas.entrada_manha || batidas.entrada;
                const saida = batidas.saida_tarde || batidas.saida;
                
                if (entrada && saida) {
                    // Dia completo de sábado
                    const entradaMin = this.timeToMinutes(entrada);
                    const saidaMin = this.timeToMinutes(saida);
                    const totalTrabalhado = saidaMin - entradaMin;
                    
                    const diferenca = totalTrabalhado - cargaSabadoMinutos;
                    let saldoFormatado = '00:00:00';
                    
                    if (Math.abs(diferenca) > toleranciaSabado) {
                        saldoFormatado = this.minutesToTime(diferenca);
                    }
                    
                    return {
                        saldo: diferenca,
                        saldoFormatado: saldoFormatado,
                        status: Math.abs(diferenca) > toleranciaSabado ? (diferenca > 0 ? 'extra' : 'falta') : 'normal',
                        tipo: 'sabado_meio_periodo',
                        totalFaltas: diferenca < 0 ? Math.abs(diferenca) : 0,
                        totalExtras: diferenca > 0 ? diferenca : 0,
                        detalhes: [`Sábado - Meio período: ${this.minutesToTime(totalTrabalhado)} trabalhado`]
                    };
                } else if (entrada) {
                    // Sábado com apenas entrada (dia incompleto)
                    const entradaMin = this.timeToMinutes(entrada);
                    const entradaEsperadaMin = this.timeToMinutes(horariosCadastrados.entrada_manha || '08:00:00');
                    
                    // Calcular atraso na entrada
                    const atrasoEntrada = entradaMin - entradaEsperadaMin;
                    
                    // Para sábado incompleto, calcular falta total
                    // Carga esperada: 4 horas (240 min)
                    // Como só bateu entrada e não saiu, considera falta total de 4h
                    // Mas se chegou adiantado, reduz a falta
                    const faltaTotal = cargaSabadoMinutos - Math.max(0, -atrasoEntrada);
                    
                    return {
                        saldo: -faltaTotal,
                        saldoFormatado: faltaTotal > 0 ? `-${this.minutesToTime(faltaTotal)}` : '00:00:00',
                        status: faltaTotal > toleranciaSabado ? 'falta' : 'normal',
                        tipo: 'sabado_incompleto',
                        totalFaltas: faltaTotal,
                        totalExtras: 0,
                        detalhes: [`Sábado - Apenas entrada (falta total: ${this.minutesToTime(faltaTotal)}, atraso: ${atrasoEntrada}min)`]
                    };
                }
            }
        }
        
        // Mapear batidas para nomes padronizados
        const entrada = batidas.entrada_manha || batidas.entrada;
        const saidaAlmoco = batidas.saida_almoco || batidas.almoco_saida;
        const voltaAlmoco = batidas.volta_almoco || batidas.almoco_volta;
        const saida = batidas.saida_tarde || batidas.saida;
        
        let totalFaltas = 0;
        let totalExtras = 0;
        const detalhes = [];
        
        // Calcular atrasos com compensações parciais (mesma lógica do funcionario.html)
        let atrasosMinutos = 0;
        
        // Atraso na entrada
        if (entrada && horariosCadastrados.entrada_manha) {
            const entradaMin = this.timeToMinutes(entrada);
            const entradaCadastradaMin = this.timeToMinutes(horariosCadastrados.entrada_manha);
            if (entradaMin > entradaCadastradaMin) {
                const atrasoEntrada = entradaMin - entradaCadastradaMin;
                atrasosMinutos += atrasoEntrada;
                detalhes.push(`Entrada: ${atrasoEntrada}min atraso`);
            }
        }
        
        // Atraso no retorno do almoço
        if (voltaAlmoco && horariosCadastrados.volta_almoco) {
            const voltaAlmocoMin = this.timeToMinutes(voltaAlmoco);
            const voltaAlmocoCadastradaMin = this.timeToMinutes(horariosCadastrados.volta_almoco);
            if (voltaAlmocoMin > voltaAlmocoCadastradaMin) {
                const atrasoVolta = voltaAlmocoMin - voltaAlmocoCadastradaMin;
                atrasosMinutos += atrasoVolta;
                detalhes.push(`Retorno almoço: ${atrasoVolta}min atraso`);
            }
        }
        
        // Calcular horas trabalhadas totais
        let horasTrabalhadasMinutos = 0;
        
        // Calcular período da manhã (entrada até saída para almoço)
        if (entrada && saidaAlmoco) {
            horasTrabalhadasMinutos += this.calcularDiferenca(entrada, saidaAlmoco);
        }
        
        // Calcular período da tarde (volta do almoço até saída)
        if (voltaAlmoco && saida) {
            horasTrabalhadasMinutos += this.calcularDiferenca(voltaAlmoco, saida);
        }
        
        // Calcular diferença entre trabalhado e esperado
        const cargaDiariaMinutos = 480; // 8 horas padrão
        const diferenca = horasTrabalhadasMinutos - cargaDiariaMinutos;
        
        let extrasMinutos = 0;
        let faltasMinutos = 0;
        
        if (diferenca > 0) {
            extrasMinutos = diferenca;
            detalhes.push(`Total: ${diferenca}min extra`);
        } else if (diferenca < 0) {
            faltasMinutos = Math.abs(diferenca);
            detalhes.push(`Total: ${Math.abs(diferenca)}min falta`);
        }
        
        // Calcular saldo final: extras - faltas (não subtrair atrasos separadamente)
        const saldoFinal = extrasMinutos - faltasMinutos;
        
        return {
            totalFaltas: faltasMinutos,
            totalExtras: extrasMinutos,
            saldoFinal: saldoFinal,
            saldoFormatado: saldoFinal >= 0 ? `+${this.minutesToTime(Math.abs(saldoFinal))}` : `-${this.minutesToTime(Math.abs(saldoFinal))}`,
            tipo: saldoFinal >= 0 ? 'extra' : 'falta',
            status: saldoFinal >= 0 ? 'extras' : 'faltas',
            detalhes: detalhes
        };
    }
    
    // Calcular saldo (diferença entre trabalhado e esperado)
    calcularSaldo(horasTrabalhadasStr, horasEsperadasStr, toleranciaMinutos = null) {
        if (toleranciaMinutos !== null) {
            this.toleranciaMinutos = toleranciaMinutos;
        }
        
        const trabalhadas = this.timeToMinutes(horasTrabalhadasStr);
        const esperadas = this.timeToMinutes(horasEsperadasStr);
        
        const diferenca = trabalhadas - esperadas;
        const diferencaAbsoluta = Math.abs(diferenca);
        
        // Verificar se está dentro da tolerância
        if (diferencaAbsoluta <= this.toleranciaMinutos) {
            return {
                saldo: '00:00:00',
                status: 'normal',
                tipo: 'dentro da tolerância'
            };
        }
        
        const saldoStr = this.minutesToTime(diferencaAbsoluta);
        
        if (diferenca > 0) {
            return {
                saldo: '+' + saldoStr,
                status: 'extras',
                tipo: 'horas extras'
            };
        } else {
            return {
                saldo: '-' + saldoStr,
                status: 'faltas',
                tipo: 'horas em falta'
            };
        }
    }
    
    // Obter próximo registro esperado
    obterProximoRegistro(registrosHoje) {
        const sequencia = ['entrada', 'almoco_saida', 'almoco_volta', 'saida'];
        const tiposRegistrados = registrosHoje.map(r => r.tipo);
        
        for (let i = 0; i < sequencia.length; i++) {
            if (!tiposRegistrados.includes(sequencia[i])) {
                return {
                    tipo: sequencia[i],
                    nome: this.obterNomeTipo(sequencia[i]),
                    posicao: i
                };
            }
        }
        
        return null; // Todos os registros foram feitos
    }
    
    // Obter nome amigável do tipo de registro
    obterNomeTipo(tipo) {
        const nomes = {
            'entrada': 'Entrada',
            'almoco_saida': 'Saída para Almoço',
            'almoco_volta': 'Volta do Almoço',
            'saida': 'Saída'
        };
        return nomes[tipo] || tipo;
    }
    
    // Verificar se o dia está completo (considerando sábados e justificativas)
    diaCompleto(registros, data = null, justificativa = null) {
        // Se há justificativa, o dia é sempre completo
        if (justificativa) {
            return true;
        }
        
        // Se é domingo, é sempre completo (folga)
        if (data) {
            const diaSemana = new Date(data + 'T00:00:00').getDay();
            if (diaSemana === 0) { // Domingo
                return true;
            }
        }
        
        const tipos = registros.map(r => r.tipo);
        
        // Se é sábado, precisa apenas de entrada e saída (meio período)
        if (data) {
            const diaSemana = new Date(data + 'T00:00:00').getDay();
            if (diaSemana === 6) { // Sábado
                return tipos.includes('entrada_manha') && tipos.includes('saida_tarde');
            }
        }
        
        // Dias úteis (segunda a sexta) - precisam de todos os 4 pontos
        return tipos.includes('entrada_manha') && 
               tipos.includes('saida_almoco') && 
               tipos.includes('volta_almoco') && 
               tipos.includes('saida_tarde');
    }
    
    // Calcular estatísticas de período (função centralizada para todos os relatórios)
    calcularEstatisticasPeriodo(registros, horariosCadastrados = null) {
        let totalHorasTrabalhadas = 0;
        let totalExtras = 0;
        let totalFaltas = 0;
        let diasCompletos = 0;
        let diasIncompletos = 0;
        
        // Processar cada registro
        registros.forEach(registro => {
            const data = registro.data;
            const diaSemana = new Date(data + 'T00:00:00').getDay();
            const isDomingo = diaSemana === 0;
            const isSabado = diaSemana === 6;
            
            // Pular domingos e registros com justificativa
            if (isDomingo || registro.justificativa) {
                return;
            }
            
            // Preparar batidas reais
            const batidasReais = [
                { tipo: 'entrada_manha', hora: registro.entrada_manha.includes(':') ? registro.entrada_manha : registro.entrada_manha + ':00' },
                { tipo: 'saida_almoco', hora: registro.saida_almoco.includes(':') ? registro.saida_almoco : registro.saida_almoco + ':00' },
                { tipo: 'volta_almoco', hora: registro.volta_almoco.includes(':') ? registro.volta_almoco : registro.volta_almoco + ':00' },
                { tipo: 'saida_tarde', hora: registro.saida_tarde.includes(':') ? registro.saida_tarde : registro.saida_tarde + ':00' }
            ].filter(p => p.hora !== '--:00' && p.hora !== 'null:00' && p.hora !== '--');
            
            // Calcular horas trabalhadas
            const horasTrabalhadasStr = this.calcularHorasTrabalhadas(batidasReais);
            if (horasTrabalhadasStr) {
                const horasMin = this.timeToMinutes(horasTrabalhadasStr);
                totalHorasTrabalhadas += horasMin;
            }
            
            // Calcular saldo da jornada
            const saldoJornada = this.calcularSaldoJornada(batidasReais, horariosCadastrados, data);
            totalExtras += saldoJornada.totalExtras || 0;
            totalFaltas += saldoJornada.totalFaltas || 0;
            
            // Verificar se o dia está completo
            const completo = this.diaCompleto(batidasReais, data, registro.justificativa);
            if (completo) {
                diasCompletos++;
            } else {
                diasIncompletos++;
            }
        });
        
        // Calcular saldo final
        const saldoFinal = totalExtras - totalFaltas;
        
        return {
            totalHorasTrabalhadas: this.minutesToTime(totalHorasTrabalhadas),
            totalExtras: this.minutesToTime(totalExtras),
            totalFaltas: this.minutesToTime(totalFaltas),
            saldoFinal: this.minutesToTime(Math.abs(saldoFinal)),
            saldoFinalMinutos: saldoFinal,
            diasCompletos: diasCompletos,
            diasIncompletos: diasIncompletos,
            totalDias: diasCompletos + diasIncompletos
        };
    }
    
    // Calcular estatísticas do mês
    calcularEstatisticasMensais(registrosMes, cargaDiariaStr = '08:00:00') {
        const cargaDiaria = this.timeToMinutes(cargaDiariaStr);
        let totalTrabalhado = 0;
        let diasTrabalhados = 0;
        let horasExtras = 0;
        let horasFaltas = 0;
        
        // Agrupar por data
        const registrosPorDia = {};
        registrosMes.forEach(registro => {
            if (!registrosPorDia[registro.data]) {
                registrosPorDia[registro.data] = [];
            }
            registrosPorDia[registro.data].push(registro);
        });
        
        // Calcular para cada dia
        Object.values(registrosPorDia).forEach(registrosDia => {
            if (this.diaCompleto(registrosDia)) {
                const horasTrabalhadas = this.calcularHorasTrabalhadas(registrosDia);
                const minutosTrabalhados = this.timeToMinutes(horasTrabalhadas);
                
                totalTrabalhado += minutosTrabalhados;
                diasTrabalhados++;
                
                const saldo = this.calcularSaldo(horasTrabalhadas, cargaDiariaStr);
                if (saldo.status === 'extras') {
                    horasExtras += this.timeToMinutes(saldo.saldo.replace('+', ''));
                } else if (saldo.status === 'faltas') {
                    horasFaltas += this.timeToMinutes(saldo.saldo.replace('-', ''));
                }
            }
        });
        
        return {
            totalTrabalhado: this.minutesToTime(totalTrabalhado),
            diasTrabalhados,
            mediaDiaria: diasTrabalhados > 0 ? this.minutesToTime(totalTrabalhado / diasTrabalhados) : '00:00:00',
            horasExtras: this.minutesToTime(horasExtras),
            horasFaltas: this.minutesToTime(horasFaltas),
            saldoMensal: this.minutesToTime(totalTrabalhado - (diasTrabalhados * cargaDiaria))
        };
    }
    
    // Formatar data para exibição
    formatarData(dataStr) {
        const data = new Date(dataStr + 'T00:00:00');
        return data.toLocaleDateString('pt-BR');
    }
    
    // Formatar data e hora atual
    obterDataHoraAtual() {
        const agora = new Date();
        
        return {
            data: agora.toLocaleDateString('pt-BR'),
            hora: agora.toLocaleTimeString('pt-BR'),
            dataISO: agora.toISOString().split('T')[0],
            horaISO: `${agora.getHours().toString().padStart(2, '0')}:${agora.getMinutes().toString().padStart(2, '0')}:${agora.getSeconds().toString().padStart(2, '0')}`
        };
    }
    
    // Validar horário de trabalho
    validarHorarioTrabalho(hora, tipoRegistro) {
        const [horas, minutos] = hora.split(':').map(Number);
        const totalMinutos = horas * 60 + minutos;
        
        // Horários típicos de trabalho (6h às 22h)
        const inicioExpediente = 6 * 60; // 06:00
        const fimExpediente = 22 * 60;   // 22:00
        
        if (totalMinutos < inicioExpediente || totalMinutos > fimExpediente) {
            return {
                valido: false,
                mensagem: `Horário fora do expediente normal (06:00 às 22:00)`
            };
        }
        
        return { valido: true };
    }
    
    // Calcular tempo restante para completar a jornada
    calcularTempoRestante(registrosHoje, cargaDiariaStr) {
        const horasTrabalhadasStr = this.calcularHorasTrabalhadas(registrosHoje);
        const trabalhadas = this.timeToMinutes(horasTrabalhadasStr);
        const esperadas = this.timeToMinutes(cargaDiariaStr);
        
        const restante = esperadas - trabalhadas;
        
        if (restante <= 0) {
            return {
                tempo: '00:00:00',
                concluido: true,
                mensagem: 'Jornada completa'
            };
        }
        
        return {
            tempo: this.minutesToTime(restante),
            concluido: false,
            mensagem: `Faltam ${this.minutesToTime(restante)} para completar a jornada`
        };
    }
}

// Instância global
const timeCalculator = new TimeCalculator();

// Exportar para uso global
window.TimeCalculator = TimeCalculator;
window.timeCalculator = timeCalculator;