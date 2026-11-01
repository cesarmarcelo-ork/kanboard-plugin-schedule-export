# Kanboard Schedule Export — v0.2.0

Plugin **somente leitura** para exportar projetos Kanboard em **Microsoft Project XML (MSPDI)**. O formato pode ser importado por vários planejadores. Testado com **ProjectLibre Desktop no Linux**; o **Open Planner Studio v2026.43.0** passou nos testes de importação, hierarquia EAP, estimativas individuais nas descrições e persistência após salvar e reabrir. O nome do plugin não significa que todos os importadores estejam homologados.

## Funcionamento

1. Instalar em `plugins/ScheduleExport` (veja `MIGRACAO.md` para substituir o antigo `ProjectLibreExport`).
2. Abrir o projeto autorizado no Kanboard.
3. No menu do projeto, selecionar **Exportar cronograma (.xml)**.
4. Abrir o arquivo `kanboard-ID-cronograma.xml` no aplicativo compatível.

O plugin não exige token pessoal/API no navegador, não grava no banco de dados, não chama serviços externos e não modifica cartões.

## Fidelidade dos dados e limitações

- O **Kanboard é a fonte de dados**: `date_started`, `date_due` e estimativas vêm dos modelos internos do Kanboard.
- Datas e horas originais são guardadas em Start/Finish/Deadline e nas observações. Calendário MSPDI **24h por dia, 7 dias por semana** para evitar o deslocamento de prazos de fim de semana.
- Somente prazo ou somente início: **marco**; ambas ausentes: cartão marcado como **sem datas — não planejado**, com âncora exclusivamente técnica.
- Relações pai/filho viram EAP; cada pai com prazo recebe um **marco auxiliar**. Cartões-pai podem mostrar duração agregada diferente do seu próprio prazo original.
- Subtarefas nativas sem datas são preservadas nas observações; **não** são transformadas em barras fictícias.
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

**Versão:** 0.2.0 estável. **Estado:** validação independente em laboratório com Kanboard v1.2.57 e ZIP da Release v0.2.0-rc1 concluída (cenários A–F, exportação pelo controller HTTP e verificações MSPDI); importação OPS, hierarquia e persistência também homologadas. Equivalência completa de CPM e precisão de minutos internos no OPS permanecem fora do escopo homologado. O plugin antigo precisa ser substituído, **não instalado em paralelo**.
