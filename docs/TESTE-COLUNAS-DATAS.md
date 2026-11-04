# Colunas de datas — 0.5.1-dev

Revisão baseada na v0.5.0. Na tabela de subtarefas, substitui a coluna
Planejamento pelas colunas Data inicial estimada e Data final estimada.
As datas vêm do planejamento já gravado pelo Schedule Export e usam o formato
de data configurado no Kanboard. Os horários continuam preservados no formulário
e na exportação XML; a tabela apresenta apenas as datas.

Membros com permissão de salvar o planejamento podem clicar na data ou em
Definir para abrir o formulário existente do período. Usuários somente de leitura
veem as datas ou Não planejada, sem links de edição.

Não há migração do banco, alteração de rastreamento de tempo, de exportação ou
do formulário de criação introduzido na v0.5.0. A versão estável e seu ZIP
não são substituídos por esta branch de desenvolvimento.

## Conferência no ambiente de testes

1. Abrir um cartão com subtarefas planejadas: conferir as duas datas na tabela.
2. Abrir uma subtarefa sem período: clicar em Definir, salvar e conferir as datas.
3. Editar um período existente por qualquer uma das duas datas.
4. Remover o planejamento: ambas as células devem voltar a Definir.
5. Conferir um usuário somente de leitura: datas visíveis, sem links de edição.
6. Conferir criação com planejamento e exportação XML usando os mesmos dados.
7. Conferir legibilidade em tela estreita e preservação da coluna de tempo.

Outras colunas inseridas por plugins ou customizações independentes não são
removidas por esta revisão.
