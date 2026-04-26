# ADR-009: Módulo de Autenticação

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

O módulo de autenticação cobre as funcionalidades básicas de cadastro, login e recuperação de senha visíveis ao usuário final. É distinto da estratégia técnica de tokens e sessões (tratada em ADR-014).

A escolha do escopo do módulo no MVP afeta:

- Tempo de entrega
- Experiência do usuário (UX de cadastro/login)
- Complexidade de implementação
- Cobertura de casos de uso esperados pelo mercado

Em SaaS modernos, o usuário espera fluxos como login social (Google, Facebook), 2FA, magic links, etc. Decidir o que entra e o que fica para v2 é crítico para entregar um MVP funcional sem sobrecarregar o desenvolvimento.

---

## Decisão

Implementar autenticação básica e robusta no MVP com e-mail/senha como método único, deixando login social, 2FA e magic links para v2.

### Detalhamento

```
Funcionalidades de Autenticação no MVP
─────────────────────────────────────────

1. Cadastro de Cliente Final (vitrine)
   ├── Auto-cadastro pela vitrine pública
   ├── Campos: nome, e-mail, senha, telefone
   ├── Aceite de termos e política de privacidade
   ├── Validação de senha forte
   └── Login automático após cadastro

2. Cadastro de Imobiliária (landing page)
   ├── Tratado em ADR-011 (Onboarding)
   └── Resulta em criação de tenant + usuário admin

3. Login
   ├── E-mail + senha
   ├── Validação no contexto do subdomínio
   ├── Mensagem de erro genérica (não vaza info)
   ├── Lock após 5 tentativas (15 minutos)
   └── Notificação de login em IP novo

4. Logout
   ├── Invalida refresh token no servidor
   ├── Limpa sessão do navegador
   └── Redireciona para vitrine pública

5. Recuperação de Senha
   ├── Solicitação por e-mail
   ├── Link com token (1h validade)
   ├── Token único, uso único
   ├── Invalida outras sessões ao trocar senha
   └── E-mail de notificação após mudança

6. Convite de Corretor
   ├── Admin envia convite por e-mail
   ├── Token JWT válido por 48h
   ├── Corretor define senha no primeiro acesso
   ├── Conta criada já vinculada ao tenant
   └── Logado automaticamente após aceitar

7. Mudança de Senha (logado)
   ├── Exige senha atual
   ├── Validação de força
   ├── Invalida outras sessões
   └── E-mail de confirmação
```

```
Funcionalidades FORA do MVP (v2)
─────────────────────────────────────────

❌ Login social (Google, Facebook, Apple)
❌ Magic links (login sem senha)
❌ 2FA (autenticação de dois fatores)
❌ SSO para clientes enterprise
❌ Login com biometria (mobile)
❌ Recuperação por SMS
❌ Lembrar dispositivo (sessão estendida confiável)
```

### Política de Senha

```
Requisitos de senha forte:
  ├── Mínimo 8 caracteres
  ├── Pelo menos 1 letra
  ├── Pelo menos 1 número
  ├── Não estar em lista de senhas vazadas
  │   (Laravel: Password::min(8)->uncompromised())
  └── Não conter dados pessoais óbvios
      (e-mail, nome, "senha", "123456")

Mensagens ao usuário:
  ├── Mínimo 8 caracteres ✅
  ├── Não incluir dicas específicas (segurança)
  └── Erro genérico se rejeitada ("Senha não atende aos requisitos")
```

### Tela de Login

```
Layout adaptado por contexto:

Vitrine pública (cliente):
  ├── Modal sobreposto na vitrine
  ├── Aba "Login" e aba "Criar conta"
  ├── Campo: e-mail, senha
  ├── Link: "Esqueci minha senha"
  └── Botão "Entrar"

Painel admin (admin/corretor):
  ├── Página dedicada (/login)
  ├── Apenas formulário de login
  ├── Sem opção de cadastro (apenas convite)
  └── Identidade visual do tenant aplicada
```

---

## Justificativa

A escolha do escopo se justifica por:

1. **Cobertura essencial sem complexidade** — E-mail/senha cobre 95% dos casos
2. **Implementação rápida** — Laravel + Sanctum resolve naturalmente
3. **Segurança robusta sem features avançadas** — Argon2id + lock + recuperação cobre o crítico
4. **Login social pode esperar** — Não bloqueia validação do produto, adiciona complexidade
5. **2FA é overkill no MVP** — Adicionar depois quando justificar (clientes enterprise)

A política de senha forte é não-negociável porque:
- Banco compartilhado amplifica risco de vazamento
- Senhas fracas são o vetor #1 de ataque
- Validação contra lista de senhas vazadas é gratuita (HaveIBeenPwned)

---

## Alternativas Consideradas

### Alternativa A — Login Social Desde o MVP

- **Descrição:** Permitir login com Google/Facebook desde o início.
- **Pontos fortes:** Reduz fricção, aumenta conversão.
- **Pontos fracos:** Requer configuração OAuth, dependência externa, edge cases.
- **Por que não foi escolhida:** Adiciona 3-5 dias de dev. E-mail/senha é o caminho mais previsível para validação.

### Alternativa B — Magic Links (sem senha)

- **Descrição:** Login apenas por link enviado por e-mail (sem senha).
- **Pontos fortes:** Sem senhas para esquecer, UX moderna.
- **Pontos fracos:** Depende 100% de e-mail funcionando, edge cases (spam, atraso).
- **Por que não foi escolhida:** Modelo ainda em adoção, fricção em alguns casos. E-mail/senha é universal.

### Alternativa C — 2FA Obrigatório Para Admins

- **Descrição:** Forçar 2FA para todos os admins de imobiliária.
- **Pontos fortes:** Segurança máxima.
- **Pontos fracos:** Fricção alta no onboarding, complexidade de implementação.
- **Por que não foi escolhida:** Pode ser opcional na v2. Senha forte + Argon2id é suficiente no MVP.

### Alternativa D — Recuperação de Senha por SMS

- **Descrição:** Permitir reset de senha via código SMS.
- **Pontos fortes:** Funciona se cliente não lembrar do e-mail.
- **Pontos fracos:** Custo (~R$ 0,15/SMS), complexidade.
- **Por que não foi escolhida:** E-mail é universal e gratuito. SMS pode entrar na v2.

---

## Consequências

### Positivas

- Implementação direta e bem documentada (Laravel padrão)
- Segurança robusta com mínimo de complexidade
- Fluxos previsíveis e testáveis
- Compatível com qualquer dispositivo/navegador
- Zero dependência de provedor externo (OAuth)

### Negativas

- Sem opção de login social (mais fricção que mercado moderno)
- Sem 2FA disponível (limita clientes enterprise)
- Recuperação depende 100% de e-mail funcionar
- Magic links seriam mais "modernos"

### Riscos

- **Risco:** Cliente esquecer senha e não receber e-mail (spam)
  - **Mitigação:** Configurar SPF/DKIM/DMARC corretamente. Monitorar deliverability.

- **Risco:** Brute force em rotas de login
  - **Mitigação:** Rate limiting (5 tentativas / 15 min) + Cloudflare WAF.

- **Risco:** Token de convite/reset interceptado
  - **Mitigação:** TTL curto (48h convite, 1h reset). HTTPS obrigatório. Token uso único.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- 3+ clientes solicitarem login social como crítico
- Cliente enterprise exigir 2FA como requisito de compliance
- Taxa de abandono em cadastro for alta (login social pode ajudar)
- Magic links se tornarem padrão consolidado no setor

---

## Referências

- ADRs relacionadas: `ADR-014` (Authentication detalhes técnicos), `ADR-022` (Security), `ADR-023` (Password Crypto)
- Laravel Auth: https://laravel.com/docs/authentication
- Laravel Sanctum: https://laravel.com/docs/sanctum

---

## Notas de Implementação

- Usar Laravel Sanctum para tokens de API
- Form Requests específicos para cada operação (LoginRequest, RegisterRequest, etc.)
- Eventos: `UserRegistered`, `UserLoggedIn`, `PasswordReset`, `PasswordChanged`
- Listener envia notificação de login em IP novo
- Tabela `password_reset_tokens` (Laravel padrão)
- Tabela `invite_tokens` para convites de corretor
- Política de senha definida em `app/Rules/StrongPassword.php`
- Lock de tentativas via Laravel RateLimiter
- Mensagem genérica de erro: "E-mail ou senha inválidos" (não distingue qual)
- E-mail único por tenant (não global) — validação considera `tenant_id`
