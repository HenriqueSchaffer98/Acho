# ADR-023: Criptografia de Senhas

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A criptografia de senhas é uma das decisões de segurança mais críticas em qualquer aplicação. Em SaaS multi-tenant com banco compartilhado, o risco é amplificado:

- Um vazamento de banco expõe senhas de TODOS os tenants
- Recovery de senhas vazadas exige notificar TODOS os usuários
- Custo de incidente é proporcional ao número de tenants

A escolha precisa cobrir:

- **Algoritmo de hash** (bcrypt, scrypt, Argon2)
- **Parâmetros de custo** (resistente a hardware atual)
- **Proteções adicionais** (pepper, salt único)
- **Política de senhas** (força, validação)
- **Re-hash transparente** (atualizar hashes antigos)
- **Detecção de anomalias** (senhas vazadas, brute force)

A escolha errada hoje resulta em vulnerabilidade que só se manifesta quando é tarde demais.

---

## Decisão

Adotar **Argon2id** com pepper global, política de senhas fortes validadas contra base de senhas vazadas (HaveIBeenPwned), re-hash automático em mudança de parâmetros, e detecção ativa de anomalias.

### Detalhamento

```
Algoritmo: Argon2id
─────────────────────────────────────────

Por que Argon2id?
  ├── Vencedor do Password Hashing Competition (2015)
  ├── Resistente a ataques GPU/ASIC
  ├── Resistente a side-channel attacks (variant 'id')
  ├── Recomendado por OWASP, NIST, RFC 9106
  └── Suporte nativo em PHP 7.2+

Por que não bcrypt:
  └── Algoritmo mais antigo (1999)
  └── Vulnerável a ASICs especializados
  └── Limite de 72 bytes
  └── Argon2id é evolução clara

Por que não scrypt:
  └── Argon2 superou scrypt em design
  └── Argon2id tem melhor resistência a side-channel
```

```
Parâmetros do Argon2id
─────────────────────────────────────────

Configuração:
  memory_cost = 65536  (64 MB)
  time_cost   = 4      (4 iterações)
  threads     = 1      (single-threaded)

Justificativa:

Memory (64 MB):
  ├── Resistência a ataques GPU
  ├── GPUs típicas têm pouco RAM por core
  ├── Forçar 64 MB inviabiliza GPU clusters
  └── Aceitável para servidor (single thread)

Time (4 iterações):
  ├── Hash leva ~150-300ms em CPU moderna
  ├── Lento o suficiente para frustrar brute force
  ├── Rápido o suficiente para UX (login)
  └── Equilibra defesa vs experiência

Threads (1):
  ├── Single-threaded para previsibilidade
  ├── Não compete com outros processes
  └── Threads múltiplas abrem janela para timing attacks

Validação Empírica:
  └── Tempo de hash em produção: ~200ms
  └── Aceitável: usuário não percebe
  └── Inaceitável: > 1 segundo (ajustar para baixo)
```

```
Pepper Global
─────────────────────────────────────────

O que é Pepper?
  └── String secreta adicionada à senha ANTES do hash
  └── Igual para todas as senhas (vs salt que é único)
  └── Armazenada FORA do banco (vault)

Implementação:

Função:
  hash = Argon2id(password + pepper, salt)

Onde fica o pepper:
  ├── Variável de ambiente: APP_PEPPER
  ├── Vault de produção (Forge env)
  ├── NUNCA no banco
  ├── NUNCA no repositório
  └── Backup criptografado em local seguro

Por que Pepper?
  ├── Defesa adicional se BANCO vazar
  ├── Atacante precisa banco + pepper para crackear
  ├── Pepper fora do banco = harder to leak
  └── Mesmo com hash quebrado, sem pepper inútil

Risco:
  └── Perda do pepper = todos não conseguem logar
  └── Mitigação: backup em múltiplos locais seguros
```

```
Política de Senhas
─────────────────────────────────────────

Requisitos Obrigatórios:

1. Mínimo 8 caracteres
   └── NIST SP 800-63B recomenda 8 mínimo

2. Pelo menos 1 letra
   └── Reduz senhas puramente numéricas

3. Pelo menos 1 número
   └── Aumenta espaço de busca

4. Não estar em lista de senhas vazadas
   ├── HaveIBeenPwned API (k-anonymity)
   ├── Validação em tempo real
   └── Bloquia senhas no top 1 milhão de vazadas

5. Não conter dados pessoais óbvios
   ├── Não conter o e-mail
   ├── Não conter o nome do usuário
   └── Não ser palavras óbvias ("senha", "123456")

NÃO exigir:
  ❌ Caracteres especiais obrigatórios
       (NIST recomenda contra — frustra usuário)
  ❌ Mudança periódica
       (NIST recomenda contra — leva a senhas piores)
  ❌ Maiúsculas E minúsculas
       (frustração maior que segurança real)

Por que NIST recomenda contra regras complexas:
  └── Estudos mostram que regras complexas levam a:
      ├── Padrões previsíveis (Senha123!)
      ├── Reuso de senhas
      └── Anotações em locais inseguros
  └── Comprimento + verificação de vazamento >> complexidade
```

```
Validação de Senhas Vazadas (HaveIBeenPwned)
─────────────────────────────────────────

API: https://api.pwnedpasswords.com/range/{hash5}

Funcionamento (k-anonymity):
  1. Hash SHA-1 da senha do usuário
  2. Pegar primeiros 5 caracteres
  3. Enviar APENAS esses 5 caracteres
  4. API retorna lista de hashes que começam com esses 5
  5. Verificar se hash completo está na lista
  6. Sem enviar senha real para fora

Vantagens:
  ├── Privacidade preservada (k-anonymity)
  ├── Gratuita
  ├── Cobre 700+ milhões de senhas vazadas
  └── Atualização contínua

Implementação no Laravel:
  └── Rule "Password::min(8)->uncompromised()"
  └── Já implementado, só configurar
```

```
Re-hash Automático
─────────────────────────────────────────

Cenário:
  └── Hoje uso Argon2id com 64MB
  └── Daqui 2 anos: GPUs mais rápidas
  └── Aumentar para 128MB
  └── Mas usuários antigos têm hash com 64MB

Solução:
  ├── Em todo login bem-sucedido:
  │   ├── Verificar parâmetros do hash atual
  │   ├── Comparar com configuração atual
  │   └── Se diferentes: re-hash transparente

Implementação Laravel:
  └── password_needs_rehash() nativo
  └── Atualizar hash no banco no login
  └── Usuário não percebe

Vantagens:
  ├── Migração transparente para usuário
  ├── Não força reset de senhas
  └── Aumenta segurança gradualmente
```

```
Detecção de Anomalias em Auth
─────────────────────────────────────────

Eventos que disparam alertas:

1. Brute Force Detection
   ├── 5 tentativas falhas em 15min/IP
   │   └── Bloqueio temporário (15min)
   ├── 10 tentativas falhas em 1h/email
   │   └── Bloqueio da conta (admin para reativar)
   └── 100 tentativas falhas em 1h/IP
       └── Block permanente do IP via Cloudflare

2. Login em IP Novo
   └── Notificar usuário por e-mail
   └── "Foi você? Caso não, troque sua senha"

3. Login Geograficamente Improvável
   └── Login em 2 países em 1 hora
   └── Forçar re-autenticação
   └── Alertar usuário

4. Múltiplos Refresh Tokens em Curto Período
   └── 10+ tokens em 1h pode indicar replay attack
   └── Investigar e potencialmente revogar todos

5. Senha Reset Sem Login Após
   └── Reset solicitado mas não usado em 24h
   └── Pode indicar tentativa de takeover
   └── Logar para auditoria

Implementação:
  ├── Listeners em eventos Laravel
  ├── Jobs assíncronos para análise
  ├── Tabela auth_anomalies para tracking
  └── Alertas via e-mail e Sentry
```

```
Estrutura no Banco
─────────────────────────────────────────

Tabela: users
  ├── password (varchar 255)        ← hash Argon2id
  ├── password_changed_at (timestamp)
  ├── password_history (jsonb)      ← últimos 5 hashes
  └── failed_login_attempts (int)

Por que histórico de 5 senhas?
  └── Impede reuso recente (boa prática)
  └── 5 é equilíbrio comum
  └── Cada hash com salt único

Tabela: auth_anomalies
  ├── id (uuid)
  ├── user_id (uuid, nullable)
  ├── event_type (string)
  ├── severity (low, medium, high)
  ├── ip_address
  ├── user_agent
  ├── metadata (jsonb)
  └── created_at

Tabela: password_resets (Laravel padrão)
  ├── email
  ├── token (hashed)
  └── created_at
```

---

## Justificativa

A escolha por Argon2id + Pepper se justifica por:

1. **Argon2id é o estado da arte** — Vencedor de competição internacional
2. **Banco compartilhado amplifica risco** — Defesa extra é proporcional ao risco
3. **Pepper protege contra vazamento isolado de banco** — Camada adicional sem custo
4. **Senhas vazadas causam mais incidentes que senhas fracas** — Validação HIBP cobre isso
5. **Re-hash transparente permite evolução** — Sem dor para usuários

A escolha consciente:
- **NÃO exigir caracteres especiais** — NIST recomenda contra
- **NÃO forçar troca periódica** — NIST recomenda contra
- **Validar contra base vazada** — Mais valor real que regras complexas

---

## Alternativas Consideradas

### Alternativa A — bcrypt (Padrão Laravel)

- **Descrição:** Algoritmo padrão do Laravel.
- **Pontos fortes:** Bem estabelecido, simples.
- **Pontos fracos:** Vulnerável a ASICs, limite de 72 bytes.
- **Por que não foi escolhida:** Argon2id é evolução clara. PHP 8.3 suporta nativamente.

### Alternativa B — Argon2id Sem Pepper

- **Descrição:** Argon2id com salt mas sem pepper.
- **Pontos fortes:** Mais simples.
- **Pontos fracos:** Sem proteção adicional contra vazamento de banco.
- **Por que não foi escolhida:** Pepper é proteção barata e significativa em multi-tenant.

### Alternativa C — Argon2id com Parâmetros Mais Pesados

- **Descrição:** memory=128MB, time=8.
- **Pontos fortes:** Defesa mais forte.
- **Pontos fracos:** Login lento (>1s), pode degradar UX.
- **Por que não foi escolhida:** 64MB já é considerado forte. Pode-se aumentar via re-hash.

### Alternativa D — Magic Links (sem senhas)

- **Descrição:** Eliminar senhas, login só por link de e-mail.
- **Pontos fortes:** Sem problema de senhas.
- **Pontos fracos:** Depende 100% de e-mail funcionar.
- **Por que não foi escolhida:** Postergada para v2 como alternativa opcional. Senhas são padrão hoje.

---

## Consequências

### Positivas

- Estado da arte em proteção de senhas
- Pepper protege contra vazamento isolado de banco
- Validação contra senhas vazadas reduz risco real
- Re-hash automático permite evolução
- Detecção de anomalias adiciona camada
- NIST-compliant

### Negativas

- Login com Argon2id é ~200ms (vs ~50ms bcrypt)
- Pepper exige cuidado adicional (vault, backup)
- Validação HIBP adiciona dependência externa (gateway)
- Detecção de anomalias gera complexidade adicional

### Riscos

- **Risco:** Pepper vazado torna defesa inútil
  - **Mitigação:** Vault separado do código. Backup criptografado offline. Rotação possível (com migration).

- **Risco:** Argon2id muito lento causar timeout
  - **Mitigação:** Parâmetros calibrados para ~200ms. Monitorar no Sentry. Ajustar se necessário.

- **Risco:** API HIBP cair e bloquear cadastros
  - **Mitigação:** Timeout curto (3s). Fallback para regex de senhas óbvias. Não bloquear cadastro se API down.

- **Risco:** Re-hash automático adicionar latência ao login
  - **Mitigação:** Re-hash assíncrono (job). Login retorna imediatamente.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Algoritmo melhor que Argon2id surgir e ser auditado
- Hardware quântico ameaçar primitivas atuais (não no MVP)
- 2FA virar obrigatório (compliance, demanda enterprise)
- Magic links se estabelecerem como padrão de mercado

---

## Referências

- ADRs relacionadas: `ADR-009` (Auth Module), `ADR-014` (Authentication), `ADR-022` (Security)
- RFC 9106 — Argon2 Spec
- NIST SP 800-63B — Digital Identity Guidelines
- HaveIBeenPwned: https://haveibeenpwned.com/Passwords
- OWASP Password Storage Cheat Sheet

---

## Notas de Implementação

- Configuração em `config/hashing.php`:
  ```php
  'driver' => 'argon2id',
  'argon' => [
      'memory' => 65536,      // 64 MB
      'threads' => 1,
      'time' => 4,
  ],
  ```
- Pepper em `.env`:
  - `APP_PEPPER=...` (gerado randomicamente, 32+ chars)
  - Backup do APP_PEPPER em vault separado
- Trait `HashesPasswordWithPepper` no User model:
  - Override do método `setPasswordAttribute`
  - Aplica pepper antes do hash
- Rule `app/Rules/StrongPassword.php`:
  - Combina min length, uncompromised, custom checks
  - Usado em FormRequests
- Listener `RehashPasswordOnLogin`:
  - Verifica password_needs_rehash
  - Dispara job assíncrono para re-hash
- Listener `DetectAuthAnomaly`:
  - Aplicado em LoginSucceeded, LoginFailed
  - Cria registros em auth_anomalies
- Service `AuthAnomalyService`:
  - Métodos para análise de padrões
  - Geração de alertas
- Migration: tabela `auth_anomalies`
- Seed: nada (não cria usuários com senha conhecida)
- Tests:
  - Hash Argon2id realmente usado
  - Pepper aplicado corretamente
  - Senhas vazadas rejeitadas
  - Brute force bloqueia após threshold
- Documentação para usuário:
  - Política de senhas explicada na tela de cadastro
  - Mensagens de erro claras (mas não específicas a ponto de vazar info)
