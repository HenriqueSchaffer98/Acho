# ADR-025: Padrões de Projeto e Arquitetura

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Projetos Laravel solo founder frequentemente caem em duas armadilhas:

1. **Over-engineering** — Aplicar Hexagonal/DDD/CQRS desde o dia 1, criar 5 camadas para CRUD simples
2. **Under-engineering** — Tudo no Controller, lógica espalhada, sem testes, código indebugável

Ambos os extremos são patológicos. O ideal é um **conjunto de padrões pragmáticos** que:

- Reflete o porte do projeto (não Netflix-scale)
- Facilita manutenção solo
- Permite onboarding futuro
- Aproveita o que Laravel oferece (sem reinventar)
- Evita decisões arbitrárias toda vez

A escolha precisa cobrir:

- **Camadas da aplicação** — Onde cada lógica vive
- **Estrutura de pastas** — Convenção clara
- **Padrões obrigatórios** — Quais sempre usar
- **Padrões NÃO usados** — Quais evitar
- **Tooling** — Qualidade automatizada
- **Banco** — Convenções específicas
- **Frontend** — Estrutura React

Esta ADR consolida os padrões adotados, complementando convenções detalhadas em `docs/conventions/`.

---

## Decisão

Adotar arquitetura em camadas pragmática (Controller → FormRequest → Service → Model → DB), sem Repository Pattern, com DTOs onde tipagem importa, eventos para desacoplamento, e ferramental rigoroso de qualidade.

### Detalhamento

```
Camadas da Aplicação
─────────────────────────────────────────

Request HTTP
    │
    ▼
Controller (thin)
    │ Recebe Request
    │ Delega para Service
    │ Retorna Response
    ▼
Form Request (validação + autorização)
    │ Validação de input
    │ Authorization
    │ Transforma em DTO
    ▼
Service (lógica de negócio)
    │ Operação principal do caso de uso
    │ Pode chamar outros Services
    │ Retorna DTO ou Model
    ▼
Model (Eloquent)
    │ Representa entidade
    │ Scopes, casts, relationships
    │ Sem lógica de negócio complexa
    ▼
Database (PostgreSQL)
    │ RLS aplicado
    │ Migrations imutáveis após deploy
    └── Source of truth
```

```
Por Que NÃO Usamos Repository Pattern
─────────────────────────────────────────

Em outros frameworks, Repository abstrai data access.
Em Laravel, Eloquent JÁ É o repository pattern.

Problema do Repository sobre Eloquent:
  ├── Duplica abstração existente
  ├── Adiciona complexidade sem ganho
  ├── Vai contra grain do framework
  └── Burocratiza CRUDs simples

Anti-pattern típico:

class UserRepository {
  public function find($id) {
    return User::find($id);  // ← redundante
  }
}

Quando Repository faria sentido (não nosso caso):
  └── Migrar de Eloquent para outro ORM
  └── Combinar múltiplas fontes (DB + API + cache)
  └── Lógica de query super complexa repetida

Conclusão:
  └── Use Eloquent Models diretamente
  └── Use Query Scopes para queries reutilizáveis
  └── Use Services para lógica que envolve múltiplos models
```

```
Service vs Action
─────────────────────────────────────────

Quando usar Service:
  ├── Lógica que envolve múltiplos models
  ├── Operação que pode ser reutilizada
  ├── Wraps de operações complexas
  └── Stateless: não guarda estado

Exemplo: app/Services/CreateTenantService.php
  ├── Cria Tenant
  ├── Cria User admin
  ├── Define plano trial
  ├── Gera token de primeiro acesso
  ├── Dispara eventos
  └── Retorna DTO com resultado

Quando usar Action (alternativa):
  └── Padrão de "Single Action Class"
  └── Uma classe = uma responsabilidade
  └── Útil para operações simples e únicas

Decisão para o projeto:
  └── Usar Services (mais flexível)
  └── Action pode ser usado em casos específicos
  └── Evitar mistura caótica
```

```
DTOs com Spatie Laravel Data
─────────────────────────────────────────

Por que DTOs?
  ├── Tipagem forte de inputs/outputs
  ├── Autocompletar em IDEs
  ├── Serialização/deserialização automática
  ├── Validação integrada
  └── Documentação implícita

Onde usar DTOs:
  ├── Inputs de Services
  ├── Returns de Services
  ├── Payloads de eventos
  ├── Responses de API (consistência)
  └── Forms (Form Request → DTO)

Onde NÃO usar:
  └── Models simples internos
  └── Query results (usar Models direto)

Exemplo:

class CreateTenantData extends Data {
  public function __construct(
    public string $razaoSocial,
    public string $cnpj,
    public string $email,
    public string $senha,
    public string $slug,
    public string $telefone,
  ) {}
}

class CreateTenantService {
  public function execute(CreateTenantData $data): TenantData {
    // ...
  }
}
```

```
Eventos e Listeners
─────────────────────────────────────────

Por que Eventos?
  ├── Desacoplamento entre features
  ├── Multiple side-effects de uma ação
  ├── Async via queues (não bloqueia request)
  └── Auditoria natural

Eventos Padrão:

User events:
  ├── UserRegistered
  ├── UserLoggedIn / UserLoginFailed
  ├── UserPasswordChanged
  └── UserDeactivated

Tenant events:
  ├── TenantCreated
  ├── TenantSuspended / TenantReactivated
  └── TenantPlanChanged

Imovel events:
  ├── ImovelCreated / ImovelUpdated / ImovelDeleted
  ├── ImovelPublicado / ImovelPausado
  └── ImovelStatusChanged

Agendamento events:
  ├── VisitScheduled
  ├── VisitConfirmed / VisitCancelled
  └── VisitCompleted

Listeners típicos:
  ├── SendNotification (e-mail, futuro WhatsApp)
  ├── CreateAuditLog
  ├── UpdateMetrics
  └── InvalidateCache

Configuração:
  └── EventServiceProvider mapeia eventos → listeners
  └── Listeners usam queue por padrão
  └── Síncronos apenas onde crítico (ex: validações)
```

```
Policies
─────────────────────────────────────────

Para autorização (vs Form Request validation):

App\Policies\ImovelPolicy:
  ├── viewAny(User $user) — pode listar?
  ├── view(User $user, Imovel $imovel) — pode ver este?
  ├── create(User $user) — pode criar?
  ├── update(User $user, Imovel $imovel) — pode editar?
  └── delete(User $user, Imovel $imovel) — pode deletar?

Lógica típica:
  ├── Admin → tudo do tenant
  ├── Corretor → apenas próprios imóveis (update/delete)
  ├── Cliente → não acessa Imovel admin
  └── Sempre verifica tenant_id

Uso:
  ├── Em Controllers: $this->authorize('update', $imovel)
  ├── Em Filament: integração nativa via `can()`
  └── Em Blade: @can('update', $imovel)
```

```
Estrutura de Pastas — Backend
─────────────────────────────────────────

app/
├── Console/Commands/         (artisan commands)
├── Events/                   (eventos do domínio)
├── Exceptions/               (exceptions customizadas)
├── Filament/
│   ├── Tenant/Resources/    (admin de cada tenant)
│   └── SuperAdmin/Resources/ (super admin)
├── Http/
│   ├── Controllers/
│   │   ├── Api/             (API endpoints)
│   │   ├── Auth/            (login, register)
│   │   ├── Public/          (vitrine pública)
│   │   └── Webhook/         (webhooks externos)
│   ├── Middleware/          (middlewares customizados)
│   └── Requests/            (Form Requests)
├── Jobs/                    (queue jobs)
├── Listeners/               (event listeners)
├── Models/                  (Eloquent models)
├── Notifications/           (Laravel Notifications)
├── Policies/                (authorization policies)
├── Providers/               (service providers)
├── Rules/                   (validation rules customizadas)
├── Services/                (business logic)
│   ├── Auth/
│   ├── Tenant/
│   ├── Imovel/
│   ├── Scheduling/
│   └── Payment/
├── Support/                 (helpers, traits)
└── Data/                    (DTOs com Spatie Data)
```

```
Estrutura de Pastas — Frontend
─────────────────────────────────────────

resources/js/
├── Pages/                   (páginas Inertia)
│   ├── Public/
│   │   ├── Home.tsx
│   │   ├── ImovelDetail.tsx
│   │   └── Listing.tsx
│   ├── Auth/
│   │   ├── Login.tsx
│   │   └── Register.tsx
│   └── Cliente/
│       └── Profile.tsx
├── Components/              (componentes React reutilizáveis)
│   ├── ui/                  (button, input, modal — base)
│   ├── forms/               (form components)
│   ├── imoveis/             (componentes específicos)
│   └── shared/              (compartilhados entre páginas)
├── Hooks/                   (custom React hooks)
├── Lib/                     (utilities)
├── Types/                   (TypeScript types compartilhados)
└── app.tsx                  (entry point)

resources/css/
├── app.css                  (Tailwind base)
└── ...
```

```
Convenções de Banco
─────────────────────────────────────────

Nomenclatura:
  ├── Tabelas: plural, snake_case (imoveis, agendamentos)
  ├── Colunas: snake_case (created_at, tenant_id)
  ├── Pivot tables: alfabético (corretor_imovel, role_user)
  ├── FKs: {table}_id (tenant_id, user_id)
  └── Booleans: is_*, has_*, can_* (is_active)

Estrutura padrão de toda tabela de negócio:
  ├── id (uuid PRIMARY KEY)
  ├── tenant_id (uuid, FK, INDEXED, NOT NULL)
  ├── created_at (timestamp NOT NULL)
  ├── updated_at (timestamp NOT NULL)
  └── deleted_at (timestamp NULL — soft delete)

UUIDs como PKs:
  ├── Não vazam quantidade (ex: /imoveis/1234)
  ├── Permitem geração no frontend
  ├── Evitam conflitos em multi-tenant
  └── Trade-off: mais espaço (16 vs 8 bytes)

Soft Delete:
  ├── Usar deleted_at em todas as tabelas
  ├── Restore possível em casos de erro
  └── Job periódico para limpeza definitiva

Indexes:
  ├── Sempre em FK (tenant_id, user_id, etc.)
  ├── Compostos em queries comuns
  ├── Documentar via comment se não óbvio
  └── Reviewer questiona se faz sentido

Migrations:
  ├── Imutáveis após deploy
  ├── Sempre reversíveis (down() funcional)
  ├── Não editar migrations após deploy
  └── Mudanças = nova migration
```

```
Validação
─────────────────────────────────────────

Camadas:

1. Frontend (UX, não segurança)
   ├── React Hook Form + Zod
   ├── Validação inline
   └── Feedback imediato

2. Form Request (Backend)
   ├── Validação real
   ├── Authorization
   └── Cleaning de inputs

3. Database
   ├── Constraints (NOT NULL, UNIQUE)
   ├── Foreign Keys
   └── Check constraints quando aplicável

Não confiar apenas em frontend:
  └── Frontend é UX
  └── Validação real é backend + DB
```

```
Testes (Pest)
─────────────────────────────────────────

Pirâmide:

Unit (50% dos testes)
  ├── Services puros
  ├── DTOs / Data classes
  ├── Helpers e utils
  └── Lógica de domínio

Feature (40% dos testes)
  ├── Endpoints HTTP
  ├── Fluxos completos
  ├── Authorization
  └── Eventos disparados

E2E (10% dos testes)
  ├── Fluxos críticos via Dusk (futuro)
  └── Não no MVP inicial

Convenções:
  ├── tests/Unit/Services/CreateTenantServiceTest.php
  ├── tests/Feature/Auth/LoginTest.php
  ├── Naming descritivo: it('creates tenant with valid data')
  ├── Arrange-Act-Assert claro
  └── Fixtures via factories

Cobertura mínima:
  ├── Critical paths: 90%+
  ├── Geral: 70%+
  └── Reportada via xdebug/pcov
```

```
Tooling de Qualidade
─────────────────────────────────────────

Backend (PHP):

Pint (Laravel CS Fixer)
  └── Formatação automática
  └── Pre-commit hook
  └── CI verifica

Larastan (PHPStan para Laravel)
  ├── Análise estática
  ├── Nível 8 (máximo)
  ├── Pega muitos bugs sem rodar
  └── CI verifica

Pest
  ├── Testing framework
  ├── Sintaxe limpa
  ├── Compatible com PHPUnit
  └── Plugin para coverage

Composer Audit
  └── Vulnerabilidades em deps
  └── CI verifica

Frontend (JS/TS):

ESLint
  ├── Padrões TypeScript estritos
  ├── React rules
  └── Custom rules do projeto

Prettier
  ├── Formatação consistente
  └── Pre-commit hook

TypeScript (strict mode)
  ├── strict: true
  ├── noImplicitAny: true
  └── strictNullChecks: true

Husky
  └── Git hooks (pre-commit, pre-push)

lint-staged
  └── Roda apenas em arquivos modificados
```

```
Padrões de Erro e Logging
─────────────────────────────────────────

Exceptions Customizadas:
  ├── App\Exceptions\TenantNotFoundException
  ├── App\Exceptions\PlanLimitExceededException
  ├── App\Exceptions\PaymentFailedException
  └── ...

Tratamento Centralizado:
  └── App\Exceptions\Handler.php
  └── Renderiza JSON para API
  └── Renderiza páginas para web
  └── Logs estruturados via Sentry

Logs Estruturados:
  ├── Sempre com contexto (tenant_id, user_id, action)
  ├── Levels: debug, info, warning, error, critical
  ├── Production: info+ apenas
  └── Sentry captura warnings+

Mensagens de Erro:
  ├── Usuário: claras, sem detalhes técnicos
  ├── Logs: detalhados para debug
  ├── API: códigos consistentes
  └── Não vazar info sensível
```

```
Padrões de API REST
─────────────────────────────────────────

Endpoints:
  ├── GET    /api/imoveis (lista)
  ├── GET    /api/imoveis/{id} (detalhe)
  ├── POST   /api/imoveis (cria)
  ├── PUT    /api/imoveis/{id} (atualiza completo)
  ├── PATCH  /api/imoveis/{id} (atualiza parcial)
  └── DELETE /api/imoveis/{id} (deleta)

Status Codes:
  ├── 200 OK
  ├── 201 Created
  ├── 204 No Content (delete)
  ├── 400 Bad Request (input inválido)
  ├── 401 Unauthorized (sem token)
  ├── 403 Forbidden (sem permissão)
  ├── 404 Not Found
  ├── 422 Unprocessable Entity (validação)
  ├── 429 Too Many Requests
  └── 500 Internal Server Error

Estrutura de Response:

Sucesso:
{
  "data": {...},
  "meta": {...optional}
}

Erro:
{
  "message": "Erro principal",
  "errors": {
    "campo": ["mensagem"]
  }
}

Paginação:
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 150,
    "per_page": 25
  }
}
```

---

## Justificativa

A escolha dos padrões se justifica por:

1. **Pragmatismo Laravel-centric** — Não fight the framework
2. **Sem over-engineering** — Repository Pattern em Eloquent é redundante
3. **Camadas claras mas finas** — Controller thin, lógica em Service
4. **DTOs onde tipagem importa** — Não burocratizar tudo
5. **Eventos para desacoplamento** — Crescimento sustentável
6. **Tooling rigoroso** — Qualidade automatizada (não manual)
7. **Convenções fortes** — Reduz decisões arbitrárias diárias

A escolha consciente:
- **Sem DDD/Hexagonal** — Overkill para o porte
- **Sem CQRS** — Não há justificativa de separação read/write
- **Sem microservices** — Monolito modular serve bem
- **Sem Repository** — Eloquent já é

---

## Alternativas Consideradas

### Alternativa A — DDD Completo (Domain-Driven Design)

- **Descrição:** Aggregates, Value Objects, Domain Events, Bounded Contexts.
- **Pontos fortes:** Estrutura forte para domínios complexos.
- **Pontos fracos:** Overkill para CRUD majoritário. Curva de aprendizado.
- **Por que não foi escolhida:** Complexity tax não compensa para o porte do projeto.

### Alternativa B — Hexagonal Architecture (Ports and Adapters)

- **Descrição:** Núcleo isolado, adapters para infrastructure.
- **Pontos fortes:** Testabilidade, facilita troca de tech.
- **Pontos fracos:** Burocracia alta para projeto solo founder.
- **Por que não foi escolhida:** Laravel já oferece testabilidade boa. Hexagonal vira teatro.

### Alternativa C — Tudo em Controllers (Laravel "Tradicional Pobre")

- **Descrição:** Lógica direto em Controllers, sem Services.
- **Pontos fortes:** Simplicidade extrema.
- **Pontos fracos:** Controllers ficam gigantes, código não reutilizável.
- **Por que não foi escolhida:** Mantém estrutura básica de qualidade.

### Alternativa D — Active Record Pesado (Lógica nos Models)

- **Descrição:** Toda lógica nos métodos do Model.
- **Pontos fortes:** Próximo ao dado.
- **Pontos fracos:** Models inchados, hard to test, hard to refactor.
- **Por que não foi escolhida:** Service Layer pequena resolve melhor.

---

## Consequências

### Positivas

- Estrutura clara e previsível
- Onboarding futuro facilitado
- Testes naturalmente bem estruturados
- Aproveita o melhor de Laravel
- Sem over-engineering desnecessário
- Tooling pega muitos bugs antes de chegar em prod

### Negativas

- Código tem mais classes que "Laravel raiz"
- DTOs adicionam um pouco de boilerplate
- Eventos podem dificultar tracing (use case complexos)
- TypeScript estrito no front é mais lento de escrever inicialmente

### Riscos

- **Risco:** Tentação de over-engineer ao crescer
  - **Mitigação:** Reavaliações conscientes. ADR para mudanças significativas.

- **Risco:** Tooling muito rigoroso atrasar entrega
  - **Mitigação:** Configurações pragmáticas. Larastan pode começar em nível menor e subir.

- **Risco:** Eventos criarem comportamento "mágico" hard to debug
  - **Mitigação:** Logs claros de cada evento. Sentry captura. Eventos documentados.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Time crescer para 5+ devs (talvez precise mais estrutura)
- Domínio se tornar significativamente complexo (DDD?)
- Performance exigir CQRS (read-heavy)
- Refatoração grande for necessária (avaliar arquitetura)

---

## Referências

- ADRs relacionadas: `ADR-019` (Tech Stack), `ADR-020` (SDD Strategy), `ADR-022` (Security)
- Convenções detalhadas: `docs/conventions/`
- Laravel Best Practices: https://github.com/alexeymezenin/laravel-best-practices
- Spatie Laravel Data: https://spatie.be/docs/laravel-data

---

## Notas de Implementação

- Configuração inicial:
  - `.editorconfig` na raiz
  - Pint config em `pint.json`
  - PHPStan config em `phpstan.neon`
  - ESLint config em `.eslintrc.json`
  - Prettier config em `.prettierrc.json`
  - TypeScript config em `tsconfig.json`
  - Husky setup em `.husky/`
- Convenções detalhadas em arquivos separados:
  - `docs/conventions/01-architecture.md` — esta ADR detalhada
  - `docs/conventions/02-folder-structure.md`
  - `docs/conventions/03-naming.md`
  - `docs/conventions/04-database.md`
  - `docs/conventions/05-api-design.md`
  - `docs/conventions/06-frontend-patterns.md`
  - `docs/conventions/07-error-handling.md`
  - `docs/conventions/08-testing.md`
- Templates iniciais para novos arquivos:
  - Service template
  - Form Request template
  - DTO template
  - Test template
- Code review checklist incluído no PR template
- IDE setup recomendado:
  - VS Code com extensões: PHP Intelephense, Laravel Pint, ESLint, Prettier
  - Settings compartilhados em `.vscode/settings.json`
- Comandos úteis:
  - `make lint` — roda Pint + Larastan + ESLint
  - `make test` — roda Pest
  - `make fresh` — reset do banco
  - `make analyze` — análise estática
