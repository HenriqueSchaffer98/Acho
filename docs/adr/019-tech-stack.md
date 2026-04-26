# ADR-019: Stack Tecnológica

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A escolha da stack tecnológica é uma das decisões mais impactantes do projeto. Define:

- Velocidade de desenvolvimento
- Custo de manutenção
- Capacidade de contratação futura
- Performance e custos operacionais
- Maturidade do ecossistema

Para SaaS multi-tenant brasileiro com solo founder, a escolha precisa equilibrar:

- **Produtividade** (entregar MVP em tempo razoável)
- **Maturidade** (frameworks/libs comprovados)
- **Documentação** (cobertura de casos de uso)
- **Comunidade** (suporte para problemas)
- **Familiaridade do dev** (não aprender tudo do zero)

A decisão considera o contexto de um desenvolvedor com experiência em Laravel/PHP e JavaScript/React.

---

## Decisão

Adotar stack pragmática focada em produtividade comprovada:

- **Backend:** Laravel 13 + PHP 8.3
- **Vitrine pública:** Inertia.js + React + TypeScript
- **Painel admin (tenant):** Filament 3
- **Painel super admin:** Filament 3 (panel separado)
- **Database:** PostgreSQL 16
- **Cache/Queue:** Redis
- **Storage:** Cloudflare R2 (prod) / filesystem (local)
- **E-mail:** Resend
- **Pagamento:** Pagar.me
- **DNS/CDN/SSL:** Cloudflare
- **Hosting:** Hetzner + Laravel Forge
- **Database hosting:** Neon Pro

### Detalhamento

```
Backend: Laravel 13 + PHP 8.3
─────────────────────────────────────────

Por que Laravel 13?
  ├── Framework MVC mais maduro do PHP
  ├── Documentação excepcional
  ├── Eloquent ORM (produtividade alta)
  ├── Ecossistema enorme (pacotes maduros)
  ├── Filament integra nativamente
  ├── Suporte LTS de 2 anos por release
  └── PHP 8.3: types fortes, performance, attributes

Versão: Laravel 13.x (mais recente)
PHP: 8.3 (estável e amplamente suportado)
```

```
Frontend Vitrine: Inertia.js + React + TypeScript
─────────────────────────────────────────

Por que Inertia.js?
  ├── SPA-like UX sem APIs separadas
  ├── Backend Laravel envia props direto para componente
  ├── Sem necessidade de OpenAPI/SDK
  ├── SSR opcional para SEO
  └── Stack unificada (um repo, um deploy)

Por que React + TypeScript?
  ├── React: ecossistema maior do mercado
  ├── TypeScript: catch errors em compile-time
  ├── Tailwind: estilização produtiva
  └── Vite: dev server super rápido

Bibliotecas chave:
  ├── @inertiajs/react
  ├── react@18
  ├── @tanstack/react-query
  ├── tailwindcss@3
  ├── lucide-react (ícones)
  ├── react-hook-form (formulários)
  └── zod (validação)
```

```
Painel Admin: Filament 3
─────────────────────────────────────────

Por que Filament?
  ├── 80% do CRUD gerado automaticamente
  ├── UI padronizada e profissional
  ├── Multi-panel nativo (tenant + super admin)
  ├── Integra com Spatie Permission
  ├── Resources, Pages, Widgets, Forms ricos
  └── Ativamente desenvolvido (v3 em 2024)

Trade-off conhecido:
  └── Stack diferente da vitrine (Livewire vs React)
  └── Aceito pelo ganho de produtividade

Tempo economizado: 6-8 semanas vs construir manualmente
```

```
Database: PostgreSQL 16
─────────────────────────────────────────

Por que PostgreSQL?
  ├── Row-Level Security (essencial para multi-tenancy)
  ├── JSONB nativo (flexibilidade onde precisa)
  ├── Performance excelente
  ├── Extensions ricas (pgcrypto, full-text search)
  ├── Suporte a UUIDs nativo
  └── Compatível com Neon (hosting moderno)

Versão: PostgreSQL 16
Hosting prod: Neon Pro
Hosting local: Docker Compose
```

```
Pacotes Laravel Críticos
─────────────────────────────────────────

stancl/tenancy
  └── Multi-tenancy com isolamento de banco

filament/filament
  └── Painel admin

spatie/laravel-permission
  └── Roles e permissões

spatie/laravel-medialibrary
  └── Upload e gestão de mídia

spatie/laravel-data
  └── DTOs tipados

spatie/laravel-feature-flags
  └── Feature flags por tenant

pagarme/pagarme-php
  └── SDK do gateway de pagamento

resend/resend-laravel
  └── Driver de e-mail

intervention/image
  └── Processamento de imagens (resize, WebP)

laravel/sanctum
  └── Autenticação API/SPA
```

```
Pacotes de Qualidade
─────────────────────────────────────────

pestphp/pest
  └── Testing framework (sucessor PHPUnit)

laravel/pint
  └── Formatação automática de PHP

larastan/larastan
  └── Análise estática (PHPStan para Laravel)
  └── Nível 8 (máximo)

barryvdh/laravel-debugbar
  └── Debug em desenvolvimento

barryvdh/laravel-ide-helper
  └── Autocomplete em IDEs
```

```
Frontend Tooling
─────────────────────────────────────────

Vite
  └── Build tool moderno e rápido

TypeScript
  └── Tipagem estática

ESLint + Prettier
  └── Lint e formatação

Husky + lint-staged
  └── Git hooks para qualidade

@tanstack/react-query
  └── Estado de servidor (cache, refetch)

react-hook-form + zod
  └── Formulários performáticos com validação

Tailwind CSS 3
  └── Utility-first CSS
```

```
Infraestrutura
─────────────────────────────────────────

Cloudflare
  ├── DNS (free)
  ├── SSL (free wildcard)
  ├── CDN (free)
  └── WAF básico (free)

Hetzner
  ├── VPS CX21: 2 vCPU, 4GB RAM, 40GB SSD
  ├── Custo: €7,55/mês
  └── Datacenter europeu (latência aceitável BR)

Laravel Forge
  ├── Provisionamento e deploy
  ├── Custo: $12/mês
  └── Worth it para evitar tocar Nginx/PHP-FPM

Neon
  ├── PostgreSQL serverless
  ├── Plano Pro: $19/mês
  ├── Branching para preview deployments
  └── Backup automático

Cloudflare R2
  ├── Object storage
  ├── 10 GB free
  ├── Egress gratuito
  └── Compatível com S3
```

```
Total de Custos de Stack (Produção)
─────────────────────────────────────────

Hetzner CX21:        ~$8/mês
Laravel Forge:       $12/mês
Neon Pro:           $19/mês
Cloudflare:         $0
Cloudflare R2:      ~$0 (free tier)
Resend:             $0 (free tier)
GitHub Actions:     $0 (free tier)
Pagar.me:           $0 (taxa por transação)
─────────────────────────────────────────
Total fixo:         ~$39/mês
```

---

## Justificativa

A escolha da stack se justifica por:

1. **Familiaridade com Laravel** — Aproveita conhecimento existente
2. **Filament reduz tempo de admin em 70%** — Crítico para entregar MVP
3. **Inertia.js elimina SPA complexity** — Sem APIs separadas, SDKs, etc.
4. **PostgreSQL + RLS é a base ideal para multi-tenancy** — Decisão estrutural
5. **Custo total previsível e baixo** — ~$39/mês total
6. **Stack mainstream** — Documentação abundante, contratação futura facilitada

Trade-offs conscientes:
- **Filament usa Livewire** (não React) — aceito pelo ganho de produtividade
- **Stack PHP** (não Node/Go) — Laravel é mais produtivo para o domínio
- **Hetzner Europa** (não AWS Brasil) — diferença de latência aceitável (~150ms)
- **Solo developer aprenderá Filament novo** — investimento de 1-2 semanas

---

## Alternativas Consideradas

### Alternativa A — Stack Node/TypeScript Full (Next.js + tRPC)

- **Descrição:** Next.js + tRPC + Prisma + PostgreSQL.
- **Pontos fortes:** Stack moderna, JS unificado.
- **Pontos fracos:** Sem equivalente ao Filament (admin demanda mais código).
- **Por que não foi escolhida:** Laravel + Filament é mais produtivo para multi-tenancy SaaS.

### Alternativa B — Ruby on Rails + Hotwire

- **Descrição:** Rails 8 com Hotwire (Turbo + Stimulus).
- **Pontos fortes:** Stack madura, produtividade comprovada (Basecamp).
- **Pontos fracos:** Sem familiaridade do dev, ecossistema BR menor.
- **Por que não foi escolhida:** Curva de aprendizado vs ganho marginal.

### Alternativa C — Go + React (Stack Performática)

- **Descrição:** Go backend + React frontend.
- **Pontos fortes:** Performance, deployment simples (binário).
- **Pontos fracos:** Sem framework batteries-included (mais código manual), curva alta.
- **Por que não foi escolhida:** Solo founder em MVP precisa produtividade, não performance otimizada.

### Alternativa D — Laravel + Vue 3 (em vez de React)

- **Descrição:** Mesma stack mas com Vue.
- **Pontos fortes:** Vue é mais simples que React, integra melhor com Laravel.
- **Pontos fracos:** Ecossistema React é maior, dev tem mais experiência com React.
- **Por que não foi escolhida:** React é a escolha mais segura para o longo prazo.

---

## Consequências

### Positivas

- Tempo de desenvolvimento reduzido drasticamente (Filament + Inertia)
- Stack mainstream com documentação abundante
- Custo de infraestrutura previsível e baixo
- Contratação futura facilitada (Laravel/React são mainstream)
- Type safety com TypeScript no front
- Multi-tenancy nativo via stancl/tenancy

### Negativas

- Stack mista (Livewire no admin, React na vitrine) — duas formas de pensar
- Lock-in com ecossistema Laravel
- PHP tem reputação inferior ao Node em algumas comunidades
- Filament tem curva de aprendizado inicial

### Riscos

- **Risco:** Filament limitar customizações específicas
  - **Mitigação:** Custom pages e custom components quando padrão não atende. Recurso bem documentado.

- **Risco:** Performance de PHP em escala alta
  - **Mitigação:** PHP 8.3 com OPcache + JIT é performático. Octane disponível se precisar mais.

- **Risco:** Latência de Hetzner Europa para Brasil
  - **Mitigação:** Cloudflare CDN cobre static assets. Migração para Hetzner US ou Vultr SP é viável quando justificar.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Performance se tornar gargalo real (medido, não percebido)
- Escala exigir migração para microserviços (improvável no MVP)
- Filament limitar funcionalidade crítica
- Latência impactar conversão (medir com clientes reais)

---

## Referências

- ADRs relacionadas: `ADR-007` (Admin Module), `ADR-014` (Authentication), `ADR-021` (Infrastructure)
- Laravel: https://laravel.com
- Filament: https://filamentphp.com
- Inertia.js: https://inertiajs.com

---

## Notas de Implementação

- `composer.json` com versão fixa de Laravel
- `.nvmrc` com versão de Node (20.x)
- `package.json` com versões pinadas dos pacotes críticos
- Setup de TypeScript estrito (`strict: true` no tsconfig)
- Larastan no nível 8 desde o dia 1
- ESLint config rigorosa (no-unused-vars, no-explicit-any)
- Configurar OPcache em produção (significativo ganho de performance)
- Configurar Redis como cache + queue + session driver
- Filament Resources em `app/Filament/Tenant/Resources/`
- Filament SuperAdmin em `app/Filament/SuperAdmin/`
- Componentes React em `resources/js/Components/`
- Pages Inertia em `resources/js/Pages/`
- Testes Pest em `tests/Feature/` e `tests/Unit/`
