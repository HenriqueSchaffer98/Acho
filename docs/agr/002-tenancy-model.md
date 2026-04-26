# ADR-002: Modelo de Negócio e Arquitetura de Tenancy

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

O projeto SaaS Imobiliário precisa definir seu modelo de negócio fundamental antes de qualquer decisão arquitetural. Existem dois modelos distintos no mercado imobiliário digital:

1. **White-label SaaS** — A plataforma vende ferramentas para imobiliárias, que mantêm sua própria marca e relacionamento com clientes.
2. **Marketplace** — A plataforma agrega imóveis de múltiplas imobiliárias e se torna o canal direto para o cliente final (modelo Quinto Andar).

Cada modelo demanda arquitetura, modelo de receita e estratégia de produto completamente diferentes. Esta decisão impacta:

- Quem é o cliente pagante
- Como cada imobiliária é exposta na plataforma
- Como usuários finais interagem com o sistema
- Modelo de receita e custos de aquisição
- Concorrência direta ou indireta com clientes

---

## Decisão

Adotar o modelo **White-label SaaS multi-tenant** com cobrança recorrente da imobiliária, onde cada cliente recebe um subdomínio dedicado e mantém relacionamento direto com seus próprios usuários finais.

### Detalhamento

```
Modelo de Negócio
─────────────────────────────────────────
Cliente pagante → Imobiliária (mensalidade)
Usuário final   → Comprador/inquilino (gratuito)
Receita         → Subscription mensal por tenant
Concorrência    → Não compete com a imobiliária
```

```
Arquitetura de Tenancy
─────────────────────────────────────────
Cada imobiliária recebe:
  └── Subdomínio dedicado
      ex: primoimoveis.seuapp.com.br

Subdomínio funciona como:
  └── Identificador do tenant
  └── Vitrine pública isolada
  └── Painel admin do tenant
  └── Contexto de autenticação
```

```
Isolamento Entre Tenants
─────────────────────────────────────────
Subdomínio identifica → tenant
Middleware injeta    → tenant_id
RLS no Postgres     → garante isolamento

Usuário criado em tenant A:
  └── NÃO existe no tenant B
  └── Mesmo e-mail pode existir nos dois
      (são contas distintas)
  └── Não há login compartilhado
```

```
Comportamento Esperado do Usuário Final
─────────────────────────────────────────
1. Visitante acessa primoimoveis.seuapp.com.br
2. Vê SOMENTE imóveis da Primo Imóveis
3. Cria conta para agendar visita
4. Conta vinculada ao tenant Primo Imóveis
5. Para usar outro tenant, cria nova conta
   (separação total)
```

---

## Justificativa

A escolha pelo modelo White-label se justifica por:

1. **Velocidade de entrega** — Não exige construir rede em ambos os lados (imobiliárias e clientes finais)
2. **Modelo de receita previsível** — Subscription mensal é mais previsível que take rate de transações
3. **Sem competição com clientes** — Imobiliária paga porque a plataforma a fortalece, não a substitui
4. **Aquisição mais simples** — Vendemos para empresas, não competimos por atenção do consumidor final
5. **Menor risco de rejeição** — Imobiliárias não temem perder clientes para a plataforma

O modelo Marketplace, embora tenha potencial de receita maior em escala, exige:
- Investimento massivo em marketing para consumidor final
- Operação dual (B2B e B2C simultaneamente)
- Concorrência direta com imobiliárias clientes (conflito)
- Tempo de validação muito maior

---

## Alternativas Consideradas

### Alternativa A — Marketplace (Modelo Quinto Andar)

- **Descrição:** Plataforma única agregando imóveis de todas as imobiliárias.
- **Pontos fortes:** Potencial de receita maior em escala, network effects, valor agregado para consumidor final.
- **Pontos fracos:** Investimento massivo necessário, conflito com imobiliárias, validação demorada, concorrência com gigantes.
- **Por que não foi escolhida:** Não é viável para bootstrap solo founder. Exige capital e tempo incompatíveis com o estágio atual.

### Alternativa B — White-label com Domínio Próprio Desde o Dia 1

- **Descrição:** Cada imobiliária usa seu próprio domínio (www.primoimoveis.com.br) apontando para a plataforma.
- **Pontos fortes:** Zero menção da plataforma para o consumidor final, marca 100% da imobiliária.
- **Pontos fracos:** Provisionamento de SSL para domínios customizados é complexo, exige pacote tipo "Cloudflare for SaaS".
- **Por que não foi escolhida:** Complexidade desnecessária no MVP. Subdomínio resolve 90% do problema com 10% do esforço.

### Alternativa C — Hybrid Marketplace + White-label

- **Descrição:** Cada tenant tem subdomínio próprio E imóveis aparecem em portal central.
- **Pontos fortes:** Permite testar ambos os modelos.
- **Pontos fracos:** Dobra a complexidade, dilui foco do produto.
- **Por que não foi escolhida:** No MVP, foco é fundamental. Pode evoluir para isso na v2 se justificar.

---

## Consequências

### Positivas

- Modelo de negócio claro e previsível desde o dia 1
- Cliente é a imobiliária — venda B2B mais escalável que B2C
- Arquitetura técnica fica mais simples (sem ranking de imóveis cross-tenant)
- Sem conflito de interesse com clientes pagantes
- Cada tenant pode ter identidade visual própria (logo, cores, layout)
- SEO e marketing de cada imobiliária são separados (não há "competição interna")

### Negativas

- Cada tenant precisa atrair seus próprios visitantes (sem efeito de rede)
- Sem economias de escala em marketing para consumidor final
- Crescimento limitado pelo crescimento das imobiliárias clientes
- Não há "brand awareness" para o consumidor final

### Riscos

- **Risco:** Imobiliária pode usar a plataforma e cancelar levando os dados
  - **Mitigação:** Termos contratuais, exportação de dados controlada, valor agregado pela plataforma supera trade-off

- **Risco:** Concorrentes podem oferecer marketplace e capturar mercado consumidor
  - **Mitigação:** Foco em servir bem as imobiliárias, considerar evolução para marketplace na v2 se justificar

- **Risco:** Receita limitada por preço médio cobrável de imobiliárias pequenas
  - **Mitigação:** Estrutura de planos com valor crescente conforme uso

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Receita atingir patamar que permita investir em marketplace (>R$ 100k MRR)
- Surgir pressão competitiva de marketplaces capturando clientes finais
- Imobiliárias clientes solicitarem participação em portal cross-tenant
- Claramente houver sinergia inexplorada entre tenants

---

## Referências

- ADRs relacionadas: `ADR-001` (Database), `ADR-016` (Subdomain Routing), `ADR-011` (Onboarding)
- Modelos de referência: Shopify (white-label e-commerce), Squarespace, Wix

---

## Notas de Implementação

- Subdomínio é tratado como identidade pública do tenant
- Tabela `tenants` deve ter coluna `slug` (subdomínio) único
- Lista de slugs reservados deve ser respeitada (admin, www, api, etc.)
- Coluna `custom_domain` deve existir desde o início (null por padrão), preparando para v2
- Toda comunicação por e-mail deve respeitar identidade do tenant (logo, nome)
