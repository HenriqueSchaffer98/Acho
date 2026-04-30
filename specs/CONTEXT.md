# Contexto do Projeto: Acho
**Inicializado em:** 2026-04-26

## Propósito
SaaS multi-tenant white-label para imobiliárias brasileiras de pequeno e médio porte. Cada cliente recebe subdomínio próprio (ex: `primoimoveis.seuapp.com.br`) com vitrine pública e painel admin. **Cliente pagante:** imobiliária (B2B subscription). **Usuário final:** comprador/inquilino (gratuito).

## Stack
- **Backend:** Laravel 13 + PHP 8.3 — core da aplicação, MVC, Eloquent ORM
- **Vitrine pública:** Inertia.js + React 18 + TypeScript — SPA-like sem APIs separadas
- **Admin tenant:** Filament 3 — painel admin gerado (~80% automático)
- **Super Admin:** Filament 3 (panel separado) — operação da plataforma
- **Banco:** PostgreSQL 16 — Row-Level Security para multi-tenancy
- **Multi-tenancy:** stancl/tenancy — isolamento entre tenants
- **Cache/Queue:** Redis
- **Storage:** Cloudflare R2 (prod) / filesystem (local), via spatie/laravel-medialibrary
- **Email:** Resend
- **Pagamento:** Pagar.me
- **Auth:** JWT + Refresh Token (ADR-014)
- **Senhas:** Argon2id + Pepper (ADR-023)
- **DTOs:** spatie/laravel-data
- **Permissões:** spatie/laravel-permission
- **Feature Flags:** spatie/laravel-feature-flags
- **Testes:** Pest
- **Qualidade:** Larastan nível 8, Pint, ESLint, Prettier, Husky
- **Infra:** Hetzner CX21 + Laravel Forge + Neon Pro + Cloudflare (~$39/mês)

## Estrutura de Diretórios (planejada — projeto ainda sem código implementado)
- `app/Console/Commands/` — artisan commands
- `app/Events/` — eventos de domínio
- `app/Filament/Tenant/Resources/` — admin Filament por tenant
- `app/Filament/SuperAdmin/Resources/` — super admin Filament
- `app/Http/Controllers/Api/` — endpoints API
- `app/Http/Controllers/Auth/` — login, register
- `app/Http/Controllers/Public/` — vitrine pública
- `app/Http/Controllers/Webhook/` — webhooks externos (Pagar.me)
- `app/Http/Requests/` — Form Requests (validação + autorização)
- `app/Jobs/` — queue jobs
- `app/Listeners/` — event listeners
- `app/Models/` — Eloquent models (todos estendem BaseTenantModel)
- `app/Notifications/` — Laravel Notifications
- `app/Policies/` — authorization policies
- `app/Services/` — lógica de negócio (Auth/, Tenant/, Imovel/, Scheduling/, Payment/)
- `app/Data/` — DTOs Spatie
- `app/Support/` — helpers, traits
- `resources/js/Pages/` — páginas Inertia (Public/, Auth/, Cliente/)
- `resources/js/Components/` — componentes React (ui/, forms/, imoveis/, shared/)
- `resources/js/Hooks/` — custom hooks
- `resources/js/Types/` — TypeScript types
- `docs/adr/` — Architecture Decision Records (25 ADRs)
- `docs/conventions/` — padrões de implementação detalhados
- `docs/specs/` — specs de features (template e índice)
- `docs/vision/` — visão de produto e roadmap
- `specs/` — specs de trabalho em andamento (este framework BRY)

## Features Implementadas
> Projeto está na **fase de planejamento/documentação**. Código da aplicação ainda não foi escrito. As 25 ADRs e convenções estão finalizadas; o desenvolvimento do MVP está prestes a começar.

Módulos planejados para o MVP:
- Onboarding automatizado de imobiliárias (ADR-011)
- Auth com JWT + Refresh Token (ADR-014)
- Vitrine pública de imóveis (ADR-006)
- Admin tenant via Filament (ADR-007)
- Agendamento de visitas (ADR-008)
- Trial e planos (ADR-012)
- Gateway de pagamento Pagar.me (ADR-013)
- Super Admin (ADR-010)
- Notificações via email/Resend (ADR-005)

## Convenções e Padrões
- **Arquitetura:** Controller (thin) → FormRequest → Service → Model → DB
- **Sem Repository Pattern** — Eloquent já é repository (ADR-025)
- **DTOs obrigatórios** em inputs/outputs de Services críticos (Spatie Data)
- **Eventos para desacoplamento** de side-effects (notificações, auditoria, cache)
- **Policies para autorização**, não condicionais em controllers
- **Toda tabela de negócio:** uuid PK + tenant_id (uuid, NOT NULL, indexed) + timestamps + soft delete
- **Todo Model de negócio** estende `BaseTenantModel` (scope automático de tenant)
- **RLS habilitado** em todas as tabelas de negócio (defesa em profundidade)
- **UUIDs como PKs** — nunca auto-increment
- **Migrations imutáveis** após deploy; mudança = nova migration
- **TypeScript strict mode** no frontend — sem `any`
- **React Hook Form + Zod** para formulários
- **Tanstack Query** para estado de servidor
- **Branches:** `feature/{desc}` e `bugfix/{desc}` a partir de `main`
- **Commits:** Conventional Commits (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `chore:`)
- **PRs para `main`** com squash merge; sem push direto

## Arquivos Chave
- `CLAUDE.md` — ponto de entrada para Claude Code; regras críticas do projeto
- `docs/adr/README.md` — índice de todas as 25 ADRs
- `docs/adr/019-tech-stack.md` — stack completa e justificativas
- `docs/adr/025-project-patterns.md` — padrões de arquitetura e estrutura de pastas
- `docs/adr/001-database-strategy.md` — estratégia multi-tenant com RLS
- `docs/adr/014-authentication.md` — JWT + Refresh Token
- `docs/adr/023-password-cryptography.md` — Argon2id + Pepper
- `docs/vision/01-product-vision.md` — visão de produto e norte estratégico
- `docs/conventions/` — padrões detalhados por área (arquitetura, banco, frontend, testes...)
- `specs/CONTEXT.md` — este arquivo
