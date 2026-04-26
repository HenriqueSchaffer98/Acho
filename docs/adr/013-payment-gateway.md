# ADR-013: Gateway de Pagamento

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A plataforma precisa de um gateway de pagamento para processar:

- Assinaturas mensais recorrentes das imobiliárias
- Mudanças de plano (upgrade/downgrade)
- Cancelamentos
- Cobrança de inadimplência
- Reembolsos (casos excepcionais)

A escolha do gateway impacta:

- Métodos de pagamento aceitos (cartão, PIX, boleto)
- Taxas cobradas por transação
- Facilidade de integração técnica
- Burocracia para conta brasileira
- Flexibilidade para evolução

A pesquisa de mercado avaliou principalmente Stripe e Pagar.me.

A confirmação atual é que **Stripe suporta PIX**, mas:

> Para empresas sediadas no Brasil, o PIX no Stripe ainda é apenas para convidados — ou seja, não está disponível abertamente para qualquer conta brasileira ainda. A integração foi viabilizada via parceria com EBANX em agosto de 2025.

Isso impacta a decisão para uma empresa brasileira recém-criada.

---

## Decisão

Adotar **Pagar.me** como gateway de pagamento principal no MVP, com webhook automatizando suspensão e ativação de tenants conforme status de pagamento.

### Detalhamento

```
Capacidades do Pagar.me Aproveitadas
─────────────────────────────────────────

Métodos de Pagamento:
  ├── PIX (nativo, sem restrição)
  ├── Cartão de crédito (recorrente)
  ├── Boleto bancário
  └── Multi-pagamento (combinação)

Funcionalidades:
  ├── Assinaturas recorrentes
  ├── Webhooks de eventos
  ├── Dashboard administrativo
  ├── Retentativas automáticas
  └── Antifraude integrado

Taxas (referência, podem variar):
  ├── Cartão: ~2.99%
  ├── PIX: ~1% (mais barato que cartão)
  ├── Boleto: ~R$ 3,49 + 1.99%
  └── Sem mensalidade
```

```
Fluxo de Cobrança
─────────────────────────────────────────

Upgrade do Plano Gratuito:
  1. Cliente escolhe plano pago
  2. Sistema redireciona para checkout
  3. Cliente preenche dados de pagamento
  4. Pagar.me cria assinatura
  5. Pagamento processado
  6. Webhook recebido pelo backend
  7. Sistema ativa novo plano no tenant
  8. E-mail de confirmação

Renovação Mensal:
  1. Pagar.me cobra automaticamente no dia
  2. Sucesso → webhook ativa próximo ciclo
  3. Falha → retentativas automáticas (3 vezes em 7 dias)
  4. Falha definitiva → webhook avisa o sistema
  5. Sistema marca tenant como inadimplente

Inadimplência:
  ├── Dia 1: Falha de cobrança
  │   └── E-mail ao cliente: "Houve um problema com sua cobrança"
  ├── Dia 3: Segunda tentativa
  ├── Dia 7: Terceira tentativa
  ├── Dia 8: Tenant suspenso
  │   └── Subdomínio mostra página de "regularizar pagamento"
  │   └── Acesso ao painel bloqueado
  │   └── Dados preservados
  └── Dia 30: Tenant marcado para arquivamento
      └── Aviso por e-mail
      └── Possibilidade de exportar dados

Reativação:
  └── Cliente paga via link no e-mail
  └── Webhook reativa tenant automaticamente
```

```
Webhooks Configurados
─────────────────────────────────────────

Eventos críticos do Pagar.me:
  ├── subscription.created       → Ativa plano
  ├── subscription.canceled      → Cancela plano (vai pra gratuito)
  ├── subscription.charged       → Renova ciclo
  ├── subscription.payment_failed → Marca como inadimplente
  ├── transaction.paid           → Confirma pagamento avulso
  └── transaction.refunded       → Reverte ativação

Segurança do Webhook:
  ├── Assinatura HMAC verificada
  ├── Idempotência (não processa mesmo evento 2x)
  ├── Logs de todos os eventos recebidos
  └── Retry pelo Pagar.me se webhook falhar
```

```
Estrutura no Banco
─────────────────────────────────────────

Tabela: subscriptions
  ├── id (uuid)
  ├── tenant_id (uuid)
  ├── plan_id (uuid)
  ├── pagarme_subscription_id (string)
  ├── status (active, paused, canceled, suspended)
  ├── current_period_start (timestamp)
  ├── current_period_end (timestamp)
  ├── trial_ends_at (timestamp, null se não trial)
  └── created_at, updated_at

Tabela: payments
  ├── id (uuid)
  ├── tenant_id (uuid)
  ├── subscription_id (uuid)
  ├── pagarme_transaction_id (string)
  ├── amount_cents (int)
  ├── method (pix, credit_card, boleto)
  ├── status (paid, failed, refunded)
  ├── paid_at (timestamp, nullable)
  └── created_at

Tabela: webhook_events
  ├── id (uuid)
  ├── source (pagarme)
  ├── event_type (string)
  ├── payload (jsonb)
  ├── processed (boolean)
  ├── processed_at (timestamp, nullable)
  └── created_at
```

---

## Justificativa

A escolha pelo Pagar.me se justifica por:

1. **PIX nativo sem restrição** — Empresas brasileiras conseguem usar imediatamente
2. **Métodos de pagamento brasileiros** — Boleto e PIX são essenciais para o mercado
3. **Conta brasileira sem burocracia** — Stripe exige processo mais complexo para empresas BR
4. **API e SDK PHP maduros** — Integração rápida com Laravel
5. **Webhooks confiáveis** — Permite automação completa
6. **Taxas competitivas** — PIX a 1% reduz custo conforme volume cresce

A escolha de NÃO usar Stripe se justifica:
- PIX restrito para empresas BR é bloqueador
- Onboarding na Stripe para empresa BR é mais burocrático
- Mercado brasileiro espera PIX e boleto
- Vantagens internacionais da Stripe não são relevantes no MVP

---

## Alternativas Consideradas

### Alternativa A — Stripe

- **Descrição:** Gateway global líder de mercado.
- **Pontos fortes:** Melhor API do mercado, documentação excelente, recursos avançados.
- **Pontos fracos:** PIX restrito para BR, conta BR mais burocrática, sem boleto.
- **Por que não foi escolhida:** Limitações específicas do mercado BR não compensam a qualidade da plataforma.

### Alternativa B — MercadoPago

- **Descrição:** Gateway popular no LATAM.
- **Pontos fortes:** Marca conhecida, aceita todos os métodos.
- **Pontos fracos:** API menos limpa que Pagar.me, dashboard menos profissional.
- **Por que não foi escolhida:** Pagar.me oferece melhor experiência técnica para SaaS.

### Alternativa C — Cobrança Manual (Boleto Avulso)

- **Descrição:** Você emite boleto manualmente, marca como pago.
- **Pontos fortes:** Zero integração técnica, custo zero.
- **Pontos fracos:** Não escala. Inviável com 5+ clientes.
- **Por que não foi escolhida:** Bootstrap real precisa automação. Custo de gateway é menor que tempo manual.

### Alternativa D — Iugu

- **Descrição:** Gateway brasileiro focado em SaaS.
- **Pontos fortes:** Foco em recorrência, dashboard bom.
- **Pontos fracos:** Comunidade menor que Pagar.me, menos integrações.
- **Por que não foi escolhida:** Pagar.me tem ecossistema mais maduro.

---

## Consequências

### Positivas

- Cobrança totalmente automatizada
- PIX disponível desde o dia 1 (importante no BR)
- Boleto cobre clientes que preferem
- Webhook automatiza ativação/suspensão
- Antifraude protege contra cobranças indevidas
- Taxas competitivas (PIX a 1%)

### Negativas

- Lock-in com gateway específico (migração futura é trabalho)
- Dependência de webhook confiável
- Burocracia inicial para configurar conta empresarial
- Reembolsos manuais (sem automação total)

### Riscos

- **Risco:** Webhook do Pagar.me cair em momento crítico
  - **Mitigação:** Idempotência + retry pelo Pagar.me. Job de reconciliação diária verifica inconsistências.

- **Risco:** Cliente fraudulento gerar chargebacks
  - **Mitigação:** Antifraude do Pagar.me ativo. Validação de CNPJ no cadastro reduz risco.

- **Risco:** Mudança de termos/taxas pelo Pagar.me
  - **Mitigação:** Acompanhar comunicações. Camada de abstração permite trocar gateway com esforço médio.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Stripe abrir PIX para empresas BR sem restrição
- Volume justificar negociação de taxas (>R$ 100k MRR)
- Surgir necessidade de aceitar moedas estrangeiras (internacionalização)
- Pagar.me mudar termos significativamente

---

## Referências

- ADRs relacionadas: `ADR-012` (Trial and Plans), `ADR-022` (Security)
- Documentação Pagar.me: https://docs.pagar.me
- SDK PHP: pagarme/pagarme-php

---

## Notas de Implementação

- SDK Pagar.me em `composer.json`: `pagarme/pagarme-php`
- Service `PaymentService` abstrai chamadas ao gateway
- Endpoint webhook: `POST /api/webhooks/pagarme`
  - Verificação de assinatura HMAC
  - Tabela `webhook_events` registra todos eventos
  - Processamento assíncrono via Queue
- Configuração em `.env`:
  - `PAGARME_API_KEY` (público, para JS)
  - `PAGARME_API_SECRET` (privado, para backend)
  - `PAGARME_WEBHOOK_SECRET` (para validar assinatura)
- Ambiente de sandbox vs produção via env
- Job `ReconcileSubscriptionsJob` (roda diariamente):
  - Verifica todas as assinaturas ativas
  - Compara status no banco vs Pagar.me
  - Loga inconsistências
  - Notifica admin se houver discrepância
- Service `SuspendTenantService`:
  - Marca tenant como suspenso
  - Subdomínio retorna página de regularização
  - Mantém dados intactos
- Service `ReactivateTenantService`:
  - Reativa após pagamento confirmado
  - Restaura acesso completo
- E-mails automáticos em todos os eventos críticos
- Página `/billing` no painel admin:
  - Status atual da assinatura
  - Próxima cobrança
  - Histórico de pagamentos
  - Botão para alterar plano
  - Botão para atualizar método de pagamento
