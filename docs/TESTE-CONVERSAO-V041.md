# Teste de homologação — conversão subtarefa → tarefa v0.4.1-rc1

**Estado:** release candidate em PR #5; não publicar nem atualizar a release v0.4.0 até homologação manual.

## Problema tratado

Na conversão padrão do Kanboard, a subtarefa original é removida após criar o novo cartão. As datas do ScheduleExport estão vinculadas ao ID da subtarefa, e o FK cascade exclui o planejamento antigo.

## Critérios de aceite

1. Criar uma subtarefa nativa com datas de planejamento explícitas (data/hora de início e término) e registrar as horas estimadas/gastas.
2. Converter a subtarefa em tarefa pelo menu nativo do Kanboard. Confirmar na nova tarefa os campos nativos **Início** e **Prazo** com os mesmos instantes planejados, respeitando fuso horário do servidor.
3. Confirmar que a nova tarefa preserva título, responsável, cor, categoria, coluna, raia, etiquetas, vínculo com o pai e tempo estimado/gasto conforme a própria conversão nativa.
4. Abrir a exportação XML unificada e importar no ProjectLibre. A nova tarefa deve ter a barra do período transferido.
5. Repetir com subtarefa **sem datas**: o cartão resultante não deve receber início/prazo inventados.
6. Repetir conversão de subtarefa **sem planejamento** em projeto de teste, verificando comportamento nativo.
7. Testar falha na criação de tarefa (permissão/coluna restrita) e confirmar que a subtarefa e suas datas permanecem.
8. Revalidar adicionar/editar/remover período, exportação detalhada, modo legado e saúde do Kanboard.
9. Verificar que **não há impacto** na extensão K-OrkFlowTech, nem reescrita de `time_estimated`/`time_spent` além do comportamento padrão do Kanboard.
10. Inspecionar a tabela `task_has_metadata` da tarefa convertida: `schedule_export_source_subtask_id`, `schedule_export_workday_minutes`, `schedule_export_workdays`.

## Restrições e riscos

- A implementação substitui o serviço DI do Kanboard `subtaskTaskConversionModel`; outros plugins que substituem o mesmo serviço podem conflitar.
- A conversão nativa não é transacional de ponta a ponta, incluindo vínculo de cartões, etiquetas e metadados. Evitar afirmar atomicidade absoluta.
- A interpretação de `datetime-local` utiliza o fuso horário configurado no processo PHP. Verificar horário de verão/fusos diferentes em ambiente de teste.
- Em bancos SQLite, o planejamento antigo pode ser removido por cascata quando a subtarefa nativa é excluída: isso é esperado **depois** de transferir as datas e metadados.
- O valor dos campos de jornada e dias úteis é preservado em metadados da tarefa; a UI nativa do cartão não os apresenta automaticamente.
- Não usar cartões reais nem dados sensíveis na homologação.

## Automação

`php tests/test_subtask_conversion_v041.php` — regressão SQLite simulando o contrato de modelos Kanboard, cenários datados/sem datas, permissão de projeto e falha de criação. Não substitui teste de integração com Kanboard real.
