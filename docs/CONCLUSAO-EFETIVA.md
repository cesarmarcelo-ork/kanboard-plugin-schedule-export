# Conclusão efetiva de subtarefas — 0.6.1

A tabela mostra início estimado, fim estimado e **Conclusão efetiva**, com cabeçalhos sem quebra de linha. A conclusão exibe a data; o horário completo aparece ao passar o mouse e na tela de detalhes.

- Concluir uma subtarefa pelo Kanboard registra automaticamente a data e hora atuais.
- A sincronização cobre o alternador de status, criação/edição pelo modelo nativo (incluindo API) e `closeAll`, usado ao finalizar o cartão.
- Clicar na conclusão abre a tela para consultar o histórico e, para membros, corrigir a data/hora de uma subtarefa concluída.
- Reabrir a subtarefa limpa a data atual e preserva o histórico. Concluir novamente registra um novo evento.
- Registros antigos já concluídos aparecem como **Não registrada**. A migração não tenta adivinhar a data; um membro pode informá-la.
- Subtarefas ainda não concluídas exibem “—”. Se já tiveram uma conclusão, esse indicador abre o histórico.
- A edição manual usa o fuso horário ativo do Kanboard, rejeita datas futuras e verifica se outro usuário alterou o registro desde a abertura do formulário.
- Datas estimadas, horas gastas e estimadas não são modificadas pelo ajuste manual. O cronômetro mantém as regras nativas do Kanboard. Alterar status via API ou fechar um cartão não acrescenta regras de cronômetro além das que o núcleo já executa.
- Observadores podem consultar; membros podem ajustar; gravações exigem CSRF e validação da relação projeto/tarefa/subtarefa.

## Persistência e compatibilidade

Migração 2 cria tabelas próprias para data atual e histórico em SQLite, MySQL e PostgreSQL. Excluir a subtarefa exclui seus registros associados. O histórico não é uma trilha independente após exclusão.

Os serviços nativos `subtaskModel` e `subtaskStatusModel` são estendidos, delegando ao núcleo as operações existentes. Plugins que também substituem esses serviços precisam de teste de compatibilidade. Escritas SQL externas que ignoram os modelos não são interceptadas.

Na exportação MSPDI, `Finish` continua representando a data final estimada. A conclusão registrada é exportada separadamente em `ActualFinish`, com `PercentComplete` e `PercentWorkComplete` iguais a 100 e `RemainingDuration` zerada. Subtarefas antigas concluídas sem data efetiva recebem o percentual 100, mas não recebem uma data inventada.

Status e registro da conclusão usam a mesma transação; o alternador nativo inclui o cronômetro nessa transação. Falhas de gravação desfazem a operação. Eventos nativos continuam sendo encaminhados normalmente.

## Teste na homelab

Esta é uma versão de desenvolvimento na branch `feature/subtask-actual-completion`, sobre a melhoria das colunas de datas. Antes de instalar, faça backup do banco e da pasta do plugin. A primeira inicialização aplica a migração 2; para rollback completo, restaure banco e plugin juntos.

1. Em uma subtarefa de teste, configure início e fim estimados.
2. Mude de pendente para em andamento: confira o cronômetro nativo e a ausência de conclusão efetiva.
3. Conclua: confira a data atual, as horas registradas e o histórico.
4. Clique na conclusão e ajuste para uma data passada: confira que os planos e horas não mudaram.
5. Reabra: confira “—” e a preservação do histórico; conclua novamente.
6. Confira uma subtarefa antiga concluída: deve mostrar “Não registrada”.
7. Finalize um cartão com subtarefas pendentes e confirme as datas efetivas destas subtarefas.
8. Teste com observador: consulta permitida, formulário de alteração ausente.
9. Abra dois formulários e salve ambos: o segundo deve solicitar recarregar.
10. Confira as três colunas, o rastreamento de tempo e demais plugins na largura usada na homelab.

A suíte automatizada inclui ciclo de conclusão, migração SQLite, edição, validação, concorrência otimista, histórico, rollback e integração com os modelos/helpers reais do Kanboard 1.2.50 e 1.2.57. A integração usa um banco isolado e não acessa dados da homelab.
