# ADR-016: Roteamento por Subdomínio

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A arquitetura multi-tenant white-label (definida em ADR-002) atribui um subdomínio único para cada imobiliária. O sistema precisa rotear requisições corretamente, identificando qual tenant cada request pertence.

A escolha de roteamento envolve:

- **DNS** — Como subdomínios resolvem para o servidor
- **SSL** — Como cobrir HTTPS para todos os subdomínios
- **Aplicação** — Como identificar tenant na requisição
- **Provisionamento** — Como ativar subdomínio para novo cliente
- **Performance** — Latência mínima de identificação

Sem automação, cada novo cliente exigiria intervenção manual no DNS e geração de certificado SSL — inviável para SaaS auto-serviço.

A arquitetura também precisa pensar adiante: na v2, alguns clientes vão querer **domínio próprio** (`www.primoimoveis.com.br`) em vez de subdomínio. A decisão atual deve permitir essa evolução sem refatoração.

---

## Decisão

Implementar roteamento via **Wildcard DNS + Wildcard SSL**, permitindo provisionamento instantâneo de subdomínios. Identificação de tenant via middleware que lê o header Host. Estrutura preparada para domínios customizados na v2.

### Detalhamento

```
Configuração de DNS
─────────────────────────────────────────

Domínio principal: seuapp.com.br

Registros DNS no Cloudflare:
  ├── A     seuapp.com.br        → IP do servidor
  ├── A     *.seuapp.com.br      → IP do servidor (wildcard)
  └── A     admin.seuapp.com.br  → IP do servidor (super admin)

Resultado:
  ├── primoimoveis.seuapp.com.br      → resolve
  ├── casanova.seuapp.com.br          → resolve
  ├── qualquercoisa.seuapp.com.br     → resolve
  └── Aplicação valida no middleware
      └── 404 se tenant não existe
```

```
SSL Configuration
─────────────────────────────────────────

Cloudflare Universal SSL (Free Plan):
  ├── Cobre wildcard *.seuapp.com.br
  ├── Renovação automática
  ├── Custo: $0
  └── Configuração: 1 clique no painel

Modo SSL configurado:
  ├── Full (strict) — exige cert válido na origem
  └── Backend usa Let's Encrypt automaticamente (Forge)

HTTPS forçado:
  └── HTTP → 301 redirect para HTTPS
  └── HSTS header em produção
```

```
Fluxo de Requisição
─────────────────────────────────────────

1. Cliente acessa primoimoveis.seuapp.com.br/imoveis
       │
       ▼
2. DNS resolve via wildcard (*.seuapp.com.br)
       │
       ▼
3. Cloudflare CDN/SSL faz proxy para origin
       │
       ▼
4. Servidor (Hetzner) recebe requisição com:
   ├── Host: primoimoveis.seuapp.com.br
   └── Proxy headers do Cloudflare
       │
       ▼
5. Nginx/Forge passa para Laravel
       │
       ▼
6. Middleware TenantResolver:
   ├── Extrai subdomínio do Host
   │   └── "primoimoveis"
   ├── Busca tenant no cache (Redis)
   ├── Cache miss → consulta banco
   ├── Tenant existe e ativo? continua
   ├── Tenant suspenso? retorna página específica
   └── Tenant não existe? retorna 404
       │
       ▼
7. Tenant injetado no contexto:
   ├── app('currentTenant') = Tenant model
   ├── DB::statement("SET app.tenant_id = ...")
   └── Logs marcados com tenant_id
       │
       ▼
8. Controller processa request com tenant carregado
```

```
Cache de Tenant
─────────────────────────────────────────

TTL: 60 segundos
Storage: Redis (in-memory)

Chave: "tenant:{slug}"
Valor: serialização do Tenant model

Por que TTL curto?
  └── Mudanças de status (suspensão, ativação) refletem
      em até 60s sem invalidação manual

Invalidação ativa:
  ├── Quando tenant é atualizado, chave é deletada
  ├── Quando tenant é suspenso, chave é deletada
  └── Próxima request cria entrada nova
```

```
Slugs Reservados
─────────────────────────────────────────

Lista bloqueada no cadastro:

Operacional:
  admin, app, api, www, mail, ftp, smtp, pop3,
  imap, dns, ns, mx, blog, help, support, docs

Marketing:
  landing, home, site

Técnicos:
  dev, staging, test, preview, qa, beta,
  cdn, assets, static, media, files

Marca:
  seuapp, noreply, contato, contact, oficial

Genéricos:
  about, privacy, terms, login, signup, register

Validação:
  ├── Lista mantida em config/reserved_slugs.php
  ├── Validação no Form Request de cadastro
  └── Atualizada periodicamente
```

```
Domínios Customizados (Preparação para v2)
─────────────────────────────────────────

Estrutura preparada desde o MVP:

Tabela: tenants
  ├── slug              ← obrigatório (subdomínio)
  ├── custom_domain     ← null por padrão
  └── domain_verified_at

Middleware preparado para ambos:
  ├── 1º: tenta encontrar tenant por slug do subdomínio
  ├── 2º: se Host não é subdomínio, tenta custom_domain
  └── 404 se nenhum match

Implementação real (v2):
  └── Cliente aponta CNAME do seu domínio para subdomínio
  └── Cloudflare for SaaS gerencia SSL para custom domain
  └── Sistema valida ownership via DNS TXT
  └── Após validação, custom_domain ativo
```

```
Cookies entre Subdomínios
─────────────────────────────────────────

CRÍTICO: cookies não devem vazar entre tenants

Configuração:
  ├── Domain: explícito do subdomínio
  │   └── primoimoveis.seuapp.com.br
  ├── NÃO usar Domain=.seuapp.com.br (vazaria)
  └── HttpOnly + Secure + SameSite=Lax

Resultado:
  ├── Cookie em primoimoveis.* não vai para casanova.*
  └── Sessões totalmente isoladas
```

```
CORS entre Subdomínios
─────────────────────────────────────────

Bloqueado por padrão.

Exceções específicas:
  └── admin.seuapp.com.br → primoimoveis.seuapp.com.br
      └── Necessário para "Login as"
      └── Whitelist explícita no backend
      └── Token CSRF obrigatório
```

---

## Justificativa

A escolha por Wildcard DNS + SSL se justifica por:

1. **Provisionamento instantâneo** — Novo tenant cadastra e está online
2. **Custo zero** — Cloudflare cobre wildcard SSL no plano free
3. **Sem trabalho manual** — Adicionar tenant não exige tocar DNS
4. **Performance ótima** — Identificação por header Host é instantânea
5. **Cache reduz consultas** — Redis com TTL 60s

A escolha do Cloudflare como camada de borda:
- DNS rápido e gerenciado
- Wildcard SSL automático
- CDN global gratuito
- DDoS protection inclusa
- WAF básico no plano free

A preparação para custom domain desde o MVP:
- Adicionar coluna `custom_domain` agora custa zero
- Refatorar depois custaria semanas
- Permite vender feature premium na v2 sem retrabalho

---

## Alternativas Consideradas

### Alternativa A — DNS Manual por Tenant

- **Descrição:** Adicionar registro DNS específico para cada subdomínio.
- **Pontos fortes:** Mais controle por tenant.
- **Pontos fracos:** Não escala. Cliente tem que esperar provisionamento.
- **Por que não foi escolhida:** Inviável para SaaS auto-serviço.

### Alternativa B — Subpastas em vez de Subdomínios

- **Descrição:** seuapp.com.br/primoimoveis/ em vez de subdomínio.
- **Pontos fortes:** Sem necessidade de wildcard, configuração mais simples.
- **Pontos fracos:** UX inferior (URL longa, marca diluída), SEO compartilhado.
- **Por que não foi escolhida:** Subdomínio é padrão de SaaS white-label moderno.

### Alternativa C — SSL Pago Tradicional

- **Descrição:** Comprar wildcard SSL de provedor pago.
- **Pontos fortes:** Marca própria, mais customização.
- **Pontos fracos:** $200-500/ano sem benefício real.
- **Por que não foi escolhida:** Cloudflare Universal SSL cobre 100% da necessidade gratuitamente.

### Alternativa D — Domínio Customizado Desde o MVP

- **Descrição:** Cada cliente usa seu próprio domínio desde o início.
- **Pontos fortes:** Marca 100% da imobiliária.
- **Pontos fracos:** Provisionamento de SSL custom é complexo. Cloudflare for SaaS é pago.
- **Por que não foi escolhida:** Postergada para v2. Subdomínio resolve 90% dos casos.

---

## Consequências

### Positivas

- Provisionamento de subdomínio em segundos
- Zero custo operacional de DNS/SSL
- CDN global gratuito incluso
- Estrutura preparada para evolução (custom domain)
- Cache reduz carga no banco

### Negativas

- Dependência do Cloudflare como camada crítica
- Tenant enumeration possível (ver "Riscos")
- Cookies precisam de cuidado para não vazar
- Custom domain exige trabalho adicional na v2

### Riscos

- **Risco:** Cloudflare ter outage afeta todos os tenants
  - **Mitigação:** Aceitar como trade-off. Cloudflare tem SLA alto. Plano de contingência: poder mudar DNS para outro provider em horas.

- **Risco:** Tenant Enumeration (atacante descobre tenants existentes)
  - **Mitigação:** Tempo constante de resposta (não vaza por timing). Página 404 idêntica para "não existe" e "suspenso". Rate limiting agressivo.

- **Risco:** Cache de tenant envenenado
  - **Mitigação:** TTL curto (60s). Invalidação explícita em mudanças. Logs de cache para auditoria.

- **Risco:** Cookie vazar entre subdomínios
  - **Mitigação:** Domain explícito do subdomínio (não wildcard). Testes de integração validam isolamento.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Demanda por custom domains crescer (3+ clientes solicitando)
- Cloudflare mudar termos do plano free
- Performance de cache de tenant se mostrar gargalo
- Surgir necessidade de roteamento mais complexo (geo-routing, A/B)

---

## Referências

- ADRs relacionadas: `ADR-002` (Tenancy Model), `ADR-001` (Database), `ADR-021` (Infrastructure)
- Cloudflare DNS: https://developers.cloudflare.com/dns/
- Cloudflare for SaaS (v2): https://developers.cloudflare.com/cloudflare-for-saas/

---

## Notas de Implementação

- Middleware `TenantResolver` em `app/Http/Middleware/`:
  - Aplicado em rotas de tenant (dentro do grupo)
  - Não aplicado em rotas de landing page e admin
- Cache key: `tenant:{slug}` com TTL 60s
- Service `TenantService::resolveBySlug()` centraliza lookup
- Service `TenantService::resolveByCustomDomain()` para v2
- Rotas estruturadas em `routes/tenant.php`:
  - Carregadas dentro do middleware group
  - Tenant disponível via `app('currentTenant')`
- Configuração Cloudflare:
  - Plano: Free
  - DNS: wildcard A para *.seuapp.com.br
  - SSL: Full (strict)
  - Always HTTPS: ativo
  - Auto Minify: HTML, CSS, JS
- Forge configurado para servir wildcard:
  - server_name: ~^(?<subdomain>[^.]+)\.seuapp\.com\.br$
  - Laravel processa subdomain via middleware
- Página 404 para tenant não encontrado:
  - "Imobiliária não encontrada"
  - Botão "Voltar para seuapp.com.br"
- Página de suspensão:
  - "Esta imobiliária está temporariamente indisponível"
  - Sem detalhes técnicos
