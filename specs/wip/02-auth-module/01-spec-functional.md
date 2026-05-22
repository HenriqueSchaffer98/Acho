# Spec Funcional: Módulo de Autenticação
**Versão:** 1.0
**Status:** approved
**Discovery:** specs/wip/02-auth-module/discovery.md

## Contexto

A plataforma possui 3 contextos de autenticação distintos: vitrine pública do tenant (clientes finais), subdomínio do tenant (admins e corretores) e painel super admin (operadores da plataforma). Sem um módulo de auth funcional, nenhum outro módulo pode ser entregue. Este módulo provê a infraestrutura de sessão (JWT + Refresh Token), os fluxos de entrada/saída e os mecanismos de recuperação e convite.

## Usuários e Benefício

- **Admin do tenant** — acessa o painel Filament da imobiliária com sessão segura de 8h
- **Corretor** — acessa o painel como colaborador com sessão de 12h
- **Cliente final** — se cadastra e faz login na vitrine para agendar visitas, sessão de 30 dias
- **Super Admin** — acessa o painel operacional com sessão de 4h

## Comportamento Esperado

---

**Fluxo 1: Login no contexto do tenant (admin/corretor/cliente)**

```
DADO que o usuário está no subdomínio de um tenant ativo
E possui conta ativa com e-mail e senha válidos
QUANDO submete e-mail + senha no formulário de login
ENTÃO recebe Access Token JWT (15min) no body da resposta
E recebe Refresh Token em cookie HttpOnly com TTL do seu perfil
E recebe dados básicos do usuário (nome, role)
E é redirecionado para o dashboard do seu perfil
```

```
DADO que o usuário tenta login com credenciais incorretas
QUANDO submete e-mail ou senha inválidos
ENTÃO recebe mensagem genérica "E-mail ou senha inválidos"
E o sistema NÃO informa qual campo está errado
E registra a tentativa falha (ip_address, user_agent)
```

```
DADO que o usuário atingiu 5 tentativas falhas em 15 minutos
QUANDO tenta novo login
ENTÃO recebe mensagem informando que a conta está bloqueada temporariamente
E o sistema rejeita novas tentativas pelos próximos 15 minutos
```

```
DADO que o usuário fez login de um IP diferente do habitual
QUANDO o login é bem-sucedido
ENTÃO o sistema dispara notificação de e-mail informando o login em IP novo (via Job assíncrono)
E a falha no envio do e-mail NÃO bloqueia o login
```

---

**Fluxo 2: Login no contexto super admin**

```
DADO que o operador está no domínio do super admin
QUANDO submete e-mail + senha válidos de super admin
ENTÃO recebe Access Token com role "super_admin" e TTL de 4h no Refresh Token
E é redirecionado para o painel operacional
```

---

**Fluxo 3: Refresh de access token**

```
DADO que o Access Token do usuário expirou (após 15min)
E o Refresh Token no cookie ainda é válido e não foi revogado
QUANDO o frontend detecta o 401 e chama o endpoint de refresh
ENTÃO o sistema valida o hash do Refresh Token no banco
E gera novo Access Token (15min) e novo Refresh Token
E invalida o Refresh Token anterior
E retorna o novo Access Token no body
E atualiza o cookie com o novo Refresh Token
```

```
DADO que o Refresh Token foi revogado ou expirou
QUANDO o frontend tenta refresh
ENTÃO o sistema retorna 401
E o frontend limpa o estado de auth e redireciona para login
```

```
DADO que chega um refresh com token já rotacionado (possível replay attack)
QUANDO o sistema detecta que o token foi usado anteriormente
ENTÃO revoga TODOS os Refresh Tokens da família do usuário no tenant
E retorna 401 forçando re-login
```

---

**Fluxo 4: Logout**

```
DADO que o usuário está autenticado
QUANDO aciona o logout
ENTÃO o sistema revoga o Refresh Token no banco (marca revoked = true)
E apaga o cookie HttpOnly
E o frontend limpa o Access Token da memória
E o usuário é redirecionado para a vitrine pública
```

---

**Fluxo 5: Recuperação de senha — solicitação**

```
DADO que o usuário esqueceu a senha
QUANDO informa o e-mail no formulário de recuperação
ENTÃO o sistema sempre responde com a mesma mensagem de sucesso
   (independente de o e-mail existir ou não — evita enumeração)
E SE o e-mail existir no tenant atual, envia link com token JWT (1h, uso único)
```

---

**Fluxo 6: Recuperação de senha — uso do link**

```
DADO que o usuário recebeu o link de reset e o token ainda é válido
QUANDO acessa o link e define nova senha
ENTÃO o sistema valida a força da senha
E atualiza a senha com Argon2id + Pepper
E invalida TODOS os Refresh Tokens ativos do usuário
E confirma a troca com e-mail de notificação
E autentica o usuário com nova sessão completa
```

```
DADO que o link de reset já foi usado ou está expirado (> 1h)
QUANDO o usuário tenta acessá-lo
ENTÃO recebe mensagem de link inválido ou expirado
E é orientado a solicitar novo link
```

---

**Fluxo 7: Mudança de senha (usuário logado)**

```
DADO que o usuário está autenticado
QUANDO informa senha atual + nova senha no formulário
ENTÃO o sistema valida a senha atual
E valida a força da nova senha
E atualiza a senha com Argon2id + Pepper
E invalida todas as outras sessões (Refresh Tokens) exceto a atual
E envia e-mail de confirmação de troca
```

---

**Fluxo 8: Convite de corretor**

```
DADO que o Admin do tenant está autenticado
QUANDO envia convite informando e-mail do corretor
ENTÃO o sistema gera JWT de convite (48h, claim purpose: "invite", role: "corretor", tenant_id)
E envia e-mail com link de aceite ao corretor
```

```
DADO que o corretor recebeu o link de convite e o token é válido (< 48h)
QUANDO acessa o link e define sua senha
ENTÃO o sistema cria a conta do corretor vinculada ao tenant
E faz login automático com sessão completa
E redireciona para o painel do corretor
```

```
DADO que o link de convite está expirado (> 48h)
QUANDO o corretor tenta acessá-lo
ENTÃO recebe mensagem de convite expirado
E é orientado a pedir novo convite ao admin
```

---

**Fluxo 9: Cadastro de cliente final (vitrine)**

```
DADO que um visitante está na vitrine de um tenant
QUANDO preenche nome, e-mail, senha e telefone e aceita os termos
ENTÃO o sistema valida a força da senha
E cria a conta vinculada ao tenant atual
E faz login automático com sessão de 30 dias
E redireciona para a vitrine com estado autenticado
```

---

## Contrato de Interface

| Endpoint | Método | Contexto |
|---|---|---|
| `/auth/login` | POST | tenant + super admin |
| `/auth/logout` | POST | todos |
| `/auth/refresh` | POST | todos |
| `/auth/forgot-password` | POST | tenant |
| `/auth/reset-password` | POST | tenant |
| `/auth/change-password` | POST | tenant (logado) |
| `/auth/register` | POST | vitrine (cliente final) |
| `/admin/corretores/convite` | POST | admin tenant |
| `/auth/convite/aceitar` | POST | corretor (via link) |

**Política de senha:**
- Mínimo 8 caracteres
- Pelo menos 1 letra e 1 número
- Não estar em lista de senhas vazadas (`Password::uncompromised()`)

**Erros tratados:**
- 401: credenciais inválidas (mensagem genérica)
- 422: validação de input (campos obrigatórios, formato)
- 429: rate limit / lock de tentativas
- 410: token expirado ou já usado (reset, convite)

## Casos de Borda

- [ ] Login com e-mail de tenant diferente do subdomínio atual → 401 genérico
- [ ] Refresh com token já rotacionado (replay attack) → revoga família inteira de tokens do usuário
- [ ] Múltiplas requests simultâneas de refresh → frontend implementa refresh queue
- [ ] Usuário solicita reset mas já tem reset pendente → novo token invalida o anterior
- [ ] Corretor já tem conta no tenant e admin tenta convidá-lo → erro informativo
- [ ] Cliente tenta cadastrar com e-mail já existente no tenant → erro informativo
- [ ] Convite aceito mais de uma vez → 410 no segundo uso

## Critérios de Aceite

- [ ] AC-01: Login retorna JWT com `user_id`, `tenant_id`, `role` e Refresh Token em cookie HttpOnly
- [ ] AC-02: Refresh Token é rotacionado a cada uso — token anterior rejeitado imediatamente
- [ ] AC-03: TTLs corretos por perfil: Admin 8h / Corretor 12h / Cliente 30d / Super Admin 4h
- [ ] AC-04: 5 tentativas falhas bloqueiam login por 15min com mensagem adequada
- [ ] AC-05: Mensagem de erro de login é genérica — não distingue e-mail ou senha
- [ ] AC-06: Reset de senha invalida todos os Refresh Tokens ativos do usuário
- [ ] AC-07: Link de reset expira em 1h e é de uso único
- [ ] AC-08: Convite de corretor expira em 48h e é de uso único
- [ ] AC-09: Logout revoga o Refresh Token no banco
- [ ] AC-10: Cadastro de cliente faz login automático com sessão de 30 dias
- [ ] AC-11: Notificação de e-mail disparada (assíncrona, não bloqueante) em login com IP novo
- [ ] AC-12: Todos os fluxos passam em Larastan nível 8 e Pint

## Fora do Escopo

- Login social (Google, Facebook, Apple)
- Magic links (login sem senha)
- 2FA
- SSO enterprise
- Recuperação por SMS
- Cadastro de imobiliária (ADR-011)
- "Login as" do Super Admin (ADR-010 — módulo separado)
