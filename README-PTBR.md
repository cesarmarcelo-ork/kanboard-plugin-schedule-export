# Kanboard Schedule Export — v0.5.0 (desenvolvimento); v0.4.2 (estável publicada)

Plugin de **planejamento de subtarefas e exportação** de projetos Kanboard em **Microsoft Project XML (MSPDI)**. O formato pode ser importado por vários planejadores. Testado com **ProjectLibre Desktop no Linux**; o **Open Planner Studio v2026.43.0** passou nos testes de importação, hierarquia EAP, estimativas individuais nas descrições e persistência após salvar e reabrir. O nome do plugin não significa que todos os importadores estejam homologados.

## Compatibilidade com o Kanboard (próxima manutenção)

Esta branch prepara a versão **0.4.2**, cuja compatibilidade mínima declarada passa a ser **Kanboard >= 1.2.50**. O responsável pelo projeto confirmou que os testes funcionais de subtarefas, conversão nativa e exportação XML no Kanboard 1.2.50 estão satisfatórios; os registros dessa execução não foram auditados independentemente neste repositório. A homologação anterior documentada em **1.2.57** permanece válida. A versão estável **0.4.1** publicada e a entrada no catálogo oficial continuam com **>=1.2.30** até a publicação de uma nova release e posterior atualização do catálogo. Ver [notas da 0.4.2](docs/RELEASE-V042.md).

## Novidades em desenvolvimento na v0.5.0

- **Internacionalização:** textos do plugin passam a acompanhar o idioma do Kanboard (inglês ou português brasileiro).
- **Criação com planejamento:** o formulário nativo de inclusão de subtarefas oferece um link **Criar subtarefa com planejamento**. Esse link abre um cadastro individual com título, responsável, tempo estimado e, opcionalmente, início, término, jornada e dias de trabalho.
- **Cadastro em lote preservado:** o cadastro nativo de várias subtarefas continua intacto. O novo formulário individual evita associar datas ao identificador errado.
- **Persistência coordenada:** a gravação da nova subtarefa e do seu planejamento usa o ID retornado pelo Kanboard, com validação prévia das datas e rollback quando a gravação falha.
- O editor **Datas**, a conversão em cartão e o exportador XML permanecem disponíveis.

**Estado:** implementação em branch de desenvolvimento, não publicada. Exige testes e homologação no Kanboard 1.2.50 antes de nova release.

## Funcionamento

1. Instalar em `plugins/ScheduleExport` (veja `MIGRACAO.md` para substituir o antigo `ProjectLibreExport`).
2. Abrir o projeto autorizado no Kanboard.
3. No menu do projeto, selecionar **Exportar cronograma (.xml)**.
4. Abrir o arquivo `kanboard-ID-cronograma.xml` no aplicativo compatível.

O plugin não exige token pessoal/API no navegador nem serviços externos. O planejamento de subtarefas é gravado na tabela própria `schedule_export_subtask_plans`. Nesta candidata, ao converter uma subtarefa com período planejado em tarefa, as datas são transferidas para os campos nativos do novo cartão. A extensão K-OrkFlowTech mantém dados locais separados.

## Fidelidade dos dados e limitações

- O **Kanboard é a fonte de dados**: `date_started`, `date_due` e estimativas vêm dos modelos internos do Kanboard.
- Datas e horas originais são guardadas em Start/Finish/Deadline e nas observações. Calendário MSPDI **24h por dia, 7 dias por semana** para evitar o deslocamento de prazos de fim de semana.
- Somente prazo ou somente início: **marco**; ambas ausentes: cartão marcado como **sem datas — não planejado**, com âncora exclusivamente técnica.
- Relações pai/filho viram EAP; cada pai com prazo recebe um **marco auxiliar**. Cartões-pai podem mostrar duração agregada diferente do seu próprio prazo original.
- Subtarefas nativas sem datas são exportadas explicitamente como não planejadas, com duração zero; o ProjectLibre pode desenhá-las como losangos por limitação visual.
- Relações de duplicação são ignoradas como dependências. Conflitos nos dados originais são apontados, não corrigidos silenciosamente.
- O campo `Duration` contém ajuste de **1 minuto por dia completo** validado com **ProjectLibre 1.9.8 Linux**; **isso não é duração estimada do Kanboard** e poderá ser interpretado diferentemente por outros aplicativos. O OPS passou no teste de importação, mas ainda requer validação independente de precisão de minutos nos campos internos e análise de caminho crítico.

## Testes offline

```bash
php tests/smoke.php
python3 tests/verify_xml.py
OUT="$(mktemp -d)"
php tests/test_matrix.php "$OUT"
python3 tests/verify_matrix.py "$OUT"
python3 tests/verify_plugin_rename.py
```

Teste opcional com snapshot de referência local, sem incluí-lo no repositório:

```bash
php tests/export_from_kexport_json.php /caminho/snapshot.json /tmp/kanboard-cronograma.xml
python3 tests/verify_source_snapshot.py /caminho/snapshot.json /tmp/kanboard-cronograma.xml
```

Veja **`MIGRACAO.md`** e **`HOMOLOGACAO-OPS.md`**.

**Versão estável publicada:** 0.4.2; versão 0.5.0 em desenvolvimento; homologada em testes funcionais na homelab. **Estado:** validação independente em laboratório com Kanboard v1.2.57 e ZIP da Release v0.2.0-rc1 concluída (cenários A–F, exportação pelo controller HTTP e verificações MSPDI); importação OPS, hierarquia e persistência também homologadas. Equivalência completa de CPM e precisão de minutos internos no OPS permanecem fora do escopo homologado. O plugin antigo precisa ser substituído, **não instalado em paralelo**.


## Correção v0.4.1

A conversão nativa **subtarefa → tarefa** agora captura o planejamento antes de excluir a subtarefa original: copia `planned_start` para `date_started` e `planned_end` para `date_due`, sem inventar datas quando não há planejamento. Preserva as horas nativas e registra os parâmetros de jornada/dias úteis nos metadados da nova tarefa para rastreabilidade. Conversões malsucedidas não devem remover a subtarefa original. A transferência foi verificada em testes automatizados e funcionais na homelab; outras configurações de fuso horário e plugins que substituam o mesmo serviço de conversão requerem validação específica.
