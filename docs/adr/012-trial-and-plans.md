# ADR-012: Trial e Estratégia de Planos

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Como SaaS, a plataforma precisa de uma estratégia clara de monetização que defina:

- Como o cliente experimenta o produto antes de pagar
- O que acontece quando o trial acaba
- Quais planos são oferecidos e seus limites
- Como o cliente faz upgrade

A decisão impacta diretamente:

- Taxa de conversão (trial → pagante)
- Churn rate (perda de clientes)
- ARPU (receita média por usuário)
- Percepção de valor pelo cliente
- Retenção a longo prazo

Existem várias abordagens possíveis para fim de trial:

1. **Suspensão total** — cliente perde acesso até pagar
2. **Downgrade para freemium** — mantém acesso com limites
3. **Cobrança automática** — cartão obrigatório, vira pagante
4. **Bloqueio gradual** — perde features ao longo do tempo

A escolha define o tom do relacionamento com o cliente.

---

## Decisão

Adotar trial de 14 dias gratuitos sem cartão de crédito, seguido de **downgrade automático para plano gratuito limitado** (não suspensão total). Cliente mantém acesso, mas com limites que incentivam upgrade.

### Detalhamento

```
Estrutura de Planos
─────────────────────────────────────────

PLANO TRIAL (14 dias, gratuito)
  ├── Limite imóveis: ilimitado
  ├── Limite corretores: ilimitado
  ├── Todas as features ativas
  ├── Branding "Trial" no painel
  └── Banner de tempo restante

PLANO GRATUITO (pós-trial, sem upgrade)
  ├── Limite imóveis: 3 ativos
  ├── Limite corretores: 1
  ├── Funcionalidades essenciais
  ├── Banner de upgrade visível
  └── Marca d'água "Powered by SeuApp" (consideração)

PLANO BÁSICO (R$ X/mês)
  ├── Limite imóveis: 30 ativos
  ├── Limite corretores: 3
  ├── Todas as features
  └── Sem marca d'água

PLANO PRO (R$ Y/mês)
  ├── Limite imóveis: 100 ativos
  ├── Limite corretores: 10
  ├── Todas as features
  └── Suporte prioritário (futuro)

PLANO ENTERPRISE (sob consulta)
  ├── Imóveis ilimitados
  ├── Corretores ilimitados
  ├── Customização avançada (futuro)
  └── SLA dedicado (futuro)

Nota: Preços específicos são definidos na ADR-013
      (Gateway de Pagamento) e podem ser ajustados
      conforme validação com clientes.
```

```
Comunicação Durante o Trial
─────────────────────────────────────────

Dia 0 — Cadastro
  └── E-mail: Boas-vindas + link de primeiro acesso
  └── No painel: Onboarding guiado

Dia 7 — Meio do Trial
  └── E-mail: "Como está sendo sua experiência?"
  └── Painel: Mostra valor entregue (ex: "X imóveis cadastrados")

Dia 13 — Véspera do Fim
  └── E-mail: "Seu trial termina amanhã"
  └── Painel: Banner urgente com CTA de upgrade

Dia 14 — Fim do Trial
  └── E-mail: "Seu trial terminou"
  └── Sistema: Downgrade automático para gratuito
  └── Painel: Modal explicativo + CTA upgrade
```

```
Comportamento ao Atingir Limite (Plano Gratuito)
─────────────────────────────────────────

Cliente tenta cadastrar 4º imóvel:
  └── Modal: "Você atingiu o limite de 3 imóveis ativos"
  └── Opções:
      ├── Pausar imóvel existente
      ├── Excluir imóvel existente
      └── Fazer upgrade
  └── CTA principal: "Ver planos"

Cliente tenta convidar 2º corretor:
  └── Mesma lógica acima

Imóveis "ativos":
  └── Status: disponível, reservado
  └── Pausados/vendidos não contam no limite
```

```
Lógica de Mudança de Plano
─────────────────────────────────────────

Upgrade (gratuito → pago):
  ├── Cliente escolhe plano
  ├── Pagar.me cria assinatura
  ├── Webhook ativa novo plano
  ├── Limites são atualizados
  └── Cliente recebe e-mail de confirmação

Downgrade (pago → gratuito):
  ├── Permitido apenas no fim do ciclo de cobrança
  ├── Se exceder novos limites:
  │   └── Imóveis acima do limite são pausados
  │   └── Corretores acima do limite são desativados
  └── Notificação clara do que será afetado

Cancelamento:
  ├── Mantém acesso até fim do ciclo pago
  ├── Vira plano gratuito após
  └── Dados preservados por 30 dias
```

---

## Justificativa

A escolha por downgrade (em vez de suspensão) se justifica por:

1. **Reduz fricção emocional** — Cliente que perde acesso fica frustrado
2. **Mantém engagement** — Cliente vê o produto, valor é reforçado
3. **Permite recuperação fácil** — Upgrade é só 1 clique
4. **Oportunidade de marketing** — Cliente continua "exposto" ao produto
5. **Seguindo padrão moderno** — Mailchimp, Notion, Slack fazem assim

A escolha de 14 dias se justifica:
- Padrão do mercado SaaS B2B
- Tempo suficiente para validar produto
- Curto o bastante para criar urgência
- Cobre 2 fins de semana (testes em horários variados)

A escolha de **3 imóveis** no plano gratuito:
- Permite que imobiliária pequena teste o produto
- Mas não consegue operar de verdade (incentiva upgrade)
- Sweet spot entre "muito restritivo" e "permissivo demais"

---

## Alternativas Consideradas

### Alternativa A — Suspensão Total Após Trial

- **Descrição:** Cliente perde acesso ao subdomínio até pagar.
- **Pontos fortes:** Pressão clara para conversão.
- **Pontos fracos:** Frustração, percepção negativa, churn pode aumentar.
- **Por que não foi escolhida:** Cliente que perde acesso geralmente não volta. Downgrade é mais inteligente.

### Alternativa B — Trial Estendido (30 dias)

- **Descrição:** Trial mais longo para testar.
- **Pontos fortes:** Mais tempo para o cliente avaliar.
- **Pontos fracos:** Aumenta tempo até receita. Reduz urgência.
- **Por que não foi escolhida:** 14 dias é equilibrado e padrão de mercado.

### Alternativa C — Cobrança Automática Pós-Trial

- **Descrição:** Cartão obrigatório upfront, cobrança automática no dia 15.
- **Pontos fortes:** Maior taxa de conversão pós-trial.
- **Pontos fracos:** Reduz cadastros (fricção upfront alta).
- **Por que não foi escolhida:** No MVP, conversão de topo é mais importante. Pode ser testado depois.

### Alternativa D — Plano Gratuito Permanente (Freemium Real)

- **Descrição:** Sem trial, plano gratuito desde sempre com limites.
- **Pontos fortes:** Reduz pressão, aumenta conversão de topo.
- **Pontos fracos:** Trial cria urgência que freemium não tem.
- **Por que não foi escolhida:** Trial + downgrade combina urgência inicial com retenção.

---

## Consequências

### Positivas

- Conversão de topo maximizada (sem cartão)
- Cliente nunca perde acesso completamente
- Modelo psicologicamente mais saudável
- Permite recuperação fácil de cliente "perdido"
- Banco de dados não cresce indefinidamente (limites do gratuito controlam)

### Negativas

- Cliente pode ficar no gratuito indefinidamente sem converter
- "Custo" de manter contas inativas no banco
- Mais lógica de validação de limites
- Mais cenários para testar (limites em todas as ações)

### Riscos

- **Risco:** Maior parte dos trials nunca converter
  - **Mitigação:** Onboarding guiado + comunicação ativa durante trial. Métricas de uso para identificar leads quentes.

- **Risco:** Clientes "burlarem" criando múltiplas contas gratuitas
  - **Mitigação:** CNPJ único por tenant impede isso. Detecção de e-mails relacionados.

- **Risco:** Banco crescer com contas inativas
  - **Mitigação:** Política de retenção: contas inativas por 12 meses são arquivadas. Apenas dados essenciais mantidos.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Taxa de conversão trial → pago for muito baixa (<10%)
- Custo de armazenamento de contas gratuitas se tornar relevante
- Concorrência mudar padrão de mercado (todos passam a exigir cartão)
- Métricas mostrarem que 30 dias converte significativamente mais

---

## Referências

- ADRs relacionadas: `ADR-011` (Onboarding), `ADR-013` (Payment Gateway)
- Modelos de referência: Mailchimp, Notion, Slack (downgrade patterns)

---

## Notas de Implementação

- Tabela `plans`:
  - id, name, slug, price_cents, max_imoveis, max_corretores, features (jsonb)
- Tabela `tenants` adiciona:
  - `plan_id` (referência ao plano atual)
  - `trial_ends_at` (timestamp)
  - `subscription_id` (referência Pagar.me, null se gratuito)
- Service `EnforcePlanLimits` valida antes de criar imóvel/corretor
- Job agendado: `EndExpiredTrialsJob` (roda diariamente)
  - Identifica trials que terminaram
  - Faz downgrade para plano gratuito
  - Pausa imóveis acima do limite
  - Desativa corretores acima do limite
  - Envia e-mail explicativo
- Eventos:
  - `TrialStarted`, `TrialEnding` (3 dias antes), `TrialEnded`
  - `PlanUpgraded`, `PlanDowngraded`, `PlanCancelled`
- Middleware verifica limites em rotas de criação:
  - 403 com mensagem clara se atingiu limite
- E-mails:
  - Dia 7 (meio): mostra uso até o momento
  - Dia 13 (véspera): banner urgente
  - Dia 14 (fim): explicação do downgrade
- Banner no painel mostra plano atual + uso (X de Y imóveis usados)
