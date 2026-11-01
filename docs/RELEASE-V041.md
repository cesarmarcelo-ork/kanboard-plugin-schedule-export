# Schedule Export v0.4.1 — Estável

Correção do planejamento durante a conversão nativa **subtarefa → tarefa** no Kanboard.

## Correções e comportamento
- Datas `planned_start`/`planned_end` são transferidas aos campos nativos `date_started`/`date_due` da nova tarefa antes que a subtarefa seja excluída.
- Subtarefas sem planejamento continuam criando tarefas sem datas inventadas.
- Jornada e dias úteis do planejamento são preservados em metadados do novo cartão.
- Tempos estimado e gasto seguem o fluxo nativo do Kanboard, sem recálculo pelo plugin.
- Exportação MSPDI e menu XML unificado da v0.4.0 preservados.

## Verificações
- GitHub Actions: testes e verificações PHP 8.1–8.4, incluindo regressão de conversão com SQLite, propriedades do cartão e cenários sem datas/erro.
- Instalação de teste na homelab; sintaxe PHP válida e container saudável.
- Testes funcionais manuais informados pelo mantenedor.

## Limitações e precauções
- A integração substitui o serviço de conversão `subtaskTaskConversionModel`; plugins que substituam o mesmo serviço podem entrar em conflito.
- A conversão nativa e os metadados não constituem uma transação indivisível; manter backups e homologar em projetos de teste.
- A equivalência entre fusos horários e ambientes diferentes exige validação.
- ProjectLibre pode desenhar subtarefas sem datas como losangos mesmo sem marcos reais.

## Atualização
Fazer backup do banco e da pasta `plugins/ScheduleExport`; substituir apenas os arquivos do plugin pela nova versão e confirmar `getPluginVersion() = 0.4.1` e saúde do Kanboard. Não instalar duas cópias do plugin.
