# v0.4.0 — Planejamento nativo das subtarefas

## Código de referência consultado

Repositório: `cesarmarcelo-ork/orkflowtech-extension`, módulo `src/modules/gantt/kgantt.js` e interface `src/modules/gantt/kgantt.html`.

Achados verificados:
- A extensão mantém os planos manuais sob `oft-kgantt-subplans:<server>:<projectId>`, usando `chrome.storage.local` e `localStorage` para migração.
- Chave do planejamento: `<cardId>:<subtaskId>`.
- O editor manual guarda `start`, `end`, `plannedStart`, `plannedEnd`, `updatedAt`.
- Também existe planejamento **automático**: distribuição proporcional ao período do cartão, opcionalmente considerando estimativas e dias úteis. Não é dado oficial.
- É possível exportar/importar JSON com `type=K-OrkFlowTech-Gantt-Subtask-Plan`, `version=1`, `projectId`, `server` e `plans`.

## Arquitetura definida

1. O plugin será fonte de verdade para planos explicitamente salvos no servidor, por ID de subtarefa nativa + ID do cartão/projeto, com autorização do Kanboard.
2. Tabela própria via migração de plugin, sem alteração nas tabelas nativas.
3. Interface de edição dentro da página de cartão, semelhante ao botão **Datas**, com campos separados para planejado (início/fim, jornada, dias considerados) e realizado (sem confundir time_spent nativo).
4. O formulário terá validação de calendário real, fim >= início, e disponibilidade somente a usuários com permissão de edição. Não reescrever `time_estimated` ou `time_spent` inadvertidamente.
5. Exportador MSPDI: precedência **plano nativo persistido** > declaração explícita legado `[SE_SUBTASK_PERIOD ...]` > sem datas. Se houver divergência entre plano e linha textual, registrar advertência; nunca fazer cálculos automáticos implícitos.
6. **Sem datas:** linha de tarefa com `Duration=PT0H0M0S`, `Milestone=0`, `[SEM DATAS — NAO PLANEJADA]`. Marcos são reservados aos prazos e eventos reais.
7. Cada cartão continua aparecendo como cartão na EAP; quando for tarefa-resumo, uma linha auxiliar preserva seu período próprio sem substituir as subtarefas.
8. Importação da extensão: fluxo explícito, com pré-visualização, seleção de projeto/servidor, confirmação por usuário e resolução de conflitos. Jamais ler silenciosamente o armazenamento do navegador.
9. Suporte a vários usuários: transações e verificações de atualização (updated_at) para evitar sobrescrita silenciosa.
10. Implementação em branch v0.4.0; v0.3.0 instalada permanece intocada até CI, testes em Kanboard e homologação visual no ProjectLibre 1.9.8/OPS.

## Critérios de aceitação

- Editar/substituir/excluir o planejamento de uma subtarefa dentro do cartão, sem depender da extensão.
- Datas persistem após logout, outro navegador e outro computador.
- Exportação MSPDI usa somente períodos explicitamente salvos.
- Subtarefas sem datas NÃO viram marcos.
- Planejamento não altera os dados nativos de esforço/tempo gasto automaticamente.
- Exportação padrão v0.2.0 segue compatível, integração experimental v0.3.0 permanece disponível.
- Todo acesso usa ACL e valida IDs projeto/cartão/subtarefa no servidor.
