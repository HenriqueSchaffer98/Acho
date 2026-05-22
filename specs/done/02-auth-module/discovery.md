# Discovery: Módulo de Autenticação
**Data:** 2026-05-08

## Problema

Implementar o módulo de autenticação da plataforma — JWT Access Token + Refresh Token rotacionado para os 3 contextos: vitrine pública, subdomínio do tenant (admin/corretor/cliente) e super admin. Cobre login, logout, recuperação de senha, convite de corretor e cadastro de cliente final.

Referências: ADR-009 (escopo funcional) e ADR-014 (estratégia técnica).

## Usuários e Benefício

Quatro perfis de usuário que precisam se autenticar na plataforma:

- **Admin do tenant** — acessa o painel Filament da imobiliária
- **Corretor** — acessa o painel como colaborador da imobiliária
- **Cliente final** — se cadastra e faz login na vitrine pública para agendar visitas
- **Super Admin** — acessa o painel operacional da plataforma

Cada perfil tem TTL de sessão ajustado ao seu contexto de uso e nível de sensibilidade.

## Critério de Sucesso

- Login com e-mail + senha funcional nos 3 contextos (vitrine, admin tenant, super admin)
- JWT gerado com payload correto: `user_id`, `tenant_id`, `role`
- Refresh Token rotacionando — token antigo invalidado após cada uso
- TTLs corretos por perfil: Admin 8h / Corretor 12h / Cliente 30d / Super Admin 4h
- Lock automático após 5 tentativas falhas por 15min
- Recuperação de senha: link 1h, uso único, invalida todas as sessões do usuário
- Convite de corretor: JWT 48h, corretor define senha no primeiro acesso
- Logout revoga refresh token no banco
- Suite de testes cobrindo happy path + edge cases de cada fluxo
- Larastan nível 8 + Pint passando

## Fora do Escopo

- Login social (Google, Facebook, Apple)
- Magic links (login sem senha)
- 2FA (autenticação de dois fatores)
- SSO para clientes enterprise
- Login com biometria (mobile)
- Recuperação por SMS
- Lembrar dispositivo (sessão estendida confiável)
- Cadastro de imobiliária (delegado ao ADR-011 — Onboarding)

## Riscos e Dependências

### Dependências

- Etapa 02 (multi-tenancy infra) mergeada em `dev` — ✅ concluído
- `firebase/php-jwt` — Sanctum não serve para este design (tokens opacos, sem claims customizados)
- ADR-023 (Argon2id + Pepper) para validação de senha no login
- ADR-022 (segurança transversal) — rate limiting, headers, logs de auth
- Migration de ajuste na tabela `users`: remover `unique(email)` global, adicionar `unique(email, tenant_id)`
- `SetTenantContext` middleware precisa ser atualizado para aceitar `tenant_id` do JWT além do subdomínio

### Riscos

- **Race condition no refresh** (múltiplas requests simultâneas) → frontend implementa refresh queue
- **Vazamento de `APP_KEY`** compromete todos os JWTs → APP_KEY em vault, rotação periódica
- **Refresh Token roubado** → mitigado pela rotação (para de funcionar quando o legítimo refresca)
- **Brute force em login** → rate limiting 5 tentativas / 15min + Cloudflare WAF
- **Token de convite/reset interceptado** → TTL curto + HTTPS + uso único

### Restrições Técnicas

- Inertia.js + JWT: interceptor de refresh no Axios precisa ser implementado antes das requisições Inertia retentarem após 401
- Cookie `SameSite: Lax` com `Domain` explícito por subdomínio — isolamento intencional entre tenants (não usa wildcard)
