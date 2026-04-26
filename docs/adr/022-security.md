# ADR-022: Segurança Transversal

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Como SaaS multi-tenant que armazena dados de empresas brasileiras, lida com pagamentos e identifica indivíduos (corretores, clientes), a segurança não é opcional — é estrutural.

Pontos críticos a cobrir:

- **OWASP Top 10** — Vulnerabilidades web comuns
- **LGPD** — Lei Geral de Proteção de Dados brasileira
- **Vazamento entre tenants** — Risco amplificado em banco compartilhado
- **Incidentes** — Como detectar e responder
- **Defesa em profundidade** — Múltiplas camadas

A decisão precisa equilibrar:

- **Pragmatismo** (MVP não precisa de SOC2)
- **Mínimo aceitável** (não pode pular básico)
- **Conformidade legal** (LGPD é obrigação)
- **Custo** (não pode dobrar custo do projeto)

Esta ADR define o que é obrigatório no MVP e o que pode ser adicionado nas fases seguintes.

---

## Decisão

Implementar conjunto de práticas de segurança transversais cobrindo OWASP Top 10, conformidade LGPD básica, defesa em profundidade contra vazamento entre tenants, e plano mínimo de resposta a incidentes.

### Detalhamento

```
OWASP Top 10 (2021) — Cobertura no MVP
─────────────────────────────────────────

A01: Broken Access Control
  ├── Mitigação: Spatie Permission + Policies
  ├── RLS no banco (defesa final)
  └── Tests cobrem permissões

A02: Cryptographic Failures
  ├── HTTPS obrigatório (HSTS)
  ├── Senhas: Argon2id + Pepper (ADR-023)
  ├── Tokens: JWT assinado
  └── Dados sensíveis criptografados em repouso

A03: Injection
  ├── Eloquent ORM (parametrização automática)
  ├── Form Requests com validação
  ├── Sanitização de uploads
  └── CSP headers

A04: Insecure Design
  ├── Threat modeling em features críticas
  ├── Code review obrigatório (mesmo solo)
  └── Princípio do menor privilégio

A05: Security Misconfiguration
  ├── Headers de segurança configurados
  ├── APP_DEBUG=false em produção
  ├── Pastas sensíveis bloqueadas (Nginx)
  └── Versions ocultas

A06: Vulnerable and Outdated Components
  ├── composer audit no CI
  ├── npm audit no CI
  └── Dependabot ativado

A07: Identification and Authentication Failures
  ├── Rate limiting agressivo
  ├── Lock após 5 tentativas
  ├── Senhas fortes obrigatórias
  └── Refresh tokens revogáveis

A08: Software and Data Integrity Failures
  ├── Verificação de assinatura de webhooks
  ├── Imutabilidade de logs de auditoria
  └── HTTPS para tudo

A09: Security Logging and Monitoring
  ├── Sentry para erros
  ├── Logs estruturados
  ├── Audit log para ações sensíveis
  └── Alertas para anomalias

A10: Server-Side Request Forgery (SSRF)
  ├── Validação de URLs externas
  ├── Allowlist em vez de blocklist
  └── Sem fetch de URLs do usuário no MVP
```

```
Cabeçalhos HTTP de Segurança
─────────────────────────────────────────

Content-Security-Policy:
  default-src 'self';
  script-src 'self' 'nonce-{nonce}';
  style-src 'self' 'unsafe-inline';
  img-src 'self' data: https://cdn.seuapp.com.br;
  connect-src 'self';
  frame-ancestors 'none';

Strict-Transport-Security: 
  max-age=31536000; includeSubDomains; preload

X-Frame-Options: DENY

X-Content-Type-Options: nosniff

Referrer-Policy: strict-origin-when-cross-origin

Permissions-Policy:
  camera=(), microphone=(), geolocation=(self)
```

```
Cloudflare WAF (Free Plan)
─────────────────────────────────────────

Regras ativadas:
  ├── Bot fight mode
  ├── Browser Integrity Check
  ├── Challenge for known bad IPs
  ├── Always HTTPS
  └── HTTP/3

Regras customizadas:
  ├── Rate limit em /api/auth/login (5/min)
  ├── Rate limit em /api/cadastro (3/hour/IP)
  ├── Block requests sem User-Agent
  └── Challenge requests de países bloqueados
      (ajustável conforme padrões observados)
```

```
Rate Limiting na Aplicação
─────────────────────────────────────────

Definidos em RateLimiter:

api (geral):       60 req/min/user
auth-login:         5 req/min/IP + 5/min/email
auth-register:      3 req/hour/IP
auth-reset:         3 req/hour/email
public-search:    100 req/min/IP
webhook:           50 req/min (Pagar.me)

Estratégia:
  ├── Limit por IP para anônimos
  ├── Limit por user_id para autenticados
  ├── Resposta 429 com Retry-After
  └── Logs de eventos de rate limit
```

```
LGPD — Conformidade no MVP
─────────────────────────────────────────

Obrigatório no MVP:

1. Política de Privacidade clara e acessível
   ├── Link no footer de toda página
   ├── Versão atualizada datada
   └── Linguagem clara (não juridiquês)

2. Termos de Uso aceitos no cadastro
   ├── Checkbox obrigatório
   ├── Versão registrada (qual termo aceitou)
   └── Histórico de versões aceitas

3. Direitos do Titular dos Dados
   ├── Acesso aos próprios dados (export)
   ├── Correção de dados
   ├── Exclusão de conta
   ├── Portabilidade (export estruturado)
   └── Revogação de consentimento

4. DPO (Data Protection Officer)
   ├── E-mail dedicado: dpo@seuapp.com.br
   ├── Page com informações
   └── SLA de resposta documentado (15 dias)

5. Bases Legais Documentadas
   ├── Cliente final: consentimento + execução de contrato
   ├── Imobiliária: execução de contrato
   ├── Corretor: execução de contrato (vínculo com tenant)
   └── Documentar em política de privacidade

6. Tratamento Mínimo Necessário
   ├── Coletar apenas dados essenciais
   ├── Justificar campo a campo
   └── Excluir dados desnecessários

7. Subprocessadores Documentados
   ├── Lista pública de quem processa dados
   ├── Cloudflare, Neon, Resend, Pagar.me, R2
   └── Atualizada conforme adições
```

```
Defesa Contra Vazamento Entre Tenants
─────────────────────────────────────────

Camadas (do mais baixo ao mais alto):

1. Banco (PostgreSQL RLS)
   ├── Toda tabela de negócio tem RLS
   ├── Política bloqueia se app.tenant_id não setado
   ├── Usuário de aplicação sem BYPASSRLS
   └── Apenas Super Admin tem role com BYPASSRLS

2. Modelo (Eloquent)
   ├── BaseTenantModel aplica scope automático
   ├── Filtra por tenant_id da request
   └── Erros loud se tenant_id ausente

3. Controllers
   ├── Middleware obriga tenant resolvido
   ├── Form Requests validam tenant_id consistente
   └── Authorization via Policies

4. Cookies
   ├── Domain explícito do subdomínio
   ├── Não vazam entre subdomínios
   └── HttpOnly + Secure + SameSite

5. Storage
   ├── Paths começam com tenant_id
   ├── Signed URLs com expiração curta
   └── Sem listing público

6. E-mails
   ├── Templates referenciam tenant correto
   ├── Imagens carregadas do tenant correto
   └── Validação no envio

7. Cache (Redis)
   ├── Chaves prefixadas com tenant_id
   ├── TTL curto (60s)
   └── Invalidação ao mudar tenant
```

```
Upload Seguro de Arquivos
─────────────────────────────────────────

Validações em camadas:

1. Frontend (UX, não segurança real)
   ├── accept="image/jpeg,image/png,image/heic"
   ├── Tamanho máximo no input
   └── Preview antes de upload

2. Backend — Validação
   ├── Magic bytes (real type detection)
   │   ├── JPEG: FF D8 FF
   │   ├── PNG: 89 50 4E 47 0D 0A 1A 0A
   │   └── HEIC: 00 00 00 ?? 66 74 79 70
   ├── MIME type via finfo (PHP)
   ├── Tamanho < 10 MB
   └── Dimensões mínimas/máximas

3. Backend — Processamento
   ├── Re-encoding obrigatório (Intervention Image)
   ├── Strip EXIF (privacidade — geo)
   ├── Conversão para WebP
   └── Geração de versões redimensionadas

4. Storage
   ├── Bucket sem permissão de execução
   ├── Path com tenant_id
   ├── Servido via CDN (não direto)
   └── Hotlink protection
```

```
Auditoria
─────────────────────────────────────────

Tabela: audit_logs
  ├── id (uuid)
  ├── tenant_id (uuid, nullable)
  ├── user_id (uuid, nullable)
  ├── action (string)
  ├── resource_type (string)
  ├── resource_id (uuid)
  ├── changes (jsonb)
  ├── ip_address (string)
  ├── user_agent (string)
  ├── created_at (timestamp)
  └── (sem updated_at — imutável)

Eventos auditados (mínimo no MVP):
  ├── User.login_success / login_failed
  ├── User.password_changed
  ├── Tenant.created / suspended / reactivated
  ├── Subscription.upgraded / downgraded / canceled
  ├── SuperAdmin.login_as
  ├── Imovel.created / deleted
  └── Permissions.changed

Características:
  ├── Imutável (sem update/delete)
  ├── Retenção: indefinida no MVP
  └── Acesso: apenas Super Admin
```

```
Plano de Resposta a Incidentes (MVP)
─────────────────────────────────────────

Detecção:
  ├── Sentry alerta erros críticos
  ├── UptimeRobot detecta downtime
  ├── Logs de auth events (anomalias)
  └── Reporte de cliente

Classificação:
  ├── P0: Vazamento de dados, sistema down completo
  ├── P1: Bug crítico afetando muitos clientes
  ├── P2: Bug afetando alguns clientes
  └── P3: Bug menor

Resposta P0/P1:
  1. Confirmar incidente (sintomas reproduzidos)
  2. Communication: status page + email para afetados
  3. Containment: reverter deploy / disable feature flag
  4. Investigation: root cause analysis
  5. Fix + verify
  6. Post-mortem documentado

Comunicação LGPD (caso de vazamento):
  ├── Notificar ANPD em até 72h
  ├── Notificar clientes afetados
  └── Documentar fato e ações tomadas

Documentação:
  └── docs/runbooks/incident-response.md
```

```
Detecção de Anomalias (mínimo no MVP)
─────────────────────────────────────────

Regras simples:

1. Login bem-sucedido em IP novo
   └── Notificar usuário por e-mail

2. 10+ tentativas de login falhas em 1h
   └── Bloquear conta temporariamente
   └── Alertar admin

3. Mudança de senha sem trocar e-mail
   └── E-mail de notificação

4. Múltiplos refresh tokens criados em curto período
   └── Investigar (possível replay attack)

5. Acesso a dados de mais de 1 tenant em curto período
   └── Apenas Super Admin pode (mas auditar)

Implementação:
  ├── Listeners Laravel em eventos de auth
  ├── Jobs para análise periódica
  └── Alertas via e-mail
```

---

## Justificativa

A escolha do escopo se justifica por:

1. **Cobre obrigações legais (LGPD)** — Não é opcional
2. **Defesa em profundidade contra principal risco** (vazamento entre tenants)
3. **Implementação pragmática** — Sem ferramentas caras
4. **Crescimento incremental** — Mais sofisticação em fases posteriores
5. **OWASP Top 10 cobre maioria dos vetores reais**

Itens conscientemente postergados:
- **SOC2/ISO 27001** — Apenas quando enterprise demandar
- **Pentest profissional** — Orçamento $$$, fica para v2
- **Bug bounty** — Plataforma exige base de usuários
- **2FA** — Plano gratuito do produto não justifica complexidade ainda

---

## Alternativas Consideradas

### Alternativa A — Segurança Mínima (Apenas HTTPS)

- **Descrição:** HTTPS + autenticação básica, sem mais.
- **Pontos fortes:** Implementação rápida.
- **Pontos fracos:** LGPD não atendida. Risco real de vazamento.
- **Por que não foi escolhida:** Inaceitável para SaaS B2B brasileiro.

### Alternativa B — Auditoria SOC2 desde o MVP

- **Descrição:** Conformidade SOC2 completa.
- **Pontos fortes:** Pronto para enterprise.
- **Pontos fracos:** $20-50k em consultoria, 6+ meses.
- **Por que não foi escolhida:** Inviável para bootstrap. Adicionar quando justificar.

### Alternativa C — WAF Pago (Imperva, Cloudflare Pro)

- **Descrição:** WAF com mais regras e proteção.
- **Pontos fortes:** Defesa mais robusta.
- **Pontos fracos:** $$$.
- **Por que não foi escolhida:** Cloudflare Free cobre o essencial. Upgrade quando justificar.

---

## Consequências

### Positivas

- LGPD atendida no mínimo legal
- Defesa em profundidade contra vazamento entre tenants
- Auditoria de ações sensíveis disponível
- OWASP Top 10 coberto adequadamente
- Plano de resposta a incidentes documentado
- Custo de segurança mínimo

### Negativas

- Sem pentest profissional inicial
- Detecção de anomalias é básica
- Sem 2FA disponível para clientes
- Conformidade é "espírito da lei", não certificada

### Riscos

- **Risco:** Vulnerabilidade zero-day em pacote crítico
  - **Mitigação:** composer audit + npm audit em CI. Dependabot ativo. Atualizações regulares.

- **Risco:** Insider threat (acesso indevido pela própria equipe)
  - **Mitigação:** Audit log de Super Admin. Princípio do menor privilégio. Isolamento de credenciais.

- **Risco:** Phishing direcionado à equipe
  - **Mitigação:** Treinamento. 2FA em GitHub, Forge, Cloudflare, todos os SaaS críticos.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Cliente enterprise exigir certificação (SOC2, ISO 27001)
- Volume de dados pessoais justificar investimento maior
- Surgir incidente (post-mortem deve evoluir esta ADR)
- Compliance brasileiro evoluir (LGPD tem mudanças regulatórias)

---

## Referências

- ADRs relacionadas: `ADR-014` (Authentication), `ADR-023` (Password Crypto), `ADR-001` (Database)
- OWASP Top 10: https://owasp.org/Top10/
- LGPD: Lei nº 13.709/2018
- ANPD: https://www.gov.br/anpd

---

## Notas de Implementação

- Middleware `SecurityHeaders` aplica headers em toda response
- Event Listeners em eventos de auth registram audit log
- Service `AuditLogService` centraliza criação de logs
- Página `/privacy` e `/terms` com versionamento
- Página `/dpo` com informações de contato
- Endpoint `/api/me/export` retorna dados do usuário
- Endpoint `/api/me/delete` inicia processo de exclusão
- Job assíncrono executa exclusão (com retenção legal de 30 dias)
- Documentação de subprocessadores em `/subprocessors`
- Configurar Sentry para alertar em:
  - Errors 500+ em produção
  - Authentication failures > threshold
  - SQL injection attempts (logged separately)
- Validação de upload em `app/Services/UploadService.php`
- Magic bytes em `app/Support/FileTypeDetector.php`
- Lista de subprocessadores em config:
  - Cloudflare (CDN/DNS)
  - Neon (database)
  - Resend (email)
  - Pagar.me (payment)
  - Cloudflare R2 (storage)
  - Hetzner (hosting)
  - Sentry (error tracking)
