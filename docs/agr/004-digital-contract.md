# ADR-004: Contrato Digital Fora do MVP

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A integração de contratos digitais (DocuSign, ClickSign, ZapSign) foi considerada inicialmente como parte do escopo do MVP. A funcionalidade resolveria o fundo de funil de uma transação imobiliária — após a visita, a proposta e o contrato seriam assinados digitalmente na plataforma.

Antes de incluir no MVP, foi necessário avaliar:

- Onde está o maior gargalo de conversão hoje?
- Qual fase do funil mais beneficia da automação?
- O custo (financeiro e de tempo) compensa o valor agregado no estágio atual?

A análise do funil imobiliário típico revelou:

```
Topo do Funil    → Captura via vitrine          ← MVP cobre
Meio do Funil    → Agendamento de visita        ← MVP cobre
Fundo do Funil   → Proposta + Contrato          ← Avaliado aqui
```

Estatísticas relevantes:
- 80%+ dos leads se perdem entre topo e meio do funil (gaps de UX e tempo de resposta)
- Apenas ~20% dos leads chegam à fase de proposta
- Contrato digital adiciona valor real, mas para uma fração pequena dos leads

---

## Decisão

**Não incluir contrato digital no MVP.** O fundo de funil será resolvido inicialmente por meio de WhatsApp e e-mail (comunicação direta entre corretor e cliente), sem assinatura digital integrada.

### Detalhamento

```
Estratégia para o Fundo de Funil no MVP
─────────────────────────────────────────

1. Cliente realiza visita ao imóvel (agendada via plataforma)
2. Após interesse, comunicação migra para canais externos
3. Corretor envia proposta via WhatsApp ou e-mail
4. Negociação acontece fora da plataforma
5. Contrato é assinado por meio que a imobiliária já usa

A plataforma:
  ✅ Registra que houve visita
  ✅ Mantém histórico de interesse do cliente
  ✅ Marca status do imóvel (vendido/alugado) ao final
  ❌ Não interfere na negociação
  ❌ Não armazena documentos contratuais
```

### Visão Para v2

```
Quando incluir contrato digital (v2):
  └── Após validação do MVP com clientes reais
  └── Quando 3+ clientes solicitarem como feature
  └── Quando justificar investimento em integração

Provedores candidatos para v2:
  ├── ClickSign (foco BR, integração nacional)
  ├── ZapSign (preço acessível, BR)
  └── DocuSign (internacional, mais caro)

Funcionalidades previstas para v2:
  ├── Geração de proposta a partir de template
  ├── Envio para assinatura via plataforma
  ├── Acompanhamento de status de assinatura
  ├── Armazenamento de documentos assinados
  └── Histórico legal completo
```

---

## Justificativa

A decisão de adiar contrato digital se justifica por:

1. **Foco no maior gargalo** — Conversão de visitante em lead qualificado é o problema mais urgente
2. **Custo evitado** — DocuSign custa ~$25/mês + complexidade de integração (1–2 semanas de dev)
3. **Validação prévia necessária** — Sem clientes pagantes, não faz sentido investir em features de fundo de funil
4. **Imobiliárias já têm processos próprios** — Maioria já usa WhatsApp/e-mail e não vê isso como fricção
5. **MVP não é "produto completo"** — É a versão mínima viável para validar a hipótese central

A fase atual do projeto (0 clientes, validação) demanda foco na captura e qualificação de leads, não no fechamento do negócio.

---

## Alternativas Consideradas

### Alternativa A — Incluir DocuSign no MVP

- **Descrição:** Integrar DocuSign desde o lançamento.
- **Pontos fortes:** Produto "completo" desde o início, diferencial competitivo.
- **Pontos fracos:** ~$25/mês fixo + 1-2 semanas de dev, sem garantia de uso.
- **Por que não foi escolhida:** Investimento desproporcional para um problema secundário no estágio atual.

### Alternativa B — Solução Caseira (Aceite por E-mail com Hash)

- **Descrição:** Implementar aceite simples via e-mail com hash de confirmação.
- **Pontos fortes:** Custo zero, sem dependência externa.
- **Pontos fracos:** Validade jurídica questionável, retrabalho se evoluir para contrato real.
- **Por que não foi escolhida:** Não resolve o problema legal real e cria dívida técnica.

### Alternativa C — Incluir Provedor Brasileiro Mais Barato (ZapSign)

- **Descrição:** ZapSign tem plano mais acessível e foco no Brasil.
- **Pontos fortes:** Preço menor que DocuSign, integração mais simples.
- **Pontos fracos:** Ainda assim adiciona complexidade ao MVP.
- **Por que não foi escolhida:** O ponto não é o custo do provedor, é a prioridade do problema. Mesmo grátis, não justifica entrar no MVP.

---

## Consequências

### Positivas

- MVP mais enxuto e focado
- Tempo de desenvolvimento reduzido em 1-2 semanas
- Sem custo fixo de provedor de assinatura digital
- Imobiliárias podem usar processos já existentes
- Validação do produto não fica condicionada a feature secundária

### Negativas

- Sistema não cobre o ciclo completo da transação
- Imobiliárias podem ver isso como limitação ao avaliar a plataforma
- Concorrentes que oferecem podem usar como diferencial

### Riscos

- **Risco:** Cliente potencial recusar a plataforma por falta de contrato digital
  - **Mitigação:** Posicionar produto como "ferramenta de captação e gestão de visitas". Coletar feedback para priorizar v2.

- **Risco:** Imobiliárias usarem ferramenta concorrente em paralelo só pelo contrato
  - **Mitigação:** Aceitar como trade-off temporário. Adicionar v2 quando justificar.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- 3+ clientes pagantes solicitarem como feature crítica
- Concorrência usar ausência como argumento de venda recorrente
- MVP estiver validado e gerando receita estável
- Equipe tiver capacidade para investir 1-2 semanas em integração

---

## Referências

- ADRs relacionadas: `ADR-008` (Scheduling Module), `ADR-005` (Notifications)
- Provedores avaliados: DocuSign, ClickSign, ZapSign

---

## Notas de Implementação

- Página de imóvel deve ter botão de "Falar com corretor" via WhatsApp
- Sistema deve registrar status do imóvel (disponível, reservado, vendido)
- Transição de status pode ser feita manualmente pelo admin/corretor
- Não há necessidade de modelar entidade "Contrato" no MVP
- Quando v2 for implementada, considerar criar ADR-026 substituindo esta
