# Plano: Módulo de Autenticação
**Spec funcional:** specs/wip/02-auth-module/01-spec-functional.md
**Spec técnica:**   specs/wip/02-auth-module/02-spec-tech.md
**Status:** done (35/35 tasks)

## Tarefas

### Camada: Dependências

- [x] TASK-01 — Instalar `firebase/php-jwt` via Composer
  - Spec ref: base de todo JWT
  - Done quando: `composer.json` lista o pacote; `composer install` passa sem erros

---

### Camada: Banco de Dados

- [x] TASK-02 — Migration `create_refresh_tokens_table`
  - Spec ref: AC-02, AC-09
  - Done quando: tabela criada com colunas `id`, `user_id`, `tenant_id`, `family_id`, `token_hash`, `expires_at`, `revoked`, `revoked_at`, `ip_address`, `user_agent`, `created_at`; índices em `token_hash`, `user_id`, `expires_at`, `family_id`; `down()` remove a tabela

- [x] TASK-03 — Migration `update_users_table_email_tenant_unique`
  - Spec ref: AC-01
  - Done quando: `unique(email)` global removido; `unique(['email', 'tenant_id'])` adicionado; colunas `last_login_ip` (varchar 45, nullable) e `last_login_at` (timestamp, nullable) adicionadas; `down()` reverte tudo

---

### Camada: Models

- [x] TASK-04 — Model `RefreshToken`
  - Spec ref: AC-02
  - Done quando: fillable, casts (`revoked` bool, `expires_at` datetime), scopes `active()` e `byFamily(string $familyId)`; `belongsTo(User::class)` e `belongsTo(Tenant::class)`; não estende `BaseTenantModel` (infraestrutura, não negócio)

- [x] TASK-05 — Atualizar Model `User`
  - Spec ref: AC-01, AC-04
  - Done quando: `$hidden` inclui `password`; método `hasRole(string $role): bool`; `hasMany(RefreshToken::class)`; política de senha padrão registrada em `AppServiceProvider` via `Password::defaults()`

---

### Camada: Core de Tokens

- [x] TASK-06 — `TokenService`
  - Spec ref: AC-01, AC-02, AC-03, AC-06, AC-07, AC-08
  - Done quando: implementa os métodos:
    - `generateAccessToken(User $user, Tenant $tenant, string $purpose = 'access'): string` — JWT HS256, payload com `user_id`, `tenant_id`, `role`, `purpose`, `exp`, `iat`; TTL fixo de 15min
    - `generateRefreshToken(User $user, Tenant $tenant, string $familyId, string $ip, string $ua): string` — gera UUID, persiste SHA-256 na tabela; retorna token puro (nunca armazenado)
    - `validateAccessToken(string $token): array` — retorna payload ou lança exceção tipada
    - `refreshTokens(string $refreshToken, string $ip, string $ua): array` — valida hash; se token já foi rotacionado (replay), revoga família inteira via `revokeFamily` e lança 401; senão rotaciona e retorna novos tokens
    - `revokeRefreshToken(string $refreshToken): void`
    - `revokeFamily(string $familyId): void` — revoga todos os tokens da família (ADR-014: mitigação de replay)
    - `revokeAllUserTokens(string $userId, ?string $exceptTokenHash = null): void`

---

### Camada: Middleware

- [x] TASK-07 — Middleware `AuthenticateJWT`
  - Spec ref: AC-01
  - Done quando: valida Access Token via `TokenService::validateAccessToken`; injeta `auth.user` (instância User) e `auth.payload` (array do JWT) no request; retorna 401 em token inválido/expirado; registrado em `bootstrap/app.php` como alias `auth.jwt`; filtragem por `purpose` fica a cargo do controller (ADR-014)

- [x] TASK-08 — Atualizar `SetTenantContext`
  - Spec ref: AC-01
  - Done quando: quando `auth.payload` presente no request, usa `tenant_id` do JWT para configurar contexto Postgres; comportamento de resolução por subdomínio preservado para requests anônimas (ADR-016)

---

### Camada: Regras

- [x] TASK-09 — Rule `StrongPassword`
  - Spec ref: AC-01, AC-06, AC-07, AC-08, AC-10
  - Done quando: valida mínimo 8 caracteres, pelo menos 1 letra, pelo menos 1 número, `Password::uncompromised()`; mensagem genérica em português ("Senha não atende aos requisitos de segurança")

---

### Camada: Login e Logout

- [x] TASK-10 — `LoginService`
  - Spec ref: AC-04, AC-05, AC-11
  - Done quando: `login(LoginData $data, string $ip, string $ua, Tenant $tenant): array` — valida credenciais (Argon2id + Pepper via ADR-023), aplica lock via `RateLimiter` (5 tentativas / 15min chave `login:{tenant_id}:{email}`), atualiza `last_login_at` e `last_login_ip`, despacha `UserLoggedIn`, gera e retorna tokens

- [x] TASK-11 — `LoginRequest` + `LoginController` + rota `POST /auth/login`
  - Spec ref: AC-01, AC-04, AC-05
  - Done quando: Request valida `email` e `password` obrigatórios; Controller delega ao `LoginService`, retorna access token no body e define cookie HttpOnly com Refresh Token; rota sem middleware de auth

- [x] TASK-12 — `LogoutController` + rota `POST /auth/logout`
  - Spec ref: AC-09
  - Done quando: revoga Refresh Token do cookie via `TokenService::revokeRefreshToken`; limpa cookie (`Max-Age=0`); retorna 204; rota protegida por `auth.jwt`

- [x] TASK-13 — `RefreshController` + rota `POST /auth/refresh`
  - Spec ref: AC-02, AC-03
  - Done quando: lê Refresh Token do cookie; chama `TokenService::refreshTokens`; retorna novo Access Token no body e atualiza cookie; replay retorna 401 (ADR-014: família revogada)

- [x] TASK-14 — Evento `UserLoggedIn` + Listener `SendNewIpLoginNotification` + Notification `NewIpLoginNotification`
  - Spec ref: AC-11
  - Done quando: listener compara `ip_address` com `last_login_ip`; se diferente, despacha `NewIpLoginNotification` via queue; falha no envio não bloqueia login (ADR-014); e-mail em português com IP, data/hora e link de suporte

---

### Camada: Recuperação de Senha

- [x] TASK-15 — `PasswordService` — métodos `forgot` e `reset`
  - Spec ref: AC-06, AC-07
  - Done quando:
    - `forgot(string $email, Tenant $tenant): void` — busca usuário no tenant; se encontrado, gera JWT `purpose: 'password_reset'` (1h), envia `PasswordResetNotification` via queue; sempre retorna void
    - `reset(ResetPasswordData $data, Tenant $tenant): array` — valida JWT e `purpose`, valida força da senha, atualiza com Argon2id+Pepper, chama `revokeAllUserTokens`, despacha `PasswordReset`, gera nova sessão

- [x] TASK-16 — `ForgotPasswordRequest` + `ForgotPasswordController` + rota `POST /auth/forgot-password`
  - Spec ref: AC-07
  - Done quando: Request valida `email`; Controller delega ao `PasswordService::forgot`; sempre responde 200 com mensagem neutra (não vaza existência do e-mail)

- [x] TASK-17 — `ResetPasswordRequest` + `ResetPasswordController` + rota `POST /auth/reset-password`
  - Spec ref: AC-06, AC-07
  - Done quando: Request valida `token`, `password`, `password_confirmation`; Controller delega ao `PasswordService::reset`; 200 com tokens em sucesso, 410 em token inválido/expirado

- [x] TASK-18 — Notifications `PasswordResetNotification` + `PasswordChangedNotification` + Evento `PasswordReset`
  - Spec ref: AC-06
  - Done quando: `PasswordResetNotification` envia e-mail com botão de link e TTL informado; `PasswordChangedNotification` envia confirmação após troca; ambas em português

---

### Camada: Mudança de Senha

- [x] TASK-19 — `PasswordService::change` + `ChangePasswordRequest` + `ChangePasswordController` + rota `POST /auth/change-password`
  - Spec ref: AC-06
  - Done quando: valida senha atual (422 se incorreta), atualiza com Argon2id+Pepper, chama `revokeAllUserTokens(exceptTokenHash: $current)`, despacha `PasswordChanged`; rota protegida por `auth.jwt`

---

### Camada: Cadastro de Cliente Final

- [x] TASK-20 — `RegisterRequest` + `RegisterController` + rota `POST /auth/register`
  - Spec ref: AC-10
  - Done quando: Request valida `name`, `email`, `password`, `phone`, `terms: true`; Controller cria usuário com role `cliente` vinculado ao tenant atual, gera sessão de 30 dias, despacha `UserRegistered`, retorna tokens; unicidade validada contra `(email, tenant_id)`

---

### Camada: Convite de Corretor

- [x] TASK-21 — `InviteService` — métodos `generate` e `accept`
  - Spec ref: AC-08
  - Done quando:
    - `generate(InviteData $data, User $admin, Tenant $tenant): void` — valida que e-mail não existe no tenant, gera JWT `purpose: 'invite'` (48h) com `email`, `role: 'corretor'`, `tenant_id`, envia `CorretorInviteNotification`
    - `accept(AcceptInviteData $data, Tenant $tenant): array` — valida JWT e `purpose: 'invite'`, cria usuário corretor, gera sessão; 410 em token inválido/expirado

- [x] TASK-22 — `InviteRequest` + `AcceptInviteRequest` + `InviteController` + rotas
  - Spec ref: AC-08
  - Done quando: `POST /admin/corretores/convite` protegida por `auth.jwt` com verificação de role `admin`; `POST /auth/convite/aceitar` pública; ambas delegam ao `InviteService`

- [x] TASK-23 — Notification `CorretorInviteNotification`
  - Spec ref: AC-08
  - Done quando: e-mail em português com botão de aceite, nome da imobiliária, aviso de expiração em 48h e próximos passos

---

### Camada: Manutenção

- [x] TASK-24 — Job `CleanupExpiredTokens` + registro no Scheduler
  - Spec ref: saúde da tabela refresh_tokens
  - Done quando: Job deleta tokens onde `expires_at < now()`; registrado via `Schedule::job(CleanupExpiredTokens::class)->daily()` em `AppServiceProvider` (padrão Laravel 13, sem Kernel)

---

### Camada: Testes

- [x] TASK-25 — Testes de login
  - Spec ref: AC-01, AC-04, AC-05, AC-11
  - Done quando: cobre happy path (tokens gerados corretamente), credenciais inválidas (401 genérico), lock após 5 tentativas (429), IP novo dispara evento, usuário de outro tenant rejeitado

- [x] TASK-26 — Testes de refresh e logout
  - Spec ref: AC-02, AC-03, AC-09
  - Done quando: cobre rotação (token antigo rejeitado), replay attack (família revogada, 401), token expirado (401), logout revoga no banco e limpa cookie

- [x] TASK-27 — Testes de recuperação de senha
  - Spec ref: AC-06, AC-07
  - Done quando: cobre forgot (sempre 200), reset válido (nova sessão + outras sessões revogadas), token expirado (410), token já usado (410)

- [x] TASK-28 — Testes de mudança de senha e cadastro de cliente
  - Spec ref: AC-06, AC-10
  - Done quando: mudança com senha atual incorreta (422), mudança válida revoga outras sessões e mantém a atual; cadastro cria conta com role correto e retorna sessão, e-mail duplicado no tenant (422)

- [x] TASK-29 — Testes de convite de corretor
  - Spec ref: AC-08
  - Done quando: envio gera token e dispara e-mail, aceite cria corretor e retorna sessão, convite expirado (410), e-mail já existente no tenant (422)

---

### Camada: Frontend

- [x] TASK-30 — `resources/js/lib/axios.ts` com interceptor 401 + refresh queue
  - Spec ref: AC-02
  - Done quando: interceptor detecta 401 e chama `POST /auth/refresh`; requests paralelas aguardam a Promise de refresh em andamento antes de retentar (ADR-014: refresh queue); falha no refresh limpa estado e redireciona para login

- [x] TASK-31 — `resources/js/hooks/useAuth.ts`
  - Spec ref: AC-01
  - Done quando: Access Token mantido em memória (nunca localStorage); expõe `user`, `isAuthenticated`, `login()`, `logout()`; reconstrói token via refresh no carregamento da página

- [x] TASK-32 — `resources/js/Pages/Auth/Login.tsx`
  - Spec ref: AC-01, AC-04, AC-05
  - Done quando: formulário com `email` e `password`, link "Esqueci minha senha", mensagem genérica em 401, feedback de bloqueio em 429

- [x] TASK-33 — `resources/js/Pages/Auth/ForgotPassword.tsx` + `ResetPassword.tsx`
  - Spec ref: AC-07
  - Done quando: Forgot exibe mensagem neutra após submit; Reset valida confirmação de senha no cliente e exibe erros do backend

- [x] TASK-34 — `resources/js/Pages/Auth/Register.tsx`
  - Spec ref: AC-10
  - Done quando: formulário com `name`, `email`, `password`, `phone`, checkbox de termos; login automático após cadastro bem-sucedido

- [x] TASK-35 — `resources/js/Pages/Auth/AcceptInvite.tsx`
  - Spec ref: AC-08
  - Done quando: lê `token` da query string; formulário de definição de senha; login automático após aceite; exibe mensagem clara em 410

---

## Ordem sugerida de implementação

```
1.  TASK-01 — firebase/php-jwt instalado
2.  TASK-02 — Migration refresh_tokens
3.  TASK-03 — Migration update_users
4.  TASK-04 — Model RefreshToken
5.  TASK-05 — Model User atualizado
6.  TASK-06 — TokenService                  ← núcleo de tudo
7.  TASK-09 — StrongPassword Rule
8.  TASK-07 — AuthenticateJWT middleware
9.  TASK-08 — SetTenantContext atualizado
10. TASK-10 — LoginService
11. TASK-11 — Login (controller + rota)      ← primeiro fluxo testável
12. TASK-12 — Logout
13. TASK-13 — Refresh
14. TASK-14 — UserLoggedIn + notificação IP
15. TASK-15 — PasswordService (forgot+reset)
16. TASK-16 — ForgotPassword (controller + rota)
17. TASK-17 — ResetPassword (controller + rota)
18. TASK-18 — Notifications de senha
19. TASK-19 — ChangePassword
20. TASK-20 — Register cliente
21. TASK-21 — InviteService
22. TASK-22 — Invite (controllers + rotas)
23. TASK-23 — CorretorInvite notification
24. TASK-24 — CleanupExpiredTokens job
25. TASK-25 — Testes login
26. TASK-26 — Testes refresh + logout
27. TASK-27 — Testes recuperação de senha
28. TASK-28 — Testes mudança + cadastro
29. TASK-29 — Testes convite
30. TASK-30 — axios interceptor + refresh queue
31. TASK-31 — useAuth hook
32. TASK-32 — Login.tsx
33. TASK-33 — ForgotPassword + ResetPassword
34. TASK-34 — Register.tsx
35. TASK-35 — AcceptInvite.tsx
```

## Notas de implementação

- Decisões de replay attack, refresh queue e filtragem por `purpose` estão definidas em ADR-014 — sem incerteza técnica
- `SetTenantContext` preserva comportamento anônimo (subdomínio) por ADR-016
- Algoritmo JWT: HS256 com `APP_KEY` — rotação da key invalida todos os tokens ativos; documentar procedimento
- Todos os e-mails de auth via Resend conforme ADR-005
