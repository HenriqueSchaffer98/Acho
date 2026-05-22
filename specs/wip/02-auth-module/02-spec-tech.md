# Spec Técnica: Módulo de Autenticação
**Versão:** 1.0
**Status:** approved
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

- `firebase/php-jwt` — geração e validação de JWT com claims customizados (`tenant_id`, `role`, `purpose`); Sanctum descartado por usar tokens opacos sem claims
- `spatie/laravel-data` — DTOs para inputs/outputs dos Services (já instalado)
- `spatie/laravel-permission` — gestão de roles (já instalado)
- Laravel `Password::defaults()` — política de senha com `uncompromised()` (HaveIBeenPwned)
- Laravel `RateLimiter` + Redis — lock de tentativas de login
- Resend via Laravel Notifications — e-mails de auth (ADR-005)

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
  RefreshToken.php          ← novo
  User.php                  ← atualizar: remover unique(email) global
```

**Services:**
```
app/Services/Auth/
  TokenService.php          ← geração/validação/revogação JWT e Refresh Token
  LoginService.php          ← orquestra login, lock, notificação de IP
  PasswordService.php       ← recuperação, reset, mudança de senha
  InviteService.php         ← geração e aceite de convite de corretor
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
  RegisterController.php    ← cliente final (vitrine)
  InviteController.php      ← envio e aceite de convite
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
  AuthenticateJWT.php       ← novo: valida Access Token, injeta usuário
  SetTenantContext.php      ← atualizar: aceita tenant_id do JWT
```

**Eventos e Listeners:**
```
app/Events/Auth/
  UserLoggedIn.php
  UserRegistered.php
  PasswordReset.php
  PasswordChanged.php

app/Listeners/Auth/
  SendNewIpLoginNotification.php
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

**Jobs:**
```
app/Jobs/
  CleanupExpiredTokens.php  ← diário, deleta refresh_tokens expirados
```

**Frontend:**
```
resources/js/
  Pages/Auth/
    Login.tsx
    ForgotPassword.tsx
    ResetPassword.tsx
    Register.tsx             ← cliente vitrine
    AcceptInvite.tsx
  lib/
    axios.ts                 ← interceptor 401 + refresh queue
  hooks/
    useAuth.ts               ← estado de auth, helpers
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

**JWT Payload (Access Token):**
```json
{
  "user_id":   "uuid",
  "tenant_id": "uuid",
  "role":      "admin | corretor | cliente | super_admin",
  "purpose":   "access | first_access | password_reset | invite",
  "exp":       1234567890,
  "iat":       1234567890
}
```
Algoritmo: **HS256** (HMAC-SHA256 com `APP_KEY`) — suficiente para MVP.

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
Request:  { name: string, email: string, password: string, phone: string, terms: true }
Response 200: { access_token: string, user: {...} }
           + Set-Cookie: refresh_token=<token>; HttpOnly; Secure; SameSite=Lax
Response 422: { errors: {...} }
```

**POST `/admin/corretores/convite`** _(admin autenticado)_
```
Header:   Authorization: Bearer <access_token>
Request:  { name: string, email: string }
Response 200: { message: "Convite enviado." }
Response 422: { errors: { email: ["Já existe um corretor com este e-mail neste tenant."] } }
```

**POST `/auth/convite/aceitar`**
```
Request:  { token: string, password: string, password_confirmation: string }
Response 200: { access_token: string, user: {...} }
           + Set-Cookie: refresh_token=<token>; HttpOnly; Secure; SameSite=Lax
Response 410: { message: "Convite expirado ou inválido." }
Response 422: { errors: {...} }
```

## Riscos Técnicos

- **Race condition no refresh** — múltiplas requests expirando ao mesmo tempo disparam vários refreshes; frontend implementa fila (só uma Promise de refresh ativa, demais aguardam o resultado)
- **Replay attack no refresh** — detectado via `family_id`: se chegar token de família já rotacionada, revogar todos da família e forçar re-login
- **Migration `unique(email, tenant_id)`** — a tabela `users` do Laravel tem constraint global; migration deve fazer `dropUnique` antes de criar o composto; verificar se há dados existentes
- **`SetTenantContext` com dupla fonte** — ao receber request autenticada, o middleware deve preferir o `tenant_id` do JWT em vez do subdomínio para evitar conflito em impersonation futura
- **`APP_KEY` como segredo do JWT** — rotação da APP_KEY invalida todos os tokens ativos; documentar procedimento de rotação

## Dúvidas Técnicas em Aberto

1. **Detecção de "IP novo"** — comparar o IP do login com `last_login_ip` do usuário; abordagem simples aceita, com ciência de falso positivo em mudança legítima de IP (4G, VPN)
2. **`CleanupExpiredTokens` job** — registrar via `Schedule::call` no `AppServiceProvider` (padrão Laravel 13, sem Kernel)
3. **Rota do super admin** — mesmo controller `/auth/login` com branch por contexto detectado via guard/middleware de domínio
