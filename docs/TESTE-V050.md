# Homologação da v0.5.0 no laboratório Kanboard 1.2.50

Esta versão está em desenvolvimento. Testar somente no ambiente isolado do notebook.
A versão estável v0.4.2 e a PR do catálogo oficial não devem ser modificadas.

## Preparação
1. Faça backup do banco SQLite e do diretório do plugin atual.
2. Instale a branch `feature/v0.5.0-i18n-planned-subtask-creation` em `plugins/ScheduleExport` no laboratório; não altere o diretório do homelab.
3. Verifique na página Plugins: **Kanboard Schedule Export**, versão `0.5.0`, mínimo `>=1.2.50`.

## Idiomas
- [ ] Em Kanboard English, rótulos do plugin em inglês: **Subtask planning**, **Planning**, **Dates**, **Export project schedule (.xml)**.
- [ ] Em Português brasileiro, rótulos correspondentes em português.
- [ ] Alternar idioma não modifica datas, período ou outros dados.

## Cadastro
- [ ] No formulário nativo **Add a sub-task**, aparece o link **Create one sub-task with planning**. O cadastro em lote original continua funcionando.
- [ ] Acessar o link e criar uma única subtarefa sem selecionar **Plan this sub-task now**. Ela existe, sem planejamento.
- [ ] Criar uma única subtarefa com período (início, término, jornada em minutos e dias úteis). Fechar e reabrir o cartão; o link **Dates** mostra o plano correto.
- [ ] Tentar término anterior ao início, data inválida, jornada fora de 1–1440 e nenhum dia selecionado: rejeitar antes de criar a subtarefa.
- [ ] Criar duas subtarefas pelo formulário nativo em lote: ambas sem planejamento automático, sem perda de dados.
- [ ] Verificar permissões para membro do projeto e proteção CSRF.

## Regressões
- [ ] Editar e remover o plano pelo link **Dates**.
- [ ] Converter subtarefa planejada em cartão e conferir transferência das datas.
- [ ] Exportar XML MSPDI e conferir subtarefas planejadas, início/fim, EAP e conteúdo.
- [ ] Conferir no navegador/console e logs que não existem erros PHP.

## Escopo técnico
- O novo fluxo utiliza uma tela separada de criação individual, acionada pelo formulário nativo. Não substitui o cadastro em lote.
- Identificadores de subtarefas são retornados pelo modelo nativo. Data inválida não deve produzir registro parcial.
- CI cobre criação e rollback em SQLite, PHP 8.1–8.4 e tradução estática.
- Banco MySQL/PostgreSQL, plugins que interceptem o mesmo cadastro e filas assíncronas requerem validação adicional, caso usados.

Não publicar a release v0.5.0 ou alterar o catálogo até concluir a homologação.
