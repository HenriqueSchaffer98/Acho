# Convenção: Estrutura de Pastas

## Backend (Laravel)

```
app/
├── Console/
│   ├── Commands/                    # Comandos artisan customizados
│   └── Kernel.php
│
├── Data/                            # DTOs (Spatie Laravel Data)
│   ├── Tenant/
│   │   ├── CreateTenantData.php
│   │   └── UpdateTenantData.php
│   ├── Imovel/
│   │   ├── CreateImovelData.php
│   │   └── UpdateImovelData.php
│   └── Agendamento/
│       └── ...
│
├── Events/                          # Eventos do domínio
│   ├── Tenant/
│   │   ├── TenantCreated.php
│   │   ├── TenantSuspended.php
│   │   └── TenantReactivated.php
│   ├── User/
│   │   └── ...
│   ├── Imovel/
│   │   └── ...
│   └── Agendamento/
│       └── ...
│
├── Exceptions/                      # Exceptions customizadas
│   ├── Handler.php
│   ├── TenantNotFoundException.php
│   ├── PlanLimitExceededException.php
│   └── PaymentFailedException.php
│
├── Filament/                        # Painéis Filament
│   ├── Tenant/                      # Painel admin de cada tenant
│   │   ├── Resources/
│   │   │   ├── ImovelResource.php
│   │   │   ├── CorretorResource.php
│   │   │   └── AgendamentoResource.php
│   │   ├── Pages/
│   │   └── Widgets/
│   └── SuperAdmin/                  # admin.seuapp.com.br
│       ├── Resources/
│       │   └── TenantResource.php
│       ├── Pages/
│       └── Widgets/
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/                     # Endpoints da API
│   │   │   ├── ImovelController.php
│   │   │   └── AgendamentoController.php
│   │   ├── Auth/                    # Login, register, password reset
│   │   ├── Public/                  # Vitrine pública (Inertia)
│   │   │   ├── HomeController.php
│   │   │   └── ImovelPublicoController.php
│   │   └── Webhook/                 # Webhooks externos
│   │       └── PagarmeWebhookController.php
│   │
│   ├── Middleware/
│   │   ├── TenantResolver.php       # Identifica tenant pelo subdomínio
│   │   ├── SetTenantContext.php     # Aplica RLS
│   │   ├── AuthenticateJWT.php
│   │   ├── RestrictToSuperAdmin.php
│   │   └── SecurityHeaders.php
│   │
│   ├── Requests/                    # Form Requests
│   │   ├── Auth/
│   │   │   ├── LoginRequest.php
│   │   │   └── RegisterRequest.php
│   │   ├── Tenant/
│   │   │   └── CreateTenantRequest.php
│   │   └── Imovel/
│   │       ├── StoreImovelRequest.php
│   │       └── UpdateImovelRequest.php
│   │
│   └── Resources/                   # API Resources (transformers)
│       ├── ImovelResource.php
│       └── AgendamentoResource.php
│
├── Jobs/                            # Queue jobs
│   ├── EndExpiredTrials.php
│   ├── ProcessImovelImage.php
│   └── ReconcileSubscriptions.php
│
├── Listeners/                       # Event listeners
│   ├── Auth/
│   │   ├── DetectAuthAnomaly.php
│   │   └── RehashPasswordOnLogin.php
│   ├── Tenant/
│   │   └── ProvisionTenantOnCreated.php
│   └── Imovel/
│       └── NotificarCorretorImovelCriado.php
│
├── Models/                          # Eloquent models
│   ├── BaseTenantModel.php          # Classe base com scope automático
│   ├── Tenant.php
│   ├── User.php
│   ├── Plan.php
│   ├── Subscription.php
│   ├── Imovel.php
│   ├── ImovelFoto.php
│   ├── Agendamento.php
│   ├── Bairro.php
│   └── AuditLog.php
│
├── Notifications/                   # Laravel Notifications
│   ├── BoasVindasNotification.php
│   ├── ConvitesCorretorNotification.php
│   └── VisitaConfirmadaNotification.php
│
├── Policies/                        # Authorization policies
│   ├── ImovelPolicy.php
│   ├── AgendamentoPolicy.php
│   └── UserPolicy.php
│
├── Providers/
│   ├── AppServiceProvider.php
│   ├── EventServiceProvider.php
│   ├── AuthServiceProvider.php
│   └── TenantServiceProvider.php
│
├── Rules/                           # Validation rules customizadas
│   ├── ValidCnpj.php
│   ├── StrongPassword.php
│   └── ReservedSlug.php
│
├── Services/                        # Lógica de negócio
│   ├── Auth/
│   │   ├── TokenService.php
│   │   └── PasswordService.php
│   ├── Tenant/
│   │   ├── CreateTenantService.php
│   │   ├── SuspendTenantService.php
│   │   └── ReactivateTenantService.php
│   ├── Imovel/
│   │   ├── CreateImovelService.php
│   │   ├── PublicarImovelService.php
│   │   └── PausarImovelService.php
│   ├── Scheduling/
│   │   ├── AvailabilityService.php
│   │   └── ScheduleVisitService.php
│   ├── Payment/
│   │   ├── PaymentService.php
│   │   └── WebhookProcessor.php
│   └── Storage/
│       ├── StorageProvider.php      # Interface
│       ├── R2Storage.php
│       ├── LocalStorage.php
│       └── ImageProcessingService.php
│
└── Support/                         # Helpers, traits
    ├── FileTypeDetector.php
    └── SubdomainHelper.php

config/
├── database.php
├── filesystems.php
├── reserved_slugs.php
├── pagarme.php
└── ...

database/
├── migrations/
├── factories/
└── seeders/

resources/
├── css/
├── js/
│   ├── Pages/                       # Páginas Inertia
│   │   ├── Public/
│   │   │   ├── Home.tsx
│   │   │   ├── ImovelDetail.tsx
│   │   │   └── Listing.tsx
│   │   ├── Auth/
│   │   │   ├── Login.tsx
│   │   │   └── Register.tsx
│   │   └── Cliente/
│   │       └── Profile.tsx
│   ├── Components/                  # Componentes React
│   │   ├── ui/                      # Base (button, input, modal)
│   │   ├── forms/                   # Formulários
│   │   ├── imoveis/                 # Específicos
│   │   └── shared/                  # Compartilhados
│   ├── Hooks/                       # Custom hooks
│   ├── Lib/                         # Utilities
│   ├── Types/                       # TypeScript types globais
│   └── app.tsx
└── views/
    └── emails/                      # Templates de e-mail

routes/
├── web.php                          # Rotas web (landing, auth)
├── tenant.php                       # Rotas dentro de subdomínio de tenant
├── api.php                          # API endpoints
├── admin.php                        # Super admin (admin.seuapp.com.br)
└── webhooks.php                     # Webhooks externos

tests/
├── Feature/                         # Testes de fluxo completo
│   ├── Auth/
│   ├── Tenant/
│   └── Imovel/
├── Unit/                            # Testes unitários
│   ├── Services/
│   ├── Data/
│   └── Rules/
└── TestCase.php

docs/                                # Documentação SDD
├── adr/
├── conventions/
├── specs/
├── vision/
└── runbooks/

storage/
public/
.github/
    └── workflows/
        ├── ci.yml
        └── deploy.yml
```

---

## Frontend (resources/js/)

### Pages

Páginas Inertia. Cada arquivo `.tsx` corresponde a uma rota.

```
Pages/
├── Public/                  # Acessível sem login na vitrine
│   ├── Home.tsx
│   ├── Listing.tsx
│   └── ImovelDetail.tsx
├── Auth/                    # Cadastro, login, recuperação
├── Cliente/                 # Área do cliente final logado
│   ├── Profile.tsx
│   └── MeusAgendamentos.tsx
└── Errors/
    ├── 404.tsx
    └── 500.tsx
```

### Components

```
Components/
├── ui/                      # Base, sem regra de negócio
│   ├── Button.tsx
│   ├── Input.tsx
│   ├── Modal.tsx
│   └── Card.tsx
├── forms/                   # Wrapper de formulários
│   ├── FormField.tsx
│   └── FormError.tsx
├── imoveis/                 # Específicos do domínio
│   ├── ImovelCard.tsx
│   ├── ImovelGallery.tsx
│   ├── ImovelMap.tsx
│   └── FiltroImoveis.tsx
└── shared/                  # Compartilhados entre páginas
    ├── Header.tsx
    ├── Footer.tsx
    └── ScheduleVisitModal.tsx
```

### Hooks

Custom React hooks reutilizáveis.

```
Hooks/
├── useAuth.ts
├── useDebounce.ts
└── useImovelFilters.ts
```

### Lib

Utilitários puros (sem JSX).

```
Lib/
├── format.ts                # formatadores (preço, data, etc.)
├── validation.ts            # esquemas Zod compartilhados
└── api.ts                   # cliente HTTP (Tanstack Query setup)
```

### Types

TypeScript types compartilhados.

```
Types/
├── index.ts                 # exports principais
├── api.ts                   # responses da API
└── domain.ts                # entidades de domínio
```

---

## Documentação (docs/)

```
docs/
├── adr/                     # Architecture Decision Records
│   ├── _template.md
│   ├── README.md
│   ├── 001-database-strategy.md
│   └── ...
│
├── vision/                  # Visão do produto
│   ├── 01-product-vision.md
│   ├── 02-business-model.md
│   ├── 03-target-audience.md
│   └── 04-roadmap.md
│
├── conventions/             # Padrões de implementação
│   ├── 01-architecture.md
│   ├── 02-folder-structure.md
│   └── ...
│
├── specs/                   # Especificações de feature
│   ├── _template.md
│   ├── 001-cadastro-imobiliaria.md
│   └── ...
│
└── runbooks/                # Procedimentos operacionais
    ├── deploy.md
    ├── rollback.md
    ├── incident-response.md
    └── local-setup.md
```

---

## Princípios

### Onde colocar arquivo novo

**Pergunte:** que tipo de coisa esse arquivo é?

- Lógica de negócio reutilizável → `Services/`
- Validação de input HTTP → `Http/Requests/`
- Resposta de API → `Http/Resources/`
- Tipo estruturado para passar entre camadas → `Data/`
- Reação a evento → `Listeners/`
- Tarefa assíncrona → `Jobs/`
- Regra de validação reutilizável → `Rules/`
- Helper sem dependências → `Support/`

### Subpastas por domínio

A partir de ~5 arquivos do mesmo tipo na mesma pasta, criar subpasta por domínio:

```
Services/
├── CreateImovelService.php          # ❌ logo vira bagunça
├── PublicarImovelService.php
├── CreateTenantService.php
├── ...
```

Vira:

```
Services/
├── Imovel/
│   ├── CreateImovelService.php
│   └── PublicarImovelService.php
└── Tenant/
    └── CreateTenantService.php
```

### Não criar pastas vazias

Crie pastas conforme há necessidade. Não estruture árvores hipotéticas.

---

## Referências

- Convention principal: este arquivo
- ADR relacionada: `ADR-025` (Project Patterns)
- Outras conventions: `01-architecture.md`, `03-naming.md`
