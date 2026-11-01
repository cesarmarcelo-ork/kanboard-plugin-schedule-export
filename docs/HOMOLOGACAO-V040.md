# Homologação da Schedule Export v0.4.0

## Decisões finais

- O plugin é responsável exclusivamente pelo planejamento temporal explícito das subtarefas nativas e pela exportação MSPDI para ProjectLibre.
- `time_estimated`, `time_spent` e o rastreamento de tempo nativo permanecem sob responsabilidade do Kanboard; o plugin **não os altera**.
- Os períodos persistem na tabela SQLite `schedule_export_subtask_plans`; a extensão K-OrkFlowTech mantém seus próprios dados locais, sem sincronização automática.
- A exportação tradicional da versão v0.2.0 permanece disponível, assim como o modo detalhado.
- Datas efetivamente cadastradas são exportadas como barras; períodos do cartão pai são preservados em uma linha auxiliar na EAP.
- Subtarefas sem datas são exportadas com `Duration=PT0H0M0S`, `Milestone=0` e rótulo `[SEM DATAS — NAO PLANEJADA]`. O ProjectLibre 1.9.8, porém, **desenha essas tarefas de duração zero como losangos** mesmo com `Milestone=0`; é uma limitação visual observada na homologação. Os losangos não indicam marcos de prazo cadastrados.
- Não inventar datas ou durações para contornar essa visualização.

## Testes já observados na homelab

1. Kanboard saudável após instalar v0.4.0-dev.
2. Migração SQLite aplicada, tabela com nove campos criada.
3. Gravação confirmada para subtarefa nativa #30 do cartão #9.
4. ProjectLibre exibiu barra para 03/11/2026 a 06/11/2026 e para alteração posterior até 30/11/2026.
5. Remoção do planejamento fez a barra desaparecer e o ProjectLibre voltou a exibir marcador de duração zero.

## Verificações ainda necessárias antes de marcar produção estável

- Instalar revisão final em ambiente de teste com backup e confirmar formulário no navegador.
- Confirmar recuperação das datas após atualização do código.
- Confirmar exportação tradicional e detalhada, logs sem erro e autorização por funções de projeto.
- Verificar manualmente interação com a extensão K-OrkFlowTech.
- Aprovar versão final e gerar tag de release estável **somente após** essas verificações.

## Backup para a instalação de revisão

O operador confirmou um backup anterior em:
`/opt/orkflowtech/kanboard/backups/v040-20261101-102931`.
Para cada atualização posterior, executar um novo backup SQLite consistente e um backup do diretório do plugin.
