# Visão de Produto

## O que estamos construindo

Uma plataforma SaaS multi-tenant white-label para imobiliárias brasileiras de pequeno e médio porte. Cada imobiliária cliente recebe um subdomínio dedicado (ex: `primoimoveis.seuapp.com.br`) com:

- Vitrine pública profissional, mobile-first, com SEO funcional
- Painel administrativo para gestão de imóveis, corretores e visitas
- Sistema de agendamento de visitas self-service para clientes finais
- Identidade visual personalizada (logo e cor primária)

A plataforma resolve o gargalo central do funil imobiliário digital: a perda de leads por demora na resposta entre o "momento de interesse" do visitante e o primeiro contato humano.

---

## Para quem

**Cliente pagante:** imobiliárias brasileiras de pequeno e médio porte (1-15 corretores, foco regional ou local) que:

- Não têm orçamento ou time para construir software próprio
- Atualmente usam combinação de portais externos (ZAP, OLX, VivaReal) + WhatsApp + planilhas
- Perdem leads por demora na resposta (ciclo manual e dependente do horário comercial)
- Querem ter sua própria marca digital sem competir com a plataforma

**Usuário final (gratuito):** comprador ou inquilino brasileiro buscando imóvel, que:

- Acessa o subdomínio da imobiliária diretamente ou via Google
- Quer agendar visita rapidamente, sem fricção
- Espera UX moderna em mobile (maioria dos acessos)
- Não precisa saber que existe uma plataforma por trás

---

## Por que existe

O mercado imobiliário brasileiro digital tem dois extremos:

1. **Marketplaces gigantes** (Quinto Andar, Loft) — competem diretamente com imobiliárias, capturando o relacionamento com cliente final
2. **Sites próprios manuais** — caros, técnicos, inflexíveis e raramente integrados

No meio, existe uma faixa enorme de imobiliárias pequenas e médias que precisam de presença digital profissional **sem perder a marca e o relacionamento direto com seus clientes**. Essa é a lacuna que o produto preenche.

A proposta de valor é clara: a imobiliária mantém sua marca, sua carteira e seu relacionamento — a plataforma fornece a tecnologia que ela não consegue construir sozinha.

---

## Norte estratégico

**Princípios de produto que guiam decisões:**

1. **A imobiliária é nosso cliente, não nosso concorrente** — nunca capturar leads para nós, sempre para elas
2. **Velocidade de resposta é o produto** — toda feature deve reduzir tempo entre interesse e contato humano
3. **Mobile-first não é opcional** — 70%+ dos acessos vêm de celular no setor
4. **Bootstrap minimal até validação real** — não construir features sem evidência de demanda
5. **Setor brasileiro tem peculiaridades** — PIX, CNPJ alfanumérico, LGPD são considerações de primeira classe, não secundárias

---

## O que NÃO somos

Para evitar drift de produto:

- ❌ Não somos marketplace agregador (não competimos com Quinto Andar)
- ❌ Não somos CRM completo (gestão de comissões e financeiro fica fora)
- ❌ Não somos site builder genérico (foco vertical em imobiliário)
- ❌ Não somos plataforma de assinatura digital (DocuSign não é parte do produto)
- ❌ Não somos solução enterprise (foco PME, no MVP)

---

## Métricas de sucesso

**Validação do MVP** (primeiros 6 meses pós-lançamento):

- 5+ imobiliárias pagantes ativas
- Trial → conversão >15%
- Churn mensal <8%
- NPS >40

**Tração** (12-18 meses pós-lançamento):

- 20+ imobiliárias pagantes
- MRR consistente e crescente
- Pelo menos 1 caso de imobiliária triplicando agendamentos via plataforma

---

## Visão de longo prazo (não MVP)

Quando atingir tração consolidada, expansão natural inclui:

- Domínios customizados (cliente usa próprio domínio em vez de subdomínio)
- Integração com portais externos (importar/exportar XML)
- WhatsApp Business API com automações
- Contrato digital integrado
- App mobile para corretores
- Marketplace opcional (cross-tenant) sem competir com clientes
- Expansão para Portugal (mercado similar, mesmo idioma)

Estas direções são possibilidades, não compromissos. A roadmap real é guiada por feedback de clientes pagantes.

---

## Referências

- ADRs relacionadas: `ADR-002` (Tenancy Model), `ADR-006` (Listing Module)
- Documentos vision relacionados: `02-business-model.md`, `03-target-audience.md`, `04-roadmap.md`
