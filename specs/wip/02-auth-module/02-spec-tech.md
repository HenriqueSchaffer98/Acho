# Spec Técnica: Módulo de Autenticação
**Versão:** 1.1
**Status:** implemented
**Spec funcional:** specs/wip/02-auth-module/01-spec-functional.md

## Decisões de Arquitetura

- Padrão `Controller (thin) → FormRequest → Service → Model`, conforme ADR-025
- `TokenService` centraliza toda geração, validação e revogação de JWT — nenhum controller toca JWT diretamente
- Middleware `AuthenticateJWT` protege rotas autenticadas e injeta o usuário resolvido no request
- `SetTenantContext` (já existente) será atualizado para aceitar `tenant_id` do payload JWT quando o usuário estiver autenticado, além da resolução por subdomínio
- Eventos para desacoplar side-effects: notificação de IP novo, e-mails de confirmação
- Rate limiting via `Laravel RateLimiter` com Redis como driver
- Família de tokens: coluna `family_id` (uuid) em `refresh_tokens` para permitir revogação em cascata em caso de replay attack

## Stack e Dependências

**Backend (Composer):**
- `firebase/php-jwt` — geração e validação de JWT com claims customizados (`tenant_id`, `role`, `purpose`); Sanctum descartado por usar tokens opacos sem claims
- `inertiajs/inertia-laravel` — server-side do Inertia (renderiza páginas React via Blade root)
- `spatie/laravel-data` — DTOs para inputs/outputs dos Services (já instalado)
- `spatie/laravel-permission` — gestão de roles (já instalado)
- Laravel `Password::defaults()` — política de senha com `uncompromised()` (HaveIBeenPwned)
- Laravel `RateLimiter` + Redis — lock de tentativas de login
- Mailpit em dev / Resend em prod via Laravel Notifications — e-mails de auth (ADR-005)

**Frontend (npm):**
- `@inertiajs/react` + `react` 18 + `react-dom` — SPA via Inertia
- `react-hook-form` + `zod` + `@hookform/resolvers` — formulários tipados com validação client
- `@tanstack/react-query` — provider registrado para futuras queries (não usado direto nesta etapa, mas obrigatório por CLAUDE.md)
- `axios` — cliente HTTP com interceptor 401 + fila single-flight de refresh
- `tailwindcss` v4 — estilo utility-first
- `vite` + `@vitejs/plugin-react` + `laravel-vite-plugin` — bundler e HMR

## Estrutura de Arquivos

**Migrations:**
```
database/migrations/
  YYYY_MM_DD_000001_create_refresh_tokens_table.php
  YYYY_MM_DD_000002_update_users_table_email_tenant_unique.php
```

**Models:**
```
app/Models/
  RefreshToken.php          ← novo; $fillable inclui `revoked` + `revoked_at`
                              (sem isso, Eloquent silenciosamente ignora a
                              revogação via $stored->update(...))
  User.php                  ← atualizar: remover unique(email) global,
                              adicionar role enum, last_login_ip/at, soft delete
```

**Services:**
```
app/Services/Auth/
  TokenService.php          ← geração/validação/revogação JWT e Refresh Token
                              Métodos públicos:
                                generateAccessToken(User, Tenant, purpose='access')
                                generatePurposeToken(User, Tenant, purpose, ttlSeconds)
                                generateAnonymousToken(array $payload, ttlSeconds)
                                  ← usado no invite (recipient ainda não é User)
                                generateRefreshToken(User, Tenant, familyId, ip, ua)
                                validateAccessToken(string $token): array
                                refreshTokens(string $token, ip, ua): array
                                revokeRefreshToken / revokeFamily / revokeAllUserTokens
                                newFamilyId(): string
                                hashToken(string): string
  LoginService.php          ← orquestra login, lock, notificação de IP
  PasswordService.php       ← recuperação (forgot/reset) + mudança autenticada (change)
  InviteService.php         ← geração e aceite de convite de corretor

app/Services/Tenant/
  TenantService.php         ← AJUSTE: cache armazena ARRAY de atributos, não o
                              model Tenant. Laravel 13 default
                              `cache.serializable_classes => false` rejeita
                              deserialização de qualquer classe (defesa contra
                              gadget chain se APP_KEY vazar) e devolveria
                              `__PHP_Incomplete_Class`. Hidratamos o model
                              via setRawAttributes na leitura.
```

**Controllers:**
```
app/Http/Controllers/Auth/
  LoginController.php
  LogoutController.php
  RefreshController.php
  ForgotPasswordController.php
  ResetPasswordController.php
  ChangePasswordController.php
  RegisterController.php          ← cliente final (vitrine)
  InviteController.php            ← envio de convite (admin only)
  AcceptInviteController.php      ← aceite de convite (público com token)
```

Todos os controllers que emitem cookie de refresh usam o helper:
```
secure: app()->environment('production')   ← Secure só em prod; dev local roda HTTP
httpOnly: true                              ← imutável (defesa contra XSS)
sameSite: 'lax'                             ← imutável
```

**Form Requests:**
```
app/Http/Requests/Auth/
  LoginRequest.php
  RegisterRequest.php
  ForgotPasswordRequest.php
  ResetPasswordRequest.php
  ChangePasswordRequest.php
  InviteRequest.php
  AcceptInviteRequest.php
```

**Middleware:**
```
app/Http/Middleware/
  AuthenticateJWT.php           ← novo: valida Access Token, injeta usuário
  SetTenantContext.php          ← atualizar: aceita tenant_id do JWT
  HandleInertiaRequests.php     ← novo (gerado via `artisan inertia:middleware`)
                                  share() expõe: tenant ({ id, slug, name }),
                                  auth.user ({ id, name, email, role }),
                                  flash messages
                                  Registrado no web group via
                                  $middleware->web(append: [...]) em bootstrap/app.php
```

**Eventos e Listeners:**
```
app/Events/Auth/
  UserLoggedIn.php              ← user, tenant, ipAddress, userAgent, newIp:bool
  UserRegistered.php            ← user, tenant, ipAddress (cadastro de cliente)
  PasswordReset.php             ← user, tenant, ipAddress (via fluxo forgot+reset)
  PasswordChanged.php           ← user, tenant, ipAddress (via fluxo autenticado)

app/Listeners/Auth/
  SendNewIpLoginNotification.php           ← ShouldQueue
                                             handle(UserLoggedIn): short-circuit
                                             se !newIp; envia notificação
  SendPasswordChangedNotification.php      ← ShouldQueue
                                             handle(PasswordReset|PasswordChanged)
                                             — UNION TYPE — escuta ambos os eventos
                                             registrado 2× no EventServiceProvider
                                             (um por classe de evento)
```

Mapeamento no `EventServiceProvider::$listen`:
```
UserLoggedIn  → SendNewIpLoginNotification
PasswordReset → SendPasswordChangedNotification
PasswordChanged → SendPasswordChangedNotification
```

**Notifications:**
```
app/Notifications/Auth/
  NewIpLoginNotification.php
  PasswordResetNotification.php
  PasswordChangedNotification.php
  CorretorInviteNotification.php
```

**Rules:**
```
app/Rules/
  StrongPassword.php        ← min 8, 1 letra, 1 número, uncompromised()
```

**Exceptions:**
```
app/Exceptions/Auth/
  InvalidCredentialsException.php
  AccountLockedException.php           ← carrega retryAfterSeconds
  InvalidTokenException.php            ← JWT inválido/expirado/purpose errado
  TokenReplayException.php             ← refresh com token já rotacionado
  MissingPepperException.php           ← falha de configuração (ADR-023)
  EmailAlreadyRegisteredException.php  ← invite: e-mail já existe no tenant
```

**Jobs:**
```
app/Jobs/Auth/
  CleanupExpiredTokens.php  ← diário; deleta refresh_tokens onde expires_at < now()
                              implementa ShouldQueue
                              registrado via Schedule::job(new CleanupExpiredTokens)
                              ->daily() em AppServiceProvider::registerSchedule()
                              (padrão Laravel 13, sem Kernel)
```

**Seeders (dev):**
```
database/seeders/
  TenantSeeder.php          ← cria tenant `teste-interno`
  UserSeeder.php            ← cria admin@teste.test / Senha@1234 no teste-interno
                              (precisa rodar via --database=pgsql_migrator)
```

**Frontend:**
```
resources/views/
  app.blade.php              ← root template Inertia (Vite, @inertia, @inertiaHead)

resources/js/
  app.tsx                    ← entry: createInertiaApp + QueryClientProvider
                               (Tanstack Query) + import.meta.glob das Pages
  vite-env.d.ts              ← /// <reference types="vite/client" /> (necessário
                               para import.meta.env e import.meta.glob)

  Pages/Auth/
    Login.tsx
    ForgotPassword.tsx
    ResetPassword.tsx        ← token via query string ?token=...
    Register.tsx             ← cliente vitrine (sem `phone` na v1)
    AcceptInvite.tsx         ← token via query string ?token=...

  Layouts/
    AuthLayout.tsx           ← shell visual com header (tenant.name) + footer

  Components/forms/
    TextField.tsx            ← input + label + error (com aria-* corretos)
    SubmitButton.tsx         ← loading state, disabled
    FormError.tsx            ← banner de erro do servidor

  lib/
    axios.ts                 ← interceptor 401 + single-flight refresh queue;
                               access_token APENAS em memória (AC-12)
                               expõe: api, getAccessToken, setAccessToken,
                               clearAccessToken, subscribeAccessToken

  hooks/
    useAuth.ts               ← lê auth.user de Inertia shared props,
                               expõe login()/logout()/user/isAuthenticated
```

## Modelos e Schemas

**Tabela: `refresh_tokens`**
```
id              uuid, PK
user_id         uuid, FK users.id, NOT NULL
tenant_id       uuid, FK tenants.id, NOT NULL
family_id       uuid, NOT NULL          ← agrupa tokens da mesma sessão
token_hash      varchar(64), NOT NULL   ← SHA-256 do token puro (nunca armazenar puro)
expires_at      timestamp, NOT NULL
revoked         boolean, default false
revoked_at      timestamp, nullable
ip_address      varchar(45), NOT NULL
user_agent      text, nullable
created_at      timestamp

Indexes:
  idx_rt_user       (user_id)
  idx_rt_hash       (token_hash)
  idx_rt_expires    (expires_at)
  idx_rt_family     (family_id)
```

**Alterações em `users`:**
```
Remover:  unique(email)                       ← índice global
Adicionar: unique(['email', 'tenant_id'])     ← único por tenant
Adicionar: last_login_ip  varchar(45), nullable
Adicionar: last_login_at  timestamp, nullable
```

**JWT Payload (Access Token e Password Reset):**
```json
{
  "user_id":   "uuid",
  "tenant_id": "uuid",
  "role":      "admin | corretor | cliente | super_admin",
  "purpose":   "access | password_reset",
  "exp":       1234567890,
  "iat":       1234567890
}
```

**JWT Payload (Invite Token — anônimo, recipient ainda não é User):**
```json
{
  "email":     "novo@corretor.test",
  "role":      "corretor",
  "tenant_id": "uuid",
  "purpose":   "invite",
  "exp":       1234567890,
  "iat":       1234567890
}
```

Algoritmo: **HS256** (HMAC-SHA256 com `APP_KEY`) — suficiente para MVP.

> `first_access` (mencionado em discovery) **não foi implementado** nesta iteração — todos os fluxos atuais cobrem o caso de "primeiro login" sem precisar de purpose dedicado.

## Contrato de API

**POST `/auth/login`**
```
Request:  { email: string, password: string }
Response 200: { access_token: string, user: { id, name, role } }
            + Set-Cookie: refresh_token=<token>; HttpOnly; Secure; SameSite=Lax
Response 401: { message: "E-mail ou senha inválidos" }
Response 429: { message: "Conta bloqueada. Tente novamente em {X} minutos." }
```

**POST `/auth/refresh`**
```
Cookie:   refresh_token=<token>
Response 200: { access_token: string }
           + Set-Cookie: refresh_token=<novo_token>; HttpOnly; Secure; SameSite=Lax
Response 401: { message: "Sessão expirada" }
```

**POST `/auth/logout`**
```
Header:   Authorization: Bearer <access_token>
Cookie:   refresh_token=<token>
Response 204: (no content)
           + Set-Cookie: refresh_token=; Max-Age=0
```

**POST `/auth/forgot-password`**
```
Request:  { email: string }
Response 200: { message: "Se o e-mail existir, você receberá as instruções." }
```

**POST `/auth/reset-password`**
```
Request:  { token: string, password: string, password_confirmation: string }
Response 200: { access_token: string, user: {...} }
           + Set-Cookie: refresh_token=<token>; HttpOnly; Secure; SameSite=Lax
Response 410: { message: "Link inválido ou expirado." }
Response 422: { errors: { password: [...] } }
```

**POST `/auth/change-password`** _(autenticado)_
```
Header:   Authorization: Bearer <access_token>
Request:  { current_password: string, password: string, password_confirmation: string }
Response 200: {}
Response 422: { errors: { current_password: ["Senha atual incorreta."], ... } }
```

**POST `/auth/register`** _(vitrine — cliente final)_
```
Request:  { name: string, email: string, password: string,
            password_confirmation: string, terms: true }
            ← `phone` adiado (v1.1) — depende de user_profiles (ADR-003)
Response 201: { access_token: string, user: {...} }
           + Set-Cookie: refresh_token=<token>; HttpOnly; SameSite=Lax; (Secure só em prod)
Response 422: { errors: {...} }
```

**POST `/admin/corretores/convite`** _(admin autenticado)_
```
Header:   Authorization: Bearer <access_token>
Request:  { email: string }
            ← `name` adiado: corretor define no aceite (v1.1)
Response 201: { message: "Convite enviado com sucesso." }
Response 422: { message: "Já existe uma conta com este e-mail neste tenant." }
Response 403: (sem json) — role do JWT não é admin
```

**POST `/auth/convite/aceitar`**
```
Request:  { token: string, name: string, password: string, password_confirmation: string }
Response 201: { access_token: string, user: {...} }
           + Set-Cookie: refresh_token=<token>; HttpOnly; SameSite=Lax; (Secure só em prod)
Response 410: { message: "Convite inválido." | "Convite expirado." | "Este convite já foi utilizado." }
Response 422: { errors: {...} }
```

**GET pages (Inertia / React)**
```
GET /auth/login              → render('Auth/Login')
GET /auth/cadastro           → render('Auth/Register')
GET /auth/esqueci-senha      → render('Auth/ForgotPassword')
GET /auth/reset-password     → render('Auth/ResetPassword')        ?token=...
GET /auth/convite/aceitar    → render('Auth/AcceptInvite')         ?token=...
```

## Riscos Técnicos

- **Race condition no refresh** — múltiplas requests expirando ao mesmo tempo disparam vários refreshes; frontend implementa fila (só uma Promise de refresh ativa, demais aguardam o resultado)
- **Replay attack no refresh** — detectado via `family_id`: se chegar token de família já rotacionada, revogar todos da família e forçar re-login
- **Migration `unique(email, tenant_id)`** — a tabela `users` do Laravel tem constraint global; migration deve fazer `dropUnique` antes de criar o composto; verificar se há dados existentes
- **`SetTenantContext` com dupla fonte** — ao receber request autenticada, o middleware deve preferir o `tenant_id` do JWT em vez do subdomínio para evitar conflito em impersonation futura
- **`APP_KEY` como segredo do JWT** — rotação da APP_KEY invalida todos os tokens ativos; documentar procedimento de rotação

## Decisões Tomadas Durante a Implementação

1. **Detecção de "IP novo"** — implementado como comparação direta entre `last_login_ip` e o IP atual; aceita falso positivo em mudança legítima de IP (4G, VPN). O listener `SendNewIpLoginNotification` curto-circuita se `newIp === false`, então o custo do listener queued sempre roda mas não envia e-mail à toa.

2. **`CleanupExpiredTokens` job** — registrado via `Schedule::job(new CleanupExpiredTokens)->daily()` em `AppServiceProvider::registerSchedule()` (padrão Laravel 13, sem Kernel).

3. **Rota do super admin** — deferido. O `/auth/login` atual exige tenant resolvido via subdomínio. Super admin terá seu próprio domínio/rotas em módulo separado (ADR-010), não impacta este módulo.

4. **Setup de roles do Postgres no dev local** — `.env` precisa ter `DB_USERNAME=acho_app` (e não `sail`). Migrations devem rodar via conexão `pgsql_migrator` (role com BYPASSRLS e privilégios de CREATE). Makefile atualizado: `make migrate` e `make fresh` chamam `--database=pgsql_migrator`.

5. **Cookie sem `EncryptCookies` em rotas de tenant** — As rotas de tenant herdam o grupo `web`, mas a abordagem atual emite o `refresh_token` raw (não encrypted). Para tests Pest com cookies funcionarem, usar `disableCookieEncryption()` + `withCredentials()` antes de `withCookies()`.

6. **Inertia infrastructure (paralelo ao auth)** — Antes de TASK-30+, foi necessário plumar a infraestrutura Inertia que ainda não existia: composer `inertia-laravel`, `HandleInertiaRequests` middleware no web group, `resources/views/app.blade.php`, `resources/js/app.tsx` montando `createInertiaApp` + `QueryClientProvider`, alias `@/*` no `tsconfig` e Vite. Documentado aqui pois sem isso o módulo de auth não renderia páginas.

## Cobertura de Testes

- **97 testes Pest verdes / 236 asserts**, distribuídos:
  - `tests/Feature/Auth/LoginTest.php` (9)
  - `tests/Feature/Auth/LogoutTest.php` (4)
  - `tests/Feature/Auth/RefreshTest.php` (6)
  - `tests/Feature/Auth/PasswordRecoveryTest.php` (9)
  - `tests/Feature/Auth/ChangePasswordTest.php` (7)
  - `tests/Feature/Auth/RegisterTest.php` (7)
  - `tests/Feature/Auth/InviteTest.php` (9)
  - `tests/Feature/Auth/CleanupExpiredTokensTest.php` (3)
  - `tests/Feature/Auth/PasswordHashingTest.php` (7) — pré-existente, ADR-023
- **Larastan** nível configurado em `phpstan.neon` — 0 erros
- **TypeScript** `tsc --noEmit` — 0 erros
- **Vite build** OK (chunks: app, axios, Login, Register, ForgotPassword, ResetPassword, AcceptInvite)

## Changelog

**v1.1 (implementação concluída — 2026-05-22)**

Mudanças em relação à v1.0:

- **Spec funcional v1.1** — `phone` adiado no register (ADR-003); `name` removido do request de invite (corretor define no aceite); todos os 12 ACs verificados.

- **Novos métodos no `TokenService`** — `generatePurposeToken` (genérico user-bound com TTL) e `generateAnonymousToken` (payload livre, sem user — usado no invite).

- **Novo evento `PasswordChanged`** e novo listener `SendPasswordChangedNotification` com union type `PasswordReset|PasswordChanged`.

- **Nova exception `EmailAlreadyRegisteredException`** usada pelo `InviteService`.

- **Novo `AcceptInviteController`** — separado do `InviteController`. O original cuida do envio (admin only); o novo cuida do aceite (público + token).

- **Cookie `Secure` env-conditional** — `app()->environment('production')`. Permite dev local em HTTP sem perder proteção em prod.

- **GET routes Inertia** adicionadas: `/auth/login`, `/auth/cadastro`, `/auth/esqueci-senha`, `/auth/reset-password`, `/auth/convite/aceitar`.

- **Infra Inertia** plumada (não existia): composer package, middleware, blade root, `app.tsx`, `vite-env.d.ts`.

- **`RefreshToken::$fillable`** inclui `revoked` + `revoked_at` (sem isso o `$stored->update()` do refresh silenciosamente falhava — bug crítico de segurança que vazaria sessões revogadas).

- **`TenantService` cache refactor** — armazena array de atributos em vez do model Eloquent (Laravel 13 `cache.serializable_classes => false` rejeita deserialize).

- **`UserSeeder`** adicionado para dev (admin@teste.test / Senha@1234 no `teste-interno`).

- **Tasks 30-35** completas: axios client + useAuth hook + 5 páginas React (Login, ForgotPassword, ResetPassword, Register, AcceptInvite) + AuthLayout + 3 form components reutilizáveis.
