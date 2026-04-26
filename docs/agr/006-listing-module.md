# ADR-006: Módulo Vitrine Pública

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A vitrine pública é o componente mais visível do produto e o principal ponto de conversão. É a interface que o consumidor final (potencial comprador/inquilino) vê ao acessar o subdomínio de uma imobiliária.

A qualidade da vitrine impacta diretamente:

- Taxa de conversão de visitante em lead
- Posicionamento em buscas (SEO)
- Percepção de profissionalismo da imobiliária
- Tempo médio na página
- Taxa de agendamento de visitas

Decidir o escopo da vitrine no MVP exige equilibrar:

- **Funcionalidades essenciais** que viabilizam conversão
- **Funcionalidades secundárias** que podem ficar para v2
- **Complexidade técnica** vs tempo de entrega
- **Padrões esperados** pelo mercado (mapas, filtros, mobile)

---

## Decisão

A vitrine pública do MVP terá os seguintes componentes essenciais:

### Detalhamento

```
Funcionalidades Incluídas no MVP
─────────────────────────────────────────

1. Listagem de Imóveis
   ├── Cards com foto principal, título, preço
   ├── Características rápidas (quartos, banheiros, área)
   ├── Localização (cidade e bairro)
   └── Paginação

2. Filtros
   ├── Tipo (venda / aluguel)
   ├── Faixa de preço
   ├── Número de quartos
   ├── Bairro (lista cadastrada pela imobiliária)
   └── Cidade (somente se imobiliária atua em 2+ cidades)

3. Página Individual do Imóvel
   ├── Galeria de fotos (até 10)
   ├── Descrição completa
   ├── Características detalhadas
   ├── Mapa interativo (Google Maps Embed)
   ├── Perfil do corretor responsável
   ├── Botão "Agendar visita"
   ├── Botão "Falar com corretor" (WhatsApp)
   ├── URL amigável e compartilhável
   └── SEO: title, description, Open Graph

4. Mapa Interativo
   ├── Provider: Google Maps Embed
   ├── Free tier: 28.000 loads/mês
   └── Mostra localização aproximada do imóvel

5. Identidade Visual por Tenant
   ├── Logo da imobiliária no cabeçalho
   ├── Cor primária aplicada em CTAs e links
   ├── Nome da imobiliária no footer
   └── Dados de contato configuráveis

6. Perfil do Corretor
   ├── Nome e foto
   ├── Telefone (WhatsApp)
   ├── Imóveis sob sua responsabilidade
   └── Página acessível publicamente

7. SEO Básico
   ├── URLs amigáveis (/imoveis/casa-3-quartos-jardim-america)
   ├── Title tags otimizados por imóvel
   ├── Meta description única por imóvel
   ├── Open Graph para compartilhamento
   └── Sitemap.xml gerado dinamicamente
```

```
Funcionalidades FORA do MVP (v2)
─────────────────────────────────────────

❌ Tour virtual 360°
❌ Comparador de imóveis
❌ Chat ao vivo
❌ Salvar imóveis favoritos
❌ Histórico de visualizações
❌ Alertas por novos imóveis
❌ Mapa com múltiplos imóveis (busca por região)
❌ Avaliações e comentários
❌ Cadastro como inquilino com perfil completo
```

### Lógica de Filtros Inteligentes

```
Cidade e Bairro - Comportamento Adaptativo
─────────────────────────────────────────

Cenário 1: Imobiliária local (1 cidade)
  └── Filtro de cidade NÃO é exibido
  └── Filtro de bairro é o principal
  └── Bairros cadastrados pela própria imobiliária

Cenário 2: Imobiliária regional (2+ cidades)
  └── Filtro de cidade É exibido
  └── Bairros aparecem após selecionar cidade
  └── Cidades vêm da API IBGE (autocomplete)

Lista de bairros é cadastrada por tenant:
  └── Admin gerencia lista de bairros do seu mercado
  └── Garante consistência (sem "Centro" vs "centro")
  └── Reflete realidade local (nem IBGE tem bairros)
```

### Mobile-First

```
Vitrine pública é mobile-first:
  ├── Maioria dos acessos vem de celular
  ├── Layout otimizado para tela pequena
  ├── Imagens otimizadas via CDN
  ├── Touch targets adequados (>44px)
  └── Performance: < 3s para LCP em 4G
```

---

## Justificativa

A escolha do escopo se justifica por:

1. **Foco em conversão** — Cada funcionalidade tem ligação direta com gerar leads
2. **Padrão esperado pelo mercado** — Mapa, filtros e galeria são "table stakes" hoje
3. **SEO desde o dia 1** — Vitrine sem SEO não aparece no Google, perde leads orgânicos
4. **Identidade por tenant** — Reforça posicionamento white-label
5. **Mobile-first** — Maioria do tráfego é mobile no setor imobiliário

Funcionalidades fora do MVP foram escolhidas por:
- Complexidade alta vs valor incremental
- Requererem volume de dados/uso para fazer sentido
- Poderem ser adicionadas sem refatoração

---

## Alternativas Consideradas

### Alternativa A — MVP Sem Mapa Interativo

- **Descrição:** Apenas endereço em texto, sem mapa.
- **Pontos fortes:** Implementação mais simples, sem dependência externa.
- **Pontos fracos:** Mapa virou commodidade, ausência transmite falta de profissionalismo.
- **Por que não foi escolhida:** Google Maps Embed é gratuito até 28k loads/mês. Vale incluir.

### Alternativa B — Lista de Bairros Padronizada (Base Externa)

- **Descrição:** Usar API externa para padronizar bairros nacionalmente.
- **Pontos fortes:** Consistência total, sem trabalho da imobiliária.
- **Pontos fracos:** Não existe base oficial confiável de bairros no Brasil.
- **Por que não foi escolhida:** Bairros cadastrados pela imobiliária refletem o mercado local melhor.

### Alternativa C — SEO Postergado para v2

- **Descrição:** Focar no funcional, otimizar SEO depois.
- **Pontos fortes:** Tempo de entrega menor.
- **Pontos fracos:** Imóveis sem SEO não geram tráfego orgânico, dependem 100% de marketing pago.
- **Por que não foi escolhida:** SEO básico é barato de implementar e essencial para conversão.

---

## Consequências

### Positivas

- Vitrine competitiva com padrões de mercado desde o lançamento
- SEO funcional gera tráfego orgânico desde o dia 1
- Filtros inteligentes se adaptam ao contexto de cada imobiliária
- Mobile-first prioriza maior parte dos visitantes
- Identidade visual por tenant reforça white-label

### Negativas

- Sem features avançadas (favoritos, comparador, alertas)
- Mapa simples sem visualização de múltiplos imóveis
- Sem cadastro como inquilino com perfil rico (apenas conta básica)
- Galeria de fotos limitada a 10 imagens

### Riscos

- **Risco:** SEO ineficiente impedir tráfego orgânico
  - **Mitigação:** Implementar sitemap.xml, robots.txt, structured data (Schema.org). Validar com Google Search Console.

- **Risco:** Free tier do Google Maps acabar em escala
  - **Mitigação:** Monitorar uso. Migrar para Mapbox ou Leaflet se necessário.

- **Risco:** Imobiliária ter dezenas de bairros e UX ficar complexa
  - **Mitigação:** Filtros com autocomplete e busca textual.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Concorrência oferecer features (favoritos, comparador) como diferencial relevante
- 3+ clientes solicitarem feature específica como crítica
- Volume de tráfego justificar busca avançada cross-imóveis
- Surgir necessidade de tour virtual 360° por demanda do mercado

---

## Referências

- ADRs relacionadas: `ADR-007` (Admin Module), `ADR-008` (Scheduling Module), `ADR-015` (Image Storage)
- API IBGE: https://servicodados.ibge.gov.br/api/docs/localidades
- Google Maps Embed: https://developers.google.com/maps/documentation/embed

---

## Notas de Implementação

- URL de imóvel: `/imoveis/{slug}` onde slug é gerado a partir do título + características
- Implementar `<picture>` com WebP para imagens otimizadas
- Lazy loading em imagens fora da viewport
- SEO via meta tags + Open Graph (compartilhamento social)
- Sitemap.xml regenerado quando imóvel é publicado/despublicado
- Página individual do imóvel deve carregar em < 2s no mobile
- Mapa com lazy loading (carrega só quando visível)
- Cor primária do tenant aplicada via CSS variable
