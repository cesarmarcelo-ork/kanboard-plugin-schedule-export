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
- **Todo cartão permanece no XML com seu próprio UID, título e hierarquia**, mesmo tendo cartões-filhos, subtarefas nativas ou ambos.
- No modo detalhado, quando um cartão se torna tarefa-resumo e possui início e fim válidos no Kanboard, uma linha auxiliar **`Periodo do cartao: <titulo>`** preserva sua barra original. Ela é subordinada ao cartão na EAP, mas **não** é um segundo cartão, uma subtarefa nativa nem uma nova atividade.
- Se o cartão-resumo possui somente início, a linha auxiliar registra apenas um marco de início; se possui somente prazo, o marco auxiliar de prazo já existente é utilizado. Sem datas, não se inventa uma barra.
- A barra de resumo pode ser recalculada pelo ProjectLibre conforme seus descendentes e incluir marcos técnicos de subtarefas sem datas. **A referência fiel do período próprio do cartão é a linha auxiliar `Periodo do cartao`.** Essa representação precisa de homologação visual no ProjectLibre e OPS.
- O modo legado da v0.2.0 fica intocado: nenhuma linha adicional de período do cartão é adicionada à exportação padrão.
- Importadores MSPDI podem ter limitações para vínculos entre tarefas-resumo; conferir dependências e caminho crítico após a importação.
- Datas e horários são guardados nos campos MSPDI Start/Finish, com compatibilidade de duração do ProjectLibre 1.9.8 e calendário 7×24 já adotados pela v0.2.0.

## Estado da versão

**Pré-homologação.** A presença deste código no GitHub não significa que o ProjectLibre/OPS ou a homelab já tenham sido testados com essa versão. Não substituir a v0.2.0 estável antes dos testes de regressão e de importação visual.
