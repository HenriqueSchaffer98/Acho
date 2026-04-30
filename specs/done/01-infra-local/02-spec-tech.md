# Spec Técnica: Infraestrutura de Desenvolvimento Local
**Versão:** 1.0
**Status:** done
**Spec funcional:** specs/done/01-infra-local/01-spec-functional.md

## Decisões de Arquitetura

1. **Laravel Sail** como wrapper sobre Docker Compose — fornece o `docker-compose.yml`
   e o script `./vendor/bin/sail`. O `Makefile` wraps os comandos Sail para
   consistência (`make up` → `sail up -d`).

2. **CI sem Sail** — no GitHub Actions os serviços rodam como containers nativos do
   Actions (postgres:16, redis). Composer e PHP rodam direto no runner. Isso evita
   Docker-in-Docker e é mais rápido.

3. **Larastan nível 8 desde o início** — configurado com `ignoreErrors` para falsos
   positivos conhecidos do skeleton Laravel (facades, helpers).

4. **ESLint 9 com flat config** (`eslint.config.js`) — versão mais recente usa flat
   config por padrão; mais explícito e sem magia de extensões legacy.

5. **Husky + lint-staged** — pre-commit roda apenas nos arquivos staged: Pint para
   `.php`, ESLint + Prettier para `.ts/.tsx`.

6. **dnsmasq** — configuração macOS padrão: arquivo `/etc/dnsmasq.d/acho.conf` com
   `address=/.acho.local/127.0.0.1`. Não automatizado — documentado no README.

## Stack e Dependências

**PHP / Composer (require-dev):**
- `laravel/sail: ^1.x` — Docker Compose wrapper
- `pestphp/pest: ^3.x` — framework de testes
- `pestphp/pest-plugin-laravel: ^3.x` — integração Pest + Laravel
- `laravel/pint: ^1.x` — code style (PSR-12 + opinionated)
- `larastan/larastan: ^3.x` — PHPStan para Laravel (nível 8)
- `nunomaduro/collision: ^8.x` — error reporting legível no CLI

**Node / npm:**
- `vite: ^6.x` — build tool
- `typescript: ^5.x` — tipagem estática
- `@vitejs/plugin-react: ^4.x` — React fast refresh
- `react: ^18.x` + `react-dom: ^18.x`
- `@inertiajs/react: ^2.x` — adapter Inertia
- `tailwindcss: ^3.x` + `autoprefixer`
- `eslint: ^9.x` — lint JS/TS (flat config)
- `@typescript-eslint/parser` + `@typescript-eslint/eslint-plugin`
- `prettier: ^3.x` — formatação
- `husky: ^9.x` — git hooks
- `lint-staged: ^15.x` — lint apenas arquivos staged

## Estrutura de Arquivos

Arquivos criados nesta etapa (nenhum de domínio):

```
├── docker-compose.yml          ← serviços: php, pgsql, redis, mailpit
├── .env.example                ← variáveis de dev pré-preenchidas
├── .env                        ← cópia do .env.example (gitignored)
├── Makefile                    ← up, down, fresh, test, lint, analyze
├── README.md                   ← setup completo passo a passo
├── phpstan.neon                ← Larastan nível 8 + ignoreErrors
├── pint.json                   ← config Pint
├── eslint.config.js            ← ESLint 9 flat config
├── .prettierrc.json            ← Prettier config
├── .prettierignore
├── tsconfig.json               ← strict: true, noImplicitAny: true
├── .husky/
│   └── pre-commit              ← npx lint-staged
├── .lintstagedrc.json          ← mapeamento extensão → comando
├── .github/
│   └── workflows/
│       └── ci.yml              ← lint + analyze + test
└── tests/
    ├── Feature/ExampleTest.php ← teste dummy (assert true)
    └── Unit/ExampleTest.php    ← teste dummy (assert true)
```

Arquivos gerados pelo Laravel que serão configurados:
- `composer.json` — versões pinadas
- `package.json` — scripts: dev, build, type-check
- `vite.config.ts` — react plugin + inertia
- `tsconfig.json` — strict mode

## Modelos e Schemas
N/A — nenhum model ou migration de domínio nesta etapa.
O banco sobe vazio; `make fresh` roda apenas `migrate:fresh --seed`
com o DatabaseSeeder vazio padrão do Laravel.

## Contrato de API
N/A — nenhum endpoint nesta etapa.

## Detalhamento dos Arquivos Chave

**docker-compose.yml (serviços Sail):**
```yaml
services:
  laravel.test:   # PHP 8.3-FPM + extensões
    image: sail-8.3/app
    ports: ["${APP_PORT:-80}:80"]
    depends_on: [pgsql, redis, mailpit]
  pgsql:
    image: postgres:16
    environment: { POSTGRES_DB, POSTGRES_USER, POSTGRES_PASSWORD }
    volumes: [sailpgsql:/var/lib/postgresql/data]
    healthcheck: pg_isready
  redis:
    image: redis:alpine
    volumes: [sailredis:/data]
  mailpit:
    image: axllent/mailpit
    ports: ["${FORWARD_MAILPIT_PORT:-1025}:1025",
            "${FORWARD_MAILPIT_DASHBOARD_PORT:-8025}:8025"]
```

**phpstan.neon:**
```neon
includes:
  - vendor/larastan/larastan/extension.neon
parameters:
  level: 8
  paths: [app]
  ignoreErrors:
    - '#Call to an undefined method Illuminate\\.*#'
```

**Makefile:**
```makefile
SAIL = ./vendor/bin/sail
up:      $(SAIL) up -d
down:    $(SAIL) down
fresh:   $(SAIL) artisan migrate:fresh --seed
test:    $(SAIL) artisan test
lint:    $(SAIL) exec laravel.test ./vendor/bin/pint \
         && $(SAIL) exec laravel.test ./vendor/bin/phpstan analyse \
         && npx eslint . \
         && npx prettier --check .
analyze: $(SAIL) exec laravel.test ./vendor/bin/phpstan analyse
```

**GitHub Actions CI (ci.yml) — estrutura:**
```yaml
on: [push, pull_request]
services:
  postgres:
    image: postgres:16
    env: { POSTGRES_DB: testing, POSTGRES_USER: sail, ... }
    ports: ["5432:5432"]
  redis:
    image: redis
    ports: ["6379:6379"]
jobs:
  ci:
    steps:
      - composer install
      - cp .env.example .env && php artisan key:generate
      - php artisan migrate
      - ./vendor/bin/pint --test
      - ./vendor/bin/phpstan analyse
      - npm ci
      - npm run type-check
      - npx eslint .
      - npx prettier --check .
      - php artisan test
```

**.lintstagedrc.json:**
```json
{
  "*.php": ["./vendor/bin/pint"],
  "*.{ts,tsx}": ["eslint --fix", "prettier --write"]
}
```

## Riscos Técnicos
- **Larastan nível 8 no skeleton Laravel** pode ter falsos positivos em facades e
  helpers gerados — mitigação: bloco `ignoreErrors` no `phpstan.neon` para patterns
  conhecidos do framework.
- **Husky `prepare` script em CI** — roda em `npm install`, inclusive em CI;
  mitigação: `"prepare": "is-ci || husky"` no package.json (`CI=true` no Actions
  pula o hook install).
- **arm64 (Apple Silicon)** — imagens `postgres:16` e `redis:alpine` têm suporte
  multi-arch nativo; imagem Sail customizada (sail-8.3) é buildada localmente no
  primeiro `sail up`.

## Dúvidas Técnicas em Aberto
Nenhuma — todas resolvidas:
- CI com PostgreSQL 16 real (Actions service container)
- Domínio local `*.acho.local` via dnsmasq
- Docker Compose via Laravel Sail
