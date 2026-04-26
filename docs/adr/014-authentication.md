# ADR-014: Estratégia de Autenticação

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Esta ADR detalha a estratégia técnica de autenticação, separando-a da definição funcional do módulo (tratada em ADR-009).

A escolha da estratégia técnica afeta:

- Segurança das sessões
- Capacidade de revogar acesso
- Performance de validação em cada requisição
- Complexidade de implementação
- Suporte a múltiplos contextos (web, mobile futuro)

Em sistema multi-tenant, o token de autenticação precisa carregar informação de tenant para evitar consultas extras ao banco. Sem isso, toda request paga o custo de descobrir a qual tenant o usuário pertence.

A plataforma tem 3 contextos distintos de autenticação:

1. **Landing page** — visitantes anônimos e cadastros
2. **Subdomínio do tenant** — admin, corretor, cliente
3. **Painel super admin** — operadores da plataforma

Cada contexto tem regras diferentes de TTL, escopo e revogação.

---

## Decisão

Adotar **JWT (Access Token) + Refresh Token rotacionado**, com Refresh Tokens persistidos no banco para permitir revogação. TTLs específicos por perfil de usuário, otimizando segurança vs UX.

### Detalhamento

```
Estrutura de Tokens
─────────────────────────────────────────

Access Token (JWT)
  ├── Validade: 15 minutos (todos os perfis)
  ├── Stateless (não consulta banco)
  ├── Payload:
  │   ├── user_id
  │   ├── tenant_id     ← crítico
  │   ├── role          ← admin | corretor | cliente
  │   ├── exp           ← expiração
  │   └── iat           ← emissão
  └── Assinado com APP_KEY

Refresh Token
  ├── Validade por perfil:
  │   ├── Admin do tenant: 8 horas
  │   ├── Corretor: 12 horas
  │   ├── Cliente final: 30 dias
  │   └── Super admin: 4 horas
  ├── Persistido no banco (revogável)
  ├── Rotacionado a cada uso
  ├── Hash armazenado (token puro nunca no banco)
  └── Vinculado ao tenant (não funciona em outros)
```

```
Fluxo de Autenticação Padrão
─────────────────────────────────────────

1. Login com e-mail + senha
       │
       ▼
2. Backend valida:
   ├── Senha correta (Argon2id + pepper)
   ├── Usuário pertence ao tenant correto
   └── Conta ativa
       │
       ▼
3. Backend gera 2 tokens:
   ├── Access Token (JWT, 15min)
   └── Refresh Token (UUID, persistido)
       │
       ▼
4. Resposta:
   ├── Access Token no body
   ├── Refresh Token em cookie HttpOnly
   └── User data no body
       │
       ▼
5. Frontend usa Access Token em toda request
   └── Header: Authorization: Bearer {token}
       │
       ▼
6. Quando Access Token expira (após 15min):
   ├── Frontend recebe 401
   ├── Tenta refresh com cookie do Refresh Token
   ├── Backend valida hash, gera novos tokens
   ├── Antigo Refresh Token é invalidado
   └── Frontend recebe novo Access Token
       │
       ▼
7. Logout:
   └── Revoga Refresh Token no banco
   └── Limpa cookie
   └── Próximo refresh é negado
```

```
Por Que tenant_id no JWT é Crítico
─────────────────────────────────────────

Sem tenant_id no token:
  └── Toda requisição precisa consultar o banco
      para descobrir tenant do usuário
  └── Latência extra em 100% das requisições
  └── Carga adicional no banco

Com tenant_id no token:
  ├── Middleware valida token (stateless)
  ├── tenant_id já disponível
  ├── RLS configurado sem consulta extra
  └── Zero latência adicional
```

```
Fluxos Especiais
─────────────────────────────────────────

Primeiro Acesso (Pós-Cadastro)
  ├── Token de primeiro acesso gerado no cadastro
  ├── JWT com claim purpose: "first_access"
  ├── Validade: 15 minutos
  ├── Enviado por e-mail
  └── Ao clicar:
      ├── Backend valida token
      ├── Cria sessão completa (Access + Refresh)
      └── Redireciona para onboarding

Convite de Corretor
  ├── Token gerado quando admin convida
  ├── JWT com claim purpose: "invite"
  ├── Inclui: email, role, tenant_id
  ├── Validade: 48 horas
  └── Ao aceitar:
      ├── Corretor define senha
      ├── Conta criada e vinculada
      └── Login automático

Login as (Super Admin)
  ├── Operador clica em "Acessar como" no painel
  ├── Backend gera JWT especial
  ├── Claims:
  │   ├── user_id (do admin do tenant)
  │   ├── tenant_id
  │   ├── role: 'admin'
  │   ├── impersonating: true     ← flag chave
  │   └── operator_id (super admin original)
  ├── Validade: 15 minutos
  └── Redireciona para subdomínio do tenant

Recuperação de Senha
  ├── Cliente solicita reset
  ├── JWT com claim purpose: "password_reset"
  ├── Validade: 1 hora
  ├── Token único, uso único
  └── Ao usar:
      ├── Define nova senha
      ├── Invalida TODOS os Refresh Tokens do usuário
      └── Força re-login
```

```
Estrutura no Banco
─────────────────────────────────────────

Tabela: refresh_tokens
  ├── id (uuid)
  ├── user_id (uuid)
  ├── tenant_id (uuid)
  ├── token_hash (varchar, 64 chars)  ← hash do token, não puro
  ├── expires_at (timestamp)
  ├── revoked (boolean)
  ├── revoked_at (timestamp, nullable)
  ├── ip_address (string)
  ├── user_agent (string)
  └── created_at

Indexes:
  ├── idx_refresh_user (user_id)
  ├── idx_refresh_hash (token_hash)
  └── idx_refresh_expires (expires_at)

Limpeza:
  └── Job diário deleta tokens expirados
```

```
Cookies de Sessão
─────────────────────────────────────────

Refresh Token em Cookie HttpOnly:
  ├── HttpOnly: true     (JS não acessa)
  ├── Secure: true       (apenas HTTPS)
  ├── SameSite: Lax      (proteção CSRF)
  ├── Domain: explícito do subdomínio
  │   └── primoimoveis.seuapp.com.br
  │   └── NÃO usa wildcard (.seuapp.com.br)
  └── Path: /

Access Token NO MEMORY:
  ├── Frontend mantém em memória (Tanstack Query)
  ├── Nunca em localStorage (XSS-vulnerable)
  └── Recarregar página = re-fetch via refresh
```

---

## Justificativa

A escolha por JWT + Refresh Token rotacionado se justifica por:

1. **Stateless validation** — Access Token validado sem consulta ao banco
2. **Revogabilidade** — Refresh Token no banco permite invalidar sessões
3. **TTL curto do Access** — Reduz janela de exposição se vazado
4. **TTL flexível do Refresh** — UX adaptada por perfil
5. **Tenant no token** — Performance ótima em multi-tenancy
6. **Padrão de mercado** — Documentação e ferramentas maduras

A escolha de TTLs por perfil:
- **Cliente final (30 dias)** — Vitrine pública precisa baixa fricção
- **Corretor (12 horas)** — Cobre dia de trabalho típico
- **Admin (8 horas)** — Mais sensível, expira no fim do dia
- **Super admin (4 horas)** — Máxima segurança operacional

---

## Alternativas Consideradas

### Alternativa A — Sessão Tradicional Laravel (Cookies + Sessions)

- **Descrição:** Sessions PHP padrão com cookies.
- **Pontos fortes:** Simples, suportado nativamente.
- **Pontos fracos:** Requer estado no servidor, dificulta escala horizontal futura.
- **Por que não foi escolhida:** JWT stateless escala melhor e é padrão moderno.

### Alternativa B — Apenas JWT (Sem Refresh Token)

- **Descrição:** JWT longo (24h) sem refresh.
- **Pontos fortes:** Implementação mais simples.
- **Pontos fracos:** Janela de exposição grande se vazado. Sem revogação real.
- **Por que não foi escolhida:** Trade-off de segurança não vale a simplicidade.

### Alternativa C — Refresh Token em LocalStorage

- **Descrição:** Token de refresh acessível pelo JS.
- **Pontos fortes:** Mais fácil de manipular no frontend.
- **Pontos fracos:** Vulnerável a XSS.
- **Por que não foi escolhida:** Cookie HttpOnly é objetivamente mais seguro.

### Alternativa D — OAuth2 Completo (Authorization Server)

- **Descrição:** Implementar fluxo OAuth2 com authorization server.
- **Pontos fortes:** Padrão de mercado, suporta integrações.
- **Pontos fracos:** Overkill no MVP. Complexidade desnecessária.
- **Por que não foi escolhida:** Postergada para quando houver integração de terceiros.

---

## Consequências

### Positivas

- Validação de Access Token sem consulta ao banco
- Revogabilidade real via Refresh Token
- TTLs ajustados ao perfil (UX otimizada)
- Tenant no payload elimina lookup extra
- Padrão moderno e bem documentado
- Pronto para mobile futuro (mesma estratégia)

### Negativas

- Complexidade ligeiramente maior que sessions
- Frontend precisa gerenciar refresh automaticamente
- Refresh Token rotation exige cuidado (race conditions)
- Tabela de refresh_tokens cresce com sessões ativas

### Riscos

- **Risco:** Race condition no refresh (múltiplas requests simultâneas)
  - **Mitigação:** Frontend implementa "refresh queue" — se já está fazendo refresh, próximas requests aguardam.

- **Risco:** Vazamento de APP_KEY comprometer todos os JWTs
  - **Mitigação:** APP_KEY em vault. Rotação periódica (a cada 6 meses ou suspeita).

- **Risco:** Refresh Token roubado permanecer ativo até expiração
  - **Mitigação:** Rotação a cada uso (token roubado para de funcionar quando legítimo refresca).

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Surgir necessidade de integração OAuth2 com terceiros
- App mobile nativo for desenvolvido (validar adequação)
- Performance de Refresh exigir cache adicional
- Compliance exigir TTLs diferentes (auditoria, regulação)

---

## Referências

- ADRs relacionadas: `ADR-009` (Auth Module), `ADR-022` (Security), `ADR-023` (Password Crypto)
- Laravel Sanctum: https://laravel.com/docs/sanctum
- JWT spec: RFC 7519

---

## Notas de Implementação

- Library JWT: `firebase/php-jwt` ou Laravel Sanctum (com customizações)
- Service `TokenService` centraliza geração/validação:
  - `generateAccessToken(User $user, Tenant $tenant): string`
  - `generateRefreshToken(User $user): string` (retorna UUID, salva hash)
  - `validateAccessToken(string $token): ?array`
  - `refreshAccessToken(string $refreshToken): array`
  - `revokeRefreshToken(string $refreshToken): void`
- Middleware `AuthenticateJWT` valida Access Token em rotas protegidas
- Middleware `SetTenantContext` aplica tenant_id no Postgres a partir do JWT
- Frontend (React + Tanstack Query):
  - Interceptor automático para refresh em 401
  - Refresh queue para evitar race conditions
  - Logout em caso de refresh falhar
- Job `CleanupExpiredTokens` (diário) deleta tokens expirados do banco
- Tabela `refresh_tokens` com particionamento futuro se crescer demais
- Logs de auth eventos:
  - login_success, login_failed, refresh, logout, password_reset
  - Inclui ip_address e user_agent
- Mensagem de erro genérica em login falho ("E-mail ou senha inválidos")
