# Roadmap

> Este roadmap é direcional, não um cronograma de compromisso. Datas dependem de validação real com clientes pagantes. As fases existem para ordenar prioridades e impedir scope creep.

---

## Fase 0 — POC (4 semanas)

**Objetivo:** validar viabilidade técnica e de mercado antes de investir 4-6 meses no MVP.

Detalhes completos em `ADR-024 (POC Strategy)`.

**Entregas técnicas:**

- Multi-tenancy com RLS funcionando localmente
- Cadastro automático de tenant + token de primeiro acesso
- Painel admin Filament com CRUD de imóveis e corretores
- Vitrine pública mínima
- Deploy em produção real (mesmo incompleto)

**Entregas de mercado:**

- 5-10 conversas estruturadas com imobiliárias-alvo
- 3+ imobiliárias dispostas a testar
- Decisão go/no-go para MVP

---

## Fase 1 — MVP (12-16 semanas após POC)

**Objetivo:** primeiro produto vendível, com clientes pagantes reais.

### Módulos core

**Onboarding e identidade**
- Cadastro automatizado com validação de CNPJ (numérico + alfanumérico)
- Token de primeiro acesso por e-mail
- Onboarding guiado pós-cadastro
- Configuração de identidade visual (logo + cor primária)
- Cadastro de bairros pela imobiliária

**Gestão (painel admin)**
- CRUD completo de imóveis (até 10 fotos cada)
- Gestão de corretores (convite, ativação, desativação)
- Configuração do tenant
- Dashboard com métricas básicas

**Vitrine pública**
- Listagem de imóveis com filtros
- Página individual com galeria, mapa, descrição
- Perfil público de corretor
- SEO básico (sitemap, Open Graph, URLs amigáveis)
- Mobile-first

**Agendamento**
- Calendário de disponibilidade por corretor
- Cliente final agenda visita self-service
- Confirmação manual pelo corretor
- Notificações por e-mail
- Status visíveis (pendente, confirmado, cancelado)

**Autenticação e segurança**
- E-mail + senha com Argon2id + Pepper
- JWT + Refresh Token rotacionado
- Recuperação de senha
- Validação contra senhas vazadas
- Rate limiting agressivo
- LGPD básico (política, termos, exclusão de conta)

**Pagamento**
- Trial 14 dias sem cartão
- Pagar.me integrado (PIX, cartão, boleto)
- Webhook para ativação/suspensão automática
- Página de gestão de assinatura

**Super Admin**
- Painel separado em `admin.seuapp.com.br`
- Gestão de tenants
- "Login as" para suporte
- Métricas globais (MRR, churn, etc.)

### Métricas de sucesso do MVP

- 5+ imobiliárias pagantes nos primeiros 6 meses
- Trial → conversão >15%
- Churn mensal <8%
- Pipeline de deploy estável (zero downtime > 30min em incidentes)

### O que está FORA do MVP

Lista consolidada (cada item tem ADR explicando por quê):

- ❌ Contrato digital (DocuSign/ClickSign) — ADR-004
- ❌ Login social (Google/Facebook/Apple) — ADR-009
- ❌ 2FA / Magic links — ADR-009
- ❌ WhatsApp Business API automático — ADR-005
- ❌ Domínio customizado — ADR-016
- ❌ Tour virtual 360°, comparador, favoritos — ADR-006
- ❌ Integração Google Calendar — ADR-008
- ❌ Importação XML de portais (ZAP/OLX) — ADR-007
- ❌ Relatórios e dashboards avançados — ADR-007
- ❌ App mobile nativo
- ❌ API pública para clientes integrarem

---

## Fase 2 — Tração (5-20 clientes pagantes)

**Objetivo:** consolidar produto com base de clientes ativos pagando.

**Mudanças de infra** (ADR-021):
- Servidor maior (Hetzner CX31)
- Banco escalado (Neon Scale)
- Staging permanente
- Monitoramento estruturado

**Features candidatas** (priorização guiada por feedback):

- Domínio customizado (cliente usa próprio domínio)
- Importação de XML de portais externos
- Reagendamento self-service pelo cliente
- Lembretes automáticos de visita (e-mail / SMS)
- Relatórios de funil (lead → visita → fechamento)
- Página de equipe (lista de corretores)
- Blog/conteúdo da imobiliária (CMS leve)
- Integração com Google Calendar

**Validação de mercado:**

- 20+ imobiliárias pagantes
- MRR consistente e crescente
- Pelo menos 1 case de sucesso documentado (imobiliária multiplicando agendamentos)

---

## Fase 3 — Escala (20+ clientes)

**Objetivo:** maturar produto e operação para escalar com qualidade.

**Mudanças de infra:**
- Load balancer + múltiplos servidores
- Read replica do banco
- APM e tracing distribuído
- On-call rotation (se time crescer)

**Features candidatas:**

- WhatsApp Business API integrado (notificações automáticas)
- Contrato digital (ClickSign ou ZapSign)
- App mobile para corretores
- 2FA opcional para admins
- API pública (REST ou GraphQL) para integrações
- White-label avançado (customização visual além de logo+cor)
- Sistema de comissões (módulo financeiro básico)
- Tour virtual 360° (integração com fornecedor)
- Marketplace opcional cross-tenant (sem competir com clientes)

---

## Backlog de longo prazo (sem ordem)

Ideias que valem registrar mas não priorizar agora:

- Expansão para Portugal
- Versão para corretores autônomos solo (plano específico)
- Integração com sistemas de gestão de condomínio
- Vistoria digital integrada
- Análise de crédito de inquilino
- Score de imóveis (precificação assistida)
- Recomendações personalizadas por visitante
- A/B testing nativo de listagens
- Multi-idioma (futuro distante)

---

## Princípios de priorização

Quando houver dúvida sobre o que priorizar:

1. **Receita > Engajamento > Aquisição** no MVP
2. **Retenção > Aquisição** após primeiros clientes
3. **Feature pedida por 3+ clientes pagantes** > feature pedida por prospects
4. **Reduzir churn** > adicionar features novas
5. **Estabilidade do core** > expansão lateral
6. **Validar antes de construir** — POC ou mock antes de feature de 4+ semanas

---

## Quando revisitar este roadmap

- Após conclusão da POC (decisão go/no-go)
- A cada 5 clientes pagantes adquiridos
- Quando atingir gatilhos de fase de infra (ADR-021)
- Quando feedback consistente apontar nova prioridade

Roadmap não é compromisso — é hipótese atual de prioridade. Atualize quando a realidade mudar.

---

## Referências

- ADRs relacionadas: todas, especialmente `ADR-024` (POC), `ADR-021` (Infra por fase)
- Documentos vision relacionados: `01-product-vision.md`, `02-business-model.md`, `03-target-audience.md`
