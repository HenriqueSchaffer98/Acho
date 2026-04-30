# Público-Alvo

## Cliente pagante: a imobiliária

### Persona principal — "Imobiliária Familiar Estabelecida"

**Perfil:**

- 1-15 corretores ativos
- 5-20 anos de mercado
- Atuação local ou regional (1-3 cidades)
- Carteira: 30-200 imóveis ativos típicos
- Faturamento: PME com margem apertada
- Decisor: dono(a) ou sócio(a) gestor(a)
- Cidade-tipo: capital de estado pequeno, interior de SP, RJ, MG, RS, SC, PR

**Dores reais:**

- Anúncios em portais externos (ZAP, OLX, VivaReal) custam caro e geram leads "ralo" que não convertem
- Não têm site próprio, ou têm site antigo e abandonado
- Gestão por WhatsApp + planilha Excel + caderno → perdem leads, não medem conversão
- Tempo de resposta a leads é o gargalo: cliente manda mensagem na sexta à noite, corretor só vê na segunda
- Querem "ter um Quinto Andar próprio" mas não têm tecnologia nem orçamento
- Receio de plataformas que viram concorrentes (caso real do mercado)

**Comportamento de compra:**

- Compram tecnologia com cautela e ceticismo
- Indicação por outras imobiliárias é o canal mais forte
- Resistem a investimentos sem ROI claro
- Preferem mensalidades pequenas a setup taxes grandes
- Querem conversar com humano antes de fechar (não 100% self-service na decisão)

**Como ouvem falar do produto:**

- Indicação direta de outra imobiliária
- LinkedIn / grupos de Facebook do setor
- Eventos regionais (CRECI estadual, sindicatos)
- Busca orgânica por termos como "site para imobiliária"
- Anúncios em mídia segmentada (depois do MVP validado)

---

### Persona secundária — "Corretor Solo Profissionalizando"

**Perfil:**

- Corretor autônomo com CRECI ativo
- Carteira pequena (10-30 imóveis próprios)
- Quer parecer maior do que é
- Tech-aware, usa Instagram e WhatsApp Business

**Dores reais:**

- Não tem capital para um site profissional
- Compete com imobiliárias maiores
- Quer construir marca pessoal digital

**Cabe no MVP?** Sim, no plano Gratuito ou Básico. Não é foco principal mas é cliente válido. Decisão consciente: produto serve essa persona sem features adicionais.

---

## Usuário final (gratuito): comprador / inquilino

### Persona principal — "Pessoa em Mudança"

**Perfil:**

- 25-55 anos
- Acessando via mobile (~70%)
- Buscando casa/apto para comprar ou alugar
- Pouco paciente: vai abandonar a página se demorar mais de 3s para carregar
- Ciclo de decisão: visita 5-15 imóveis antes de fechar

**Comportamento:**

- Pesquisa horários estranhos (noite, fim de semana)
- Quer agendar visita rapidamente quando encontra algo interessante
- Compara preços e fotos rapidamente
- Confia em marcas conhecidas localmente (a imobiliária, não a plataforma)

**O que valoriza:**

- Fotos boas e em quantidade
- Localização clara (mapa)
- Possibilidade de agendar visita sem precisar ligar
- Resposta rápida do corretor
- Site que funciona bem no celular

**O que ignora:**

- Marca da plataforma de software (white-label funciona)
- Detalhes técnicos do imóvel além do básico (área, quartos, banheiros)
- Recursos avançados como tour 360° (raros, não decisivos)

---

## Casos de uso principais

### CU-1: Captura de lead em horário não-comercial

Visitante acessa o subdomínio às 22h da sexta, encontra um imóvel interessante e agenda visita para sábado de manhã. Corretor recebe e-mail no sábado cedo, confirma rapidamente. Cliente teria sido perdido se dependesse de telefone.

### CU-2: Substituição de site velho ou inexistente

Imobiliária tem um site WordPress de 2012 que não atualiza desde 2018. Migra para a plataforma e ganha 30 imóveis cadastrados em uma tarde, com vitrine moderna funcionando.

### CU-3: Profissionalização da gestão de visitas

Imobiliária tinha gestão de visitas em WhatsApp + agenda de papel. Migra para a plataforma e passa a ter histórico, métricas, e visibilidade do funil de cada corretor.

### CU-4: Redução de fricção na qualificação de lead

Visitante interessado preenche dados básicos (nome, telefone, e-mail) ao agendar visita. Corretor recebe lead já estruturado, não precisa gastar 5 mensagens no WhatsApp coletando informação.

---

## Casos de uso fora do escopo

Para clareza:

- ❌ Compra/venda direta na plataforma (sem transação financeira de imóvel)
- ❌ Hospedagem de site institucional rico (blog, "sobre nós" elaborado, etc.)
- ❌ Anúncios pagos integrados (boost em redes sociais)
- ❌ Análise de crédito de inquilino
- ❌ Vistoria digital
- ❌ Gestão de contratos de locação ativos

---

## Quem NÃO é cliente

Tão importante quanto definir o cliente é definir quem **não é** foco:

- ❌ **Imobiliárias enterprise** (50+ corretores, múltiplas cidades) — nosso produto é simples demais
- ❌ **Construtoras / incorporadoras** — fluxo de vendas de empreendimento é diferente
- ❌ **Marketplaces** (concorrentes diretos) — não atendemos
- ❌ **Pessoas físicas vendendo imóvel próprio** — sem CNPJ, fora do modelo
- ❌ **Mercado internacional** — apenas Brasil no MVP
- ❌ **Mercado de luxo (>R$ 5MM)** — fluxo de venda é mais relacional, plataforma agrega menos

---

## Validação contínua

Estas personas são hipóteses. A POC (ADR-024) prevê conversa estruturada com 5-10 imobiliárias-alvo para validar:

- As dores descritas são reais e prioritárias?
- O ticket que estamos pensando faz sentido para esse perfil?
- A proposta de valor ressoa ou parece "mais um SaaS"?
- Quais features que estamos planejando ou tirando do MVP geram reação forte?

Personas devem ser revisadas após cada ciclo de validação.

---

## Referências

- ADRs relacionadas: `ADR-002` (Tenancy Model), `ADR-006` (Listing Module), `ADR-024` (POC Strategy)
- Documentos vision relacionados: `01-product-vision.md`, `02-business-model.md`
