# Plano: Infraestrutura de Desenvolvimento Local
**Spec funcional:** specs/wip/01-infra-local/01-spec-functional.md
**Spec técnica:**   specs/wip/01-infra-local/02-spec-tech.md
**Status:** ready

## Tarefas

### Camada: Esqueleto Laravel

- [x] TASK-01 — Instalar Laravel 13 via Composer no diretório do projeto
  - Spec ref: base de tudo
  - Done quando: `php artisan --version` retorna Laravel 13.x dentro do container

- [x] TASK-02 — Instalar dependências PHP de dev (Sail, Pest, Pint, Larastan)
  - Spec ref: AC-02, AC-05, AC-06
  - Done quando: `composer.json` lista todos os pacotes require-dev; `composer install` passa

- [x] TASK-03 — Instalar dependências Node (React, Inertia, TS, ESLint, Prettier, Husky, lint-staged)
  - Spec ref: AC-05, AC-07, AC-08
  - Done quando: `package.json` lista todos os pacotes; `npm install` passa sem erros

### Camada: Docker / Ambiente

- [x] TASK-04 — Publicar e configurar docker-compose.yml via `sail:install`
  - Spec ref: AC-01
  - Done quando: `docker-compose.yml` tem os serviços laravel.test, pgsql:16, redis, mailpit com healthchecks

- [x] TASK-05 — Configurar .env.example com todas as variáveis de dev
  - Spec ref: AC-10
  - Done quando: .env.example tem APP_URL=http://acho.local, todas as vars de DB, Redis e Mailpit preenchidas

- [x] TASK-06 — Criar Makefile com os 6 targets (up, down, fresh, test, lint, analyze)
  - Spec ref: AC-01, AC-02, AC-05
  - Done quando: todos os targets executam sem erro com containers rodando; `make up` confirma <30s

### Camada: Tooling PHP

- [x] TASK-07 — Configurar Pint (`pint.json`)
  - Spec ref: AC-05
  - Done quando: `./vendor/bin/pint --test` passa no skeleton limpo do Laravel

- [x] TASK-08 — Configurar Larastan nível 8 (`phpstan.neon`)
  - Spec ref: AC-06
  - Done quando: `./vendor/bin/phpstan analyse` nível 8 passa no skeleton limpo; ignoreErrors cobre falsos positivos conhecidos

- [x] TASK-09 — Configurar Pest e criar testes dummy
  - Spec ref: AC-02
  - Done quando: `make test` executa e passa; `tests/Feature/ExampleTest.php` e `tests/Unit/ExampleTest.php` existem com 1 assertion cada

### Camada: Tooling Frontend

- [x] TASK-10 — Configurar TypeScript strict (`tsconfig.json`)
  - Spec ref: AC-07
  - Done quando: `tsconfig.json` tem `strict: true`, `noImplicitAny: true`; `npm run type-check` passa

- [x] TASK-11 — Configurar ESLint 9 com flat config (`eslint.config.js`)
  - Spec ref: AC-05
  - Done quando: `npx eslint .` passa no código gerado pelo Laravel/Vite sem erros

- [x] TASK-12 — Configurar Prettier (`.prettierrc.json`, `.prettierignore`)
  - Spec ref: AC-05
  - Done quando: `npx prettier --check .` passa em código recém-instalado

- [x] TASK-13 — Configurar `vite.config.ts` com plugin React e Inertia
  - Spec ref: base frontend
  - Done quando: `npm run build` compila sem erros

### Camada: Git Hooks

- [x] TASK-14 — Configurar Husky e lint-staged
  - Spec ref: AC-08
  - Done quando: `.husky/pre-commit` existe; commit com código mal-formatado é bloqueado; `package.json` tem `"prepare": "[ -z \"$CI\" ] && husky || true"`

- [x] TASK-15 — Criar `.lintstagedrc.json`
  - Spec ref: AC-08
  - Done quando: `*.php` roda Pint; `*.{ts,tsx}` roda ESLint + Prettier

### Camada: CI

- [x] TASK-16 — Criar workflow GitHub Actions (`.github/workflows/ci.yml`)
  - Spec ref: AC-04
  - Done quando: push para qualquer branch dispara CI; jobs de lint, analyze, type-check e test passam com PostgreSQL 16 e Redis como serviços

### Camada: Documentação e DNS

- [x] TASK-17 — Criar README.md com setup completo
  - Spec ref: AC-09
  - Done quando: README tem seções: pré-requisitos, clone + setup, configuração dnsmasq, `make up`, verificação de saúde dos containers

- [x] TASK-18 — Documentar configuração dnsmasq no README
  - Spec ref: AC-03
  - Done quando: README tem passo a passo para macOS (brew install dnsmasq, criar /etc/dnsmasq.d/acho.conf, reiniciar serviço, testar com `ping acho.local`)

## Ordem sugerida de implementação
1.  TASK-01 — Laravel 13 instalado (base de tudo)
2.  TASK-02 — Dependências PHP dev
3.  TASK-04 — docker-compose.yml via Sail
4.  TASK-05 — .env.example
5.  TASK-06 — Makefile
6.  TASK-07 — Pint
7.  TASK-08 — Larastan nível 8
8.  TASK-09 — Pest + testes dummy  ← primeiro critério de aceite verificável
9.  TASK-03 — Dependências Node
10. TASK-10 — TypeScript strict
11. TASK-13 — vite.config.ts
12. TASK-11 — ESLint 9
13. TASK-12 — Prettier
14. TASK-14 — Husky
15. TASK-15 — lint-staged
16. TASK-16 — GitHub Actions CI  ← valida tudo junto
17. TASK-17 — README.md
18. TASK-18 — dnsmasq no README

## Pontos de incerteza técnica
- TASK-08: Larastan nível 8 pode exigir ajustes finos no ignoreErrors dependendo
  da versão exata do skeleton Laravel 13 gerado — verificar após instalação real.
- TASK-16: A versão do PHP no runner do GitHub Actions deve bater com a do
  container local (8.3) — fixar `php-version: '8.3'` no workflow.
- TASK-14: Usar `"prepare": "[ -z \"$CI\" ] && husky || true"` no package.json
  para pular o install de hooks em CI.
