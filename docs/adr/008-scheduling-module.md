# ADR-008: Módulo de Agendamentos

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

O agendamento de visitas é o componente central de conversão da plataforma. É o ponto onde o visitante interessado se transforma em lead qualificado da imobiliária.

A análise do funil imobiliário tradicional revela que o tempo entre o "momento de interesse" e o primeiro contato humano é crítico. Cada hora de demora reduz a taxa de conversão em ~20%. A maioria das imobiliárias hoje:

- Recebe interesse via WhatsApp/telefone (canal humano)
- Demora horas ou dias para responder
- Perde leads pela demora
- Não tem visibilidade do funil

A plataforma resolve isso permitindo agendamento self-service em qualquer horário, sem depender da disponibilidade do corretor para responder. Esse é um dos principais valores que o produto entrega.

Decidir o escopo do módulo no MVP exige equilibrar:

- **Fluxo simples e direto** (não pode ter fricção)
- **Qualificação mínima do lead** (corretor precisa de informação suficiente)
- **Gestão de disponibilidade** (corretor não pode ser sobrecarregado)
- **Tempo de implementação** (não pode atrasar MVP)

---

## Decisão

Implementar fluxo completo de agendamento self-service com gestão de disponibilidade por corretor, confirmação manual e notificações automáticas.

### Detalhamento

```
Fluxo do Cliente Final
─────────────────────────────────────────

1. Cliente acessa página do imóvel
       │
       ▼
2. Clica em "Agendar visita"
       │
       ▼
3. Sistema verifica autenticação:
   ├── Logado? → segue para passo 4
   └── Não → modal de cadastro/login
       │
       ▼
4. Calendário mostra horários disponíveis
       │
       ├── Baseado nos horários do corretor
       ├── Excluindo horários já ocupados
       └── Próximos 30 dias
       │
       ▼
5. Cliente escolhe data e horário
       │
       ▼
6. Confirma com observação opcional
       │
       ▼
7. Recebe e-mail de confirmação
       │
       ▼
8. Aguarda corretor confirmar (status: pendente)
```

```
Fluxo do Corretor
─────────────────────────────────────────

1. Corretor recebe e-mail de novo agendamento
       │
       ▼
2. Acessa o painel admin
       │
       ▼
3. Vê o agendamento com:
   ├── Dados do cliente (nome, telefone, e-mail)
   ├── Imóvel solicitado
   ├── Data e horário sugeridos
   └── Observação do cliente
       │
       ▼
4. Toma ação:
   ├── Confirmar → cliente recebe e-mail
   ├── Sugerir novo horário → cliente recebe e-mail
   └── Cancelar → cliente recebe e-mail
```

```
Estrutura de Dados
─────────────────────────────────────────

Tabela: agendamentos
  ├── id (uuid)
  ├── tenant_id (uuid)
  ├── imovel_id (uuid)
  ├── corretor_id (uuid)
  ├── cliente_id (uuid)
  ├── data_visita (datetime)
  ├── duracao_minutos (int, default 60)
  ├── status (enum)
  │   ├── pendente
  │   ├── confirmado
  │   ├── reagendado
  │   ├── cancelado
  │   └── concluido
  ├── observacao_cliente (text)
  ├── observacao_corretor (text)
  ├── created_at, updated_at, deleted_at

Tabela: disponibilidades
  ├── id (uuid)
  ├── tenant_id (uuid)
  ├── corretor_id (uuid)
  ├── dia_semana (enum: 0-6)
  ├── horario_inicio (time)
  ├── horario_fim (time)
  └── ativo (boolean)

Tabela: bloqueios_agenda
  ├── id (uuid)
  ├── tenant_id (uuid)
  ├── corretor_id (uuid)
  ├── data_inicio (datetime)
  ├── data_fim (datetime)
  └── motivo (string)
```

```
Regras de Negócio
─────────────────────────────────────────

Disponibilidade:
  ├── Corretor configura horários por dia da semana
  ├── Pode bloquear datas específicas (férias)
  ├── Default: segunda a sexta, 9h-18h
  └── Granularidade: slots de 1h

Limites de agendamento:
  ├── Máximo 1 visita por imóvel por horário
  ├── Mínimo 24h de antecedência
  ├── Máximo 30 dias de antecedência
  └── Cliente pode ter múltiplas visitas em diferentes imóveis

Status visível ao cliente:
  ├── Pendente: aguardando confirmação
  ├── Confirmado: visita agendada
  ├── Reagendado: corretor sugeriu outro horário
  ├── Cancelado: visita cancelada
  └── Concluído: visita realizada (marca manual)

Cancelamento:
  ├── Cliente pode cancelar até 6h antes
  ├── Corretor pode cancelar a qualquer momento
  └── Ambos os lados são notificados
```

```
Funcionalidades FORA do MVP (v2)
─────────────────────────────────────────

❌ Integração com Google Calendar
❌ Reagendamento self-service pelo cliente
❌ Lembretes automáticos por SMS/WhatsApp
❌ Cancelamento com política de prazo automática
❌ Multiplos corretores no mesmo agendamento
❌ Visita virtual (videoconferência)
❌ Avaliação pós-visita
❌ Notas de visita (CRM básico)
```

---

## Justificativa

A escolha do escopo se justifica por:

1. **Resolve o problema central** — Captura de lead em qualquer horário sem depender do corretor
2. **Confirmação manual mantém qualidade** — Corretor decide se aceita o horário (não é automação cega)
3. **Disponibilidade configurável** — Cada corretor define seus horários
4. **Notificações garantem visibilidade** — Cliente e corretor sabem o status sempre
5. **Modelo simples no banco** — Permite evoluir para casos avançados

A confirmação manual (em vez de auto-confirm) foi escolhida porque:
- Imobiliárias preferem ter controle sobre quem agenda
- Permite filtrar leads claramente não qualificados
- Reduz no-show (corretor está ciente da visita)

---

## Alternativas Consideradas

### Alternativa A — Confirmação Automática

- **Descrição:** Visita confirmada automaticamente ao agendar.
- **Pontos fortes:** Menos fricção, conversão mais rápida.
- **Pontos fracos:** Corretor pode receber visitas inadequadas, sem controle.
- **Por que não foi escolhida:** Imobiliárias preferem controle. Pode ser opção de configuração na v2.

### Alternativa B — Sem Sistema de Agendamento (apenas WhatsApp)

- **Descrição:** Botão "Agendar via WhatsApp" levando para conversa.
- **Pontos fortes:** Implementação trivial, alinhado ao que imobiliárias já fazem.
- **Pontos fracos:** Não resolve o problema de tempo de resposta. Não captura dados estruturados.
- **Por que não foi escolhida:** Ferro de lança do produto. Sem isso, a plataforma é só vitrine.

### Alternativa C — Integração com Google Calendar Desde o MVP

- **Descrição:** Sincronizar agendamentos com Google Calendar do corretor.
- **Pontos fortes:** Corretor não precisa entrar no painel, vê tudo na agenda.
- **Pontos fracos:** OAuth complexo, edge cases de sincronização, tempo de dev.
- **Por que não foi escolhida:** Adiciona 1-2 semanas. Notificação por e-mail resolve no MVP.

### Alternativa D — Reagendamento Self-service pelo Cliente

- **Descrição:** Cliente pode mudar horário sem precisar pedir ao corretor.
- **Pontos fortes:** Reduz fricção, melhora UX.
- **Pontos fracos:** Edge cases de disponibilidade, conflitos.
- **Por que não foi escolhida:** Postergada para v2 quando padrão de uso estiver claro.

---

## Consequências

### Positivas

- Funcionalidade central da plataforma resolvida no MVP
- Cliente captura mesmo fora do horário comercial
- Corretor mantém controle sobre sua agenda
- Status visível reduz ansiedade do cliente
- Notificações automáticas garantem fluxo

### Negativas

- Confirmação manual adiciona delay (corretor pode demorar)
- Cliente sem reagendamento self-service precisa contatar corretor
- Sem integração com Google Calendar exige corretor checar painel
- No-show é problema real (sem lembretes automáticos)

### Riscos

- **Risco:** Corretor não confirmar visita rápido, cliente desistir
  - **Mitigação:** E-mail urgente ao corretor. Considerar SLA (24h para confirmar) com escalação para admin.

- **Risco:** Cliente fazer no-show e corretor perder tempo
  - **Mitigação:** Coletar dados de qualificação no agendamento. Considerar lembretes na v2.

- **Risco:** Disponibilidade complexa (múltiplos corretores) gerar conflitos
  - **Mitigação:** Limite de 1 visita por imóvel por horário. Validação no momento da escolha.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Taxa de no-show ultrapassar 30% (lembretes automáticos viram prioridade)
- Corretores reclamarem da falta de Google Calendar
- 3+ clientes solicitarem reagendamento self-service
- Volume justificar SLA automático de confirmação

---

## Referências

- ADRs relacionadas: `ADR-005` (Notifications), `ADR-007` (Admin Module), `ADR-009` (Auth Module)

---

## Notas de Implementação

- Calendário no front (vitrine) renderizado em React com componente acessível
- Cálculo de disponibilidade via Service: `AvailabilityService::getAvailableSlots($corretor, $data)`
- Eventos disparados em mudança de status:
  - `VisitScheduled` → notifica corretor
  - `VisitConfirmed` → notifica cliente
  - `VisitCancelled` → notifica ambos
- Tabela `agendamentos` com índice composto em `(tenant_id, corretor_id, data_visita)`
- Status final ("concluido") é manual — corretor marca após visita
- Considerar adicionar campo `lead_score` para qualificação (v2)
- E-mails de notificação devem incluir link direto para detalhes
