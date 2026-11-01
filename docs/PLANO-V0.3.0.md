# Plano de desenvolvimento — v0.3.0 (experimental)

Base: `release/v0.2.0-stable`. **Não modificar a release ou a branch main durante os testes.**

## Invariantes de compatibilidade

1. Exportação MSPDI (XML) existente permanece disponível e é o comportamento padrão.
2. Kanboard continua sendo fonte de verdade; o plugin permanece somente leitura, sem token pessoal, serviços externos ou escrita no banco.
3. Preservar os UIDs dos cartões (ID Kanboard), hierarquia de cartões pai/filho, relações de bloqueio, calendário 7×24, marcos de prazo e regras de datas da v0.2.0.
4. Não criar datas ou duração para subtarefas nativas não agendadas; horas estimadas não se confundem com duração de calendário.
5. A extensão K-OrkFlowTech não é dependência. Funcionalidades de uma extensão não devem modificar o XML nem executar em duplicidade dentro do plugin.

## Modos de exportação previstos

- **Legado (padrão):** mesmo resultado da v0.2.0, incluindo subtarefas nativas nas observações do cartão.
- **EAP detalhada (opt-in):** subtarefas nativas podem aparecer como linhas subordinadas ao cartão no XML, com UID sintético livre de colisões, marcador de origem `kanboard-subtask:<task_id>:<subtask_id>`, identificação e estimativa de esforço. Sem datas próprias, devem ser explicitamente marcadas **não planejadas**, sem barra temporal fictícia.
- **Períodos (opt-in, futuro):** somente produzir barras quando existirem início e fim válidos, provenientes de campos autoritativos ou de um padrão de descrição **documentado e validado**. Nunca interpretar texto livre ambiguamente como datas.

### Decisões necessárias antes da implementação

- Um cartão pode ser simultaneamente pai de cartões filhos e conter subtarefas nativas. A ordem EAP deve preservar todos os descendentes e posicionar marcos auxiliares após a última linha descendente.
- Não atribuir a uma subtarefa o período de seu cartão pai; isso fabricaria precisão.
- Preservar o comportamento de tarefas-resumo, que alguns importadores recalculam automaticamente.
- Tratar data inválida, invertida, parcialmente preenchida e conflito de identidade como casos explícitos, sem correção silenciosa.
- Exportação detalhada deve ter seleção independente da extensão; nenhuma alteração no esquema do Kanboard.

## Matriz mínima de regressão antes de liberar

1. Executar todos os testes atuais da v0.2.0 (smoke, verify_xml, test_matrix, verify_matrix, verify_plugin_rename).
2. Comparar XML do modo legado contra a v0.2.0 com mesmos dados, timezone e âncora; diferença esperada: nenhuma.
3. Validar cartão simples, pai/filho, subtarefa nativa, pai com filhos e subtarefas simultâneos, prazos de fim de semana, marcos, cartões sem datas, relações bloqueia, relações duplicadas e erros de vínculo.
4. Garantir UID único e WBS/OutlineLevel consistente para todas as linhas, inclusive marcos auxiliares.
5. Abrir os arquivos no ProjectLibre 1.9.8, conferir datas/barras/níveis e reabrir após salvar. Repetir no OPS, distinguindo importação de fidelidade temporal.
6. Confirmar ausência de gravações no Kanboard e ausência de conflito funcional com a extensão K-OrkFlowTech.

## Critério de promoção

A v0.3.0 só poderá substituir a versão estável após testes automatizados e homologação manual. Até lá, manter v0.2.0 e possibilidade de rollback.
