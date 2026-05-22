# Discovery: Módulo de Onboarding Automatizado de Imobiliárias
**Data:** 2026-05-21

## Problema

Implementar o **Módulo de Onboarding Automatizado de Imobiliárias** conforme ADR-011 — todo o fluxo self-service desde a landing pública até o primeiro acesso autenticado no subdomínio do tenant. Sem este módulo, a única forma de criar tenants na plataforma é via seeder, o que inviabiliza o modelo de SaaS B2B (ADR-002). O módulo cobre cadastro com validação de CNPJ (numérico + alfanumérico pós-jul/2026), provisionamento instantâneo de subdomínio (ADR-016), criação atômica de Tenant + User admin + plano trial de 14 dias (ADR-012), token JWT `purpose: 'first_access'` (reaproveitando o `TokenService` já entregue na ADR-014), e-mail de boas-vindas, e onboarding guiado pós-login (checklist). Inclui também os artefatos transversais necessários: rate limiting `auth-register`, versionamento de termos para LGPD (ADR-022), e tabela `plans` com seeder dos 5 planos.

## Usuários e Benefício

**Imobiliária prospect (futuro cliente B2B):** acessa a landing pública (`acho.com.br`), preenche o formulário e em segundos tem subdomínio próprio rodando com trial de 14 dias gratuitos sem cartão. **Benefício:** elimina fricção de aquisição — produto funcionando em minutos, sem ligação comercial nem espera de provisionamento manual.

**Admin da imobiliária (responsável pelo cadastro):** recebe e-mail de boas-vindas, faz primeiro acesso via link único (JWT 15min), é guiado por checklist visual no painel (logo, cor, 1º imóvel, 1º corretor, horários, bairros). **Benefício:** percebe valor rápido durante o trial, reduzindo drop-off precoce.

**Equipe Acho (operação):** não precisa intervir manualmente em nenhum cadastro novo. **Benefício:** modelo escala desde o dia 1 sem custo operacional incremental por cliente.

**Sistema (downstream):** Onboarding entrega o pré-requisito para todos os módulos seguintes (Admin Tenant, Vitrine, Agendamento, Pagamento) — destrava o caminho crítico.

## Critério de Sucesso

Critérios técnicos verificáveis:

- Visitante consegue cadastrar imobiliária em `acho.test/cadastro` e em < 5 segundos o tenant está provisionado no banco com plano trial e `trial_ends_at = now() + 14 dias`.
- Validação de CNPJ aceita ambos os formatos (numérico clássico e alfanumérico pós-jul/2026) e rejeita inválidos com mensagem clara.
- Endpoint `GET /api/subdomain/check?slug=xxx` responde sob 100ms indicando disponibilidade (livre / em uso / reservado).
- Slug é sugerido automaticamente a partir da razão social (remove "ltda/me/eireli/sa", acentos, max 30 chars).
- Após submit do form bem-sucedido: tela "Verifique seu e-mail" + e-mail entregue ao Mailpit (dev) com link `https://{slug}.acho.test/auth/first-access?token=...`.
- Click no link com token válido (≤ 15min) cria sessão no subdomínio do tenant e redireciona para checklist.
- Click no link com token expirado/inválido retorna 410 com mensagem "Link expirado, solicite um novo".
- Rate limiting `auth-register: 3 req/hour/IP` rejeita 4ª tentativa com 429.
- Tentativa de cadastro com CNPJ ou subdomínio já existente retorna 422 com erro específico.
- Tentativa de cadastro com subdomínio reservado (`admin`, `www`, `api`, etc.) retorna 422.
- Transação atômica: se qualquer passo da criação falhar, nenhum registro fica órfão no banco (rollback completo).
- Eventos `TenantCreated`, `UserRegistered`, `TrialStarted` são disparados.
- Suite Pest passando com cobertura ≥ 70% nos paths críticos (validação CNPJ, criação atômica, validação de token de 1º acesso, rate limiting).
- Larastan nível 8 sem erros.
- Aceite de termos versionado registrado em `tenants.terms_version` e `tenants.terms_accepted_at` (LGPD).

Critérios funcionais (Acceptance Criteria) serão detalhados na Spec Funcional.

## Fora do Escopo

**Trial pós-criação (delegado a módulo "Trial e Planos" — ADR-012):**
- `EndExpiredTrialsJob` (downgrade automático no dia 14)
- Enforcement de limites de plano em criação de imóvel/corretor
- E-mails de comunicação durante o trial (dia 7, 13, 14)
- Eventos `TrialEnding`, `TrialEnded`, `PlanDowngraded`
- Página de gestão de assinatura

**Pagamento (delegado a ADR-013):**
- Integração com Pagar.me (PIX, cartão, boleto)
- Webhooks de ativação/suspensão por pagamento
- Cobrança real no fim do trial

**Checklist guiado — itens que dependem de outros módulos ainda não implementados:**
- "Adicionar logo" e "Definir cor primária" — itens visíveis no checklist mas levam para placeholder "em breve" (depende de Configuração do Tenant — ADR-007)
- "Cadastrar primeiro imóvel" — placeholder "em breve" (depende de CRUD de imóveis — ADR-006/007)
- "Convidar primeiro corretor" — **funcional** (módulo Auth já entrega o fluxo de convite — ADR-009)
- "Configurar horários padrão de visita" — placeholder "em breve" (depende de ADR-008)
- "Cadastrar bairros que atende" — placeholder "em breve" (depende de ADR-007)

**LGPD / segurança transversal (delegados a módulo dedicado ou já presumidos resolvidos):**
- Páginas públicas `/privacy` e `/terms` com conteúdo jurídico real e histórico público de versões (a infra de aceite versionado entra; o conteúdo legal das páginas fica fora — assume conteúdo placeholder por ora)
- Página `/dpo` com contato do encarregado
- Página `/subprocessors`
- Tabela `audit_logs` completa (ADR-022 — eventos disparados atendem para auditoria via Sentry no curto prazo)

**Validação cadastral profunda:**
- Consulta à API da Receita Federal para situação cadastral do CNPJ (Alternativa D da ADR-011 — adiada para v2)
- Verificação de e-mail por confirmação (double opt-in clássico) — link de 1º acesso já cumpre função similar
- reCAPTCHA invisível — adiado; rate limiting agressivo (3/hora/IP) cobre o MVP. Adicionar reativamente se abuso for observado.

**Domínio customizado real:**
- ADR-016 deixa coluna `custom_domain` preparada no schema (já existe). Ativação efetiva via DNS TXT e Cloudflare for SaaS fica para v2.

**Super Admin:**
- Painel separado em `admin.acho.test`, "Login as", métricas globais — ADR-010, próxima etapa após Onboarding.

**Auth (já entregue):**
- Login, refresh, logout, recuperação de senha, mudança de senha, convite de corretor, cadastro de cliente final na vitrine — tudo entregue na etapa 03 e reaproveitado por este módulo.

## Riscos e Dependências

### Dependências de etapas anteriores (já entregues)

- **Etapa 02 (Multi-tenancy):** `tenants` table, `BaseTenantModel`, `TenantScope`, RLS, `TenantResolver` + `SetTenantContext` middlewares, `TenantService::resolveBySlug` com cache Redis, `Rule\ReservedSlug`, `config/reserved_slugs.php`, eventos `TenantCreated`/`Updated`/`Suspended`/`Reactivated`.
- **Etapa 03 (Auth — ADR-009/014):** `TokenService` (gera/valida JWT com `purpose`), `LoginService`, política de senha forte via `Password::defaults()`, middleware `auth.jwt`, refresh token rotacionado, Argon2id + Pepper, axios interceptor com refresh queue, hook `useAuth`. **Vamos estender** o `TokenService` para suportar `purpose: 'first_access'`.

### Dependências externas

- **Resend** (e-mail transacional — ADR-005) — precisa estar configurado no `.env` para envio real; em dev usamos Mailpit.
- **Redis** — rate limiter `auth-register` usa Redis (já configurado na etapa 01).
- **dnsmasq** — wildcard `*.acho.test` → 127.0.0.1 (já configurado).

### Migrações de schema necessárias

- `alter_tenants_add_onboarding_fields`: adicionar `cnpj` (varchar 18, unique, NOT NULL), `phone` (varchar 20, nullable), `plan_id` (uuid, FK → plans), `trial_ends_at` (timestamp, nullable), `subscription_id` (string, nullable), `terms_version` + `terms_accepted_at`, `privacy_version` + `privacy_accepted_at`.
- `create_plans_table`: id (uuid), name, slug (unique), price_cents, max_imoveis, max_corretores, features (jsonb), is_public (bool), sort_order, timestamps.
- `PlansSeeder`: criar os 5 planos (Trial, Gratuito, Básico, Pro, Enterprise).
- **Atenção:** migration de `tenants` é cuidadosa porque já existe o tenant `teste-interno` em produção local — precisa de default ou backfill para os NOT NULL novos.

### Restrições técnicas / decisões

- **CNPJ alfanumérico:** entra em vigor 6/jul/2026 (faltam ~6 semanas). Implementar suporte aos dois formatos desde já. Algoritmo módulo 11 padrão para numérico; conversão ASCII (caractere → valor numérico via posição no alfabeto) para alfanumérico.
- **Rate limiter:** definir em `bootstrap/app.php` ou `AppServiceProvider::boot()` via `RateLimiter::for('auth-register', ...)`.
- **Transação atômica:** `CreateTenantService::execute()` precisa rodar dentro de `DB::transaction()` — falha em qualquer passo (criar tenant, criar user admin, gerar token, enviar e-mail) faz rollback. Atenção: envio de e-mail via Resend é I/O externo — deve ser disparado por evento `TenantCreated` em **Job assíncrono** (queue), não dentro da transação. Falha de envio não pode bloquear o cadastro.
- **Token de 1º acesso:** JWT HS256 com `APP_KEY`, claims `{user_id, tenant_id, purpose: 'first_access', exp}`. Reutiliza `TokenService::generateAccessToken` com novo purpose. Validação no `FirstAccessController` checa `purpose` antes de criar sessão.
- **Subdomínio em uso vs reservado vs livre:** endpoint `/api/subdomain/check` precisa diferenciar para UX (mensagens diferentes). Lookup direto no banco (não usa cache — feedback em tempo real).

### Riscos identificados

- **Atacante automatiza criação de tenants:** mitigado por rate limiting (3 cadastros/IP/hora). Sem reCAPTCHA no MVP — monitorar logs para abuso real.
- **Cliente perde e-mail de 1º acesso:** botão "Reenviar link" na tela "Verifique seu e-mail" (gera novo JWT, invalida o anterior por timestamp).
- **Race condition em slug:** dois cadastros simultâneos com mesmo slug — `unique` constraint do banco resolve; tratar exceção 23505 com mensagem amigável.
- **Validação CNPJ com falsos positivos:** CNPJ pode ser válido matematicamente mas empresa fictícia. ADR-011 aceita esse risco no MVP (Alternativa D adiada).
- **`first_access` token usado mais de uma vez:** TTL curto (15min) reduz superfície. Não há revogação por reuso explícita no MVP — o link de qualquer forma só faz sentido na 1ª vez (depois o user já tem sessão). Adicionar `used_at` se observarmos abuso.
- **Tenants órfãos por falha pós-criação:** envio de e-mail falha → tenant existe mas user nunca recebe link. Mitigação: botão "Reenviar link" exige que user lembre do e-mail/subdomínio; ou job de cleanup para tenants sem login após X dias.

### Restrição de UX

- **Landing** ainda é placeholder (`HomeController::show` retorna view simples). Esta etapa não tem objetivo de entregar a landing comercial completa — entrega **apenas a página de cadastro** funcional (`acho.test/cadastro` ou rota equivalente). Landing marketing fica para módulo separado de marketing.
