# Modelo de Negócio

## Como geramos receita

**Modelo:** SaaS B2B com assinatura mensal recorrente.

**Cliente pagante:** imobiliária (não cliente final).

**Receita por cliente:** previsível e proporcional ao uso (planos com limites de imóveis e corretores).

**Sem take rate:** não cobramos comissão sobre transações imobiliárias. A imobiliária paga por usar a plataforma, não por vender imóveis através dela.

---

## Estrutura de planos

> Nota: preços específicos são estimativas iniciais e devem ser validados com clientes reais. A estrutura de tiers é mais estável que os valores absolutos.

### Plano Trial (14 dias, gratuito)

- Acesso completo ao produto
- Sem cartão de crédito necessário
- Imóveis e corretores ilimitados durante o trial
- Banner indicando dias restantes

### Plano Gratuito (pós-trial, sem upgrade)

- Limite: 3 imóveis ativos
- Limite: 1 corretor
- Funcionalidades essenciais
- Banner permanente de upgrade
- (Cliente nunca perde acesso, apenas usa com limitações)

### Plano Básico

- Limite: 30 imóveis ativos
- Limite: 3 corretores
- Todas as features do MVP
- Suporte por e-mail

### Plano Pro

- Limite: 100 imóveis ativos
- Limite: 10 corretores
- Todas as features
- Suporte prioritário (futuro)

### Plano Enterprise (futuro, sob consulta)

- Imóveis ilimitados
- Corretores ilimitados
- Customização avançada
- SLA dedicado
- Hospedagem dedicada (se justificar)

---

## Estratégia de pricing

**Filosofia:** pricing transparente, sem upsell agressivo, alinhado ao valor entregue.

**Princípios:**

1. **Trial sem cartão** — fricção zero no topo do funil. Conversão pós-trial vale mais que conversão de cadastro.
2. **Plano gratuito limitado, não suspenso** — cliente que volta sempre é mais valioso que cliente que sumiu.
3. **Limites claros e fáceis de entender** — número de imóveis e corretores. Sem features escondidas atrás de paywalls obscuros.
4. **Preço progressivo** — Básico → Pro tem margem maior, refletindo valor crescente.
5. **Sem cobranças surpresa** — exceder limite mostra modal de upgrade, não cobra automático.

**Ciclo de cobrança:** mensal apenas no MVP. Anual com desconto pode ser adicionado quando justificar (reduz churn mas adiciona complexidade).

**Forma de pagamento:** Pagar.me — cartão de crédito, PIX (~1% taxa) e boleto (ver ADR-013).

---

## Estrutura de custos

**Custos fixos** (mensais, ~$39 no MVP):

- Hospedagem (Hetzner CX21): ~$8
- Provisionamento (Laravel Forge): $12
- Banco de dados (Neon Pro): $19
- DNS/CDN/SSL (Cloudflare Free): $0
- Storage (Cloudflare R2 free tier): $0
- E-mail (Resend free tier): $0
- CI/CD (GitHub Actions free tier): $0

**Custos variáveis:**

- Pagar.me: taxa por transação (PIX 1%, cartão 2.99%)
- Storage R2: $0.015/GB acima de 10 GB free
- Resend: $20/mês acima de 3.000 e-mails

**Crescimento de custos:**

- Linear até ~50 tenants (sem mudanças de infra)
- Saltos pontuais nas transições de fase (ver ADR-021)
- Margem bruta esperada: >85% após primeiros 5 clientes pagantes

---

## Métricas-chave

### Aquisição (topo de funil)

- Visitantes únicos na landing page
- Taxa de conversão visitante → cadastro de trial
- Custo de aquisição (CAC), quando aplicável

### Ativação

- % de tenants que completam onboarding guiado (logo + 1 imóvel + 1 corretor)
- Tempo médio entre cadastro e primeiro imóvel publicado
- Tempo médio entre cadastro e primeira visita agendada

### Retenção e Conversão

- Taxa de conversão trial → plano pago
- Churn mensal (% de tenants pagantes que cancelam)
- LTV (Lifetime Value) médio por tenant

### Receita

- MRR (Monthly Recurring Revenue)
- ARPU (Average Revenue Per User) por plano
- MRR Growth Rate mês a mês

### Engajamento

- DAU/MAU dos admins de tenant
- Imóveis ativos por tenant (média)
- Visitas agendadas por tenant (média)

---

## Hipóteses de negócio a validar

**Hipótese 1: Imobiliárias pequenas pagariam pelo produto.**

- Como validar: 5+ imobiliárias topam pagar após trial
- Falsificação: <2 conversões em 20+ trials qualificados

**Hipótese 2: Trial sem cartão tem melhor ROI que com cartão.**

- Como validar: medir conversão e churn por 3 meses
- Falsificação: conversão <8% (testar com cartão obrigatório)

**Hipótese 3: 30 imóveis no Básico é suficiente para imobiliárias pequenas.**

- Como validar: feedback dos clientes nos primeiros meses
- Falsificação: 50%+ dos clientes do Básico exceder limite no primeiro mês

**Hipótese 4: Subdomínio (vs domínio próprio) é aceitável no MVP.**

- Como validar: <10% dos cadastros pedindo domínio próprio
- Falsificação: maioria dos prospects condicionar fechamento a custom domain

---

## O que NÃO está no modelo

Para clareza estratégica:

- ❌ Não cobramos comissão sobre transações imobiliárias (não queremos competir)
- ❌ Não vendemos leads para imobiliárias (sem marketplace cross-tenant)
- ❌ Não monetizamos cliente final (sem ads, sem premium para o consumidor)
- ❌ Não vendemos dados (LGPD + posicionamento)
- ❌ Não fazemos consultoria ou serviços profissionais no MVP

---

## Referências

- ADRs relacionadas: `ADR-012` (Trial and Plans), `ADR-013` (Payment Gateway), `ADR-021` (Infrastructure)
- Documentos vision relacionados: `01-product-vision.md`, `04-roadmap.md`
