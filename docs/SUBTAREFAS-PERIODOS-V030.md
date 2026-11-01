# Schedule Export v0.3.0 — Subtarefas e períodos explícitos (experimental)

Esta funcionalidade **não altera** a exportação padrão `Exportar cronograma (.xml)` da v0.2.0.

## Como testar

1. Em um cartão Kanboard, cadastre uma subtarefa nativa e observe seu número interno (ID). **Não confunda o ID da subtarefa com o ID do cartão.**
2. Opcionalmente, na **descrição do cartão que contém essa subtarefa**, insira uma linha no formato exato:

```text
[SE_SUBTASK_PERIOD id=15 start=2026-11-03T08:00:00 end=2026-11-05T18:00:00]
```

3. Ajuste o `id` ao ID **real** da subtarefa. Use horário local da configuração do Kanboard, formato `AAAA-MM-DDTHH:MM:SS`.
4. No menu do projeto, escolha **Exportar cronograma com subtarefas (experimental .xml)**.
5. Importe o XML no ProjectLibre 1.9.8 e confira a EAP, o início, o término e a barra. Valide também no Open Planner Studio.

## Regras de integridade

- A informação só é interpretada no **modo experimental**, nunca no modo padrão.
- A linha pertence à **descrição do cartão pai da subtarefa nativa**. Uma linha pode ser cadastrada por subtarefa.
- **Início e fim devem ser válidos; o fim deve ser posterior ao início.**
- Data inexistente, ID de subtarefa não pertencente ao cartão, linha malformada e declaração duplicada geram erro explícito, sem criar períodos presumidos.
- Subtarefas nativas **sem** linha de período aparecem como marcos técnicos `SEM DATAS — NAO PLANEJADA`, não como barras fictícias.
- O período explícito é tratado como **janela de calendário**. `time_estimated` permanece uma estimativa de esforço separada.
- A opção experimental não depende da extensão K-OrkFlowTech, não cria campos no banco, não escreve no Kanboard e não modifica as datas de origem.
- A presença de subtarefas transforma visualmente seu cartão em tarefa-resumo; importadores MSPDI podem recalcular as datas da tarefa-resumo e ter limitações para vínculos com ela. Conferir após a importação.
- Datas e horários são guardados nos campos MSPDI Start/Finish, com compatibilidade de duração do ProjectLibre 1.9.8 e calendário 7×24 já adotados pela v0.2.0.

## Estado da versão

**Pré-homologação.** A presença deste código no GitHub não significa que o ProjectLibre/OPS ou a homelab já tenham sido testados com essa versão. Não substituir a v0.2.0 estável antes dos testes de regressão e de importação visual.
