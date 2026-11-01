# Schedule Export v0.4.0 — Estável

## Novidades
- Único item **Exportar cronograma (.xml)** no menu, com modo detalhado padrão.
- Exportação MSPDI para ProjectLibre e outros importadores compatíveis, incluindo cartões, cartões-filhos, subtarefas nativas, marcos reais e datas planejadas explícitas.
- Coluna **Planejamento / Datas** na tabela de subtarefas.
- Inclusão, edição e remoção de períodos em banco próprio do plugin; suporte a SQLite/MySQL/PostgreSQL.
- Formulário responsivo; preservação das informações nativas de tempo estimado/tempo gasto.
- Exportação tradicional acessível via `subtasks=legacy`, sem duplicidade no menu.

## Limitações conhecidas
- O ProjectLibre pode desenhar subtarefas sem período como losangos, mesmo com `Duration=PT0H0M0S` e `Milestone=0`. Não significa marco real cadastrado.
- O ProjectLibre e o Open Planner Studio podem recalcular o período da tarefa-resumo com base nas subtarefas. Isso não altera automaticamente o cartão no Kanboard.
- Os campos de jornada e dias úteis são armazenados para o planejamento, mas não substituem o calendário nativo de execução nem alteram `time_estimated` ou `time_spent`.
- A extensão K-OrkFlowTech tem armazenamento separado; não há sincronização automática.
- Subtarefas sem datas podem receber uma âncora técnica no XML por compatibilidade com aplicativos de Gantt. Ela não é agendamento oficial.

## Homologação
- Testes GitHub Actions PHP 8.1–8.4.
- Testes de persistência em SQLite e regressão MSPDI.
- Verificação manual na homelab: interface, inclusão/remoção de datas e visualização no ProjectLibre 1.9.8.
- Banco da homelab permanece sob administração local e backups próprios.

## Instalação
Copiar o conteúdo desta versão para `plugins/ScheduleExport`, após backup do plugin e do banco. Não instalar dois diretórios do mesmo plugin dentro de `plugins/`. Ao acessar o Kanboard, ele aplicará a migração necessária. Exige permissões de escrita no banco.
