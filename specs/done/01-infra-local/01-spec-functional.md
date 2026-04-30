# Spec Funcional: Infraestrutura de Desenvolvimento Local
**Versão:** 1.0
**Status:** done
**Discovery:** specs/done/01-infra-local/discovery.md

## Contexto
O projeto Acho (SaaS multi-tenant para imobiliárias) está na fase zero de
implementação. Antes de qualquer código de domínio, é necessário um ambiente
local reproduzível que siga os padrões das ADRs e permita ao dev (e futuros
colaboradores) iniciar em minutos a partir de um clone limpo.

## Usuários e Benefício
Dev solo e futuros colaboradores. Benefício: ambiente idêntico ao que será
produzido em cada máquina — sem "funciona na minha máquina", sem configuração
manual a cada clone, com tooling de qualidade já integrado e CI validando desde
o primeiro commit.

## Comportamento Esperado

**Fluxo 1: Setup inicial a partir de clone limpo**
DADO que o dev clonou o repositório em um Mac sem setup prévio
QUANDO seguir as instruções do README.md
ENTÃO ao final terá o ambiente rodando com `make up` funcionando

**Fluxo 2: Subida do ambiente no dia a dia**
DADO que o ambiente foi configurado ao menos uma vez (imagens em cache)
QUANDO executar `make up`
ENTÃO todos os containers (PHP, PostgreSQL 16, Redis, Mailpit) sobem prontos em <30 segundos

**Fluxo 3: Rodar testes**
DADO que o ambiente está de pé
QUANDO executar `make test`
ENTÃO o Pest executa a suíte e passa (incluindo ao menos 1 teste dummy)

**Fluxo 4: Rodar lint e análise estática**
DADO que há código no projeto (mesmo que seja apenas o esqueleto inicial)
QUANDO executar `make lint`
ENTÃO Pint (PHP), Larastan nível 8 (PHP) e ESLint + Prettier (TS) rodam sem erros

**Fluxo 5: Resolução de subdomínios locais**
DADO que dnsmasq está configurado conforme o README
QUANDO o dev acessar qualquer subdomínio `*.acho.local` no browser
ENTÃO o domínio resolve para 127.0.0.1 sem editar /etc/hosts manualmente

**Fluxo 6: Pre-commit automático**
DADO que o dev tenta fazer commit de código com erros de lint ou formato
QUANDO o commit for executado
ENTÃO o Husky intercepta e roda lint-staged, bloqueando o commit se houver erro

**Fluxo 7: CI no GitHub Actions**
DADO um push ou pull request para qualquer branch
QUANDO o workflow de CI rodar
ENTÃO lint + análise estática + testes passam e o status fica verde

## Contrato de Interface

**Comandos Makefile disponíveis:**
- `make up`      — sobe containers via Docker Compose (detached)
- `make down`    — para containers
- `make fresh`   — reset completo de banco + executa seeders
- `make test`    — roda Pest completo dentro do container
- `make lint`    — Pint + Larastan + ESLint + Prettier check
- `make analyze` — apenas Larastan nível 8

**Variáveis de ambiente obrigatórias no .env (desenvolvimento):**
- `APP_URL=http://acho.local`
- `DB_HOST`, `DB_PORT=5432`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `REDIS_HOST`, `REDIS_PORT=6379`
- `MAIL_HOST` (Mailpit), `MAIL_PORT=1025`

**Erros tratados:**
- Porta em uso: Docker Compose exibe erro claro; README documenta como trocar porta
- Dnsmasq não configurado: acesso a `*.acho.local` falha; README instrui setup

## Casos de Borda
- [ ] Mac com Apple Silicon (arm64): imagens Docker devem funcionar nativamente
- [ ] Primeira execução (sem cache de imagem): `make up` pode demorar mais — não
      conta para o critério de <30s; README deve avisar
- [ ] Porta 80, 5432 ou 6379 já em uso: mapeamento de porta alternativa via
      variável de ambiente no `.env` ou override de compose
- [ ] Node/PHP não instalados globalmente: tudo roda dentro do container,
      exceto `npm install` para Husky (Node local necessário apenas para hooks)

## Critérios de Aceite
- [ ] AC-01: `make up` sobe todos os containers em <30s com imagens em cache
- [ ] AC-02: `make test` executa e passa com ao menos 1 teste Pest dummy
- [ ] AC-03: `*.acho.local` resolve para 127.0.0.1 via dnsmasq (após setup do README)
- [ ] AC-04: CI no GitHub Actions passa lint + analyze + test em push/PR
- [ ] AC-05: `make lint` não reporta erros em código limpo recém-instalado
- [ ] AC-06: Larastan está configurado em nível 8 (`phpstan.neon`)
- [ ] AC-07: `tsconfig.json` tem `strict: true` e `noImplicitAny: true`
- [ ] AC-08: Husky pre-commit roda lint-staged e bloqueia commit com erros de lint
- [ ] AC-09: README.md tem seção de setup completa (clone → `make up` rodando)
- [ ] AC-10: `.env.example` tem todas as variáveis necessárias com valores de dev

## Fora do Escopo
- Multi-tenancy e stancl/tenancy — Etapa 02
- Qualquer migration ou model de domínio
- Configuração de produção (Forge, Hetzner, Neon)
- SSL local (https)
- Setup automatizado de dnsmasq via script (documentado no README é suficiente)

## Dúvidas em Aberto
Nenhuma — resolvidas antes da aprovação:
- CI usa PostgreSQL real (container de serviço no Actions)
- Domínio local: `*.acho.local` via dnsmasq
- Ambiente via Docker Compose (Laravel Sail)

## Como foi implementado

Bootstrap via `composer:latest` Docker image (sem PHP local). Laravel 13.6.0 instalado com
`composer create-project`, dependências PHP e Node instaladas em sequência.

**Desvios e decisões durante o build:**

- `compose.yaml` em vez de `docker-compose.yml` — Sail 1.57 usa o nome moderno; Docker Compose detecta ambos automaticamente
- PHP 8.3 corrigido manualmente no `compose.yaml` — Sail detectou PHP 8.5 do `composer:latest` e gerou runtime errado
- PostgreSQL `postgres:16` substituiu `postgres:18-alpine` gerado — alinhamento com ADR-019
- `platform: {"php": "8.3"}` adicionado ao `composer.json` — PHPStan instalado com PHP 8.4 resolveu versão incompatível com o container PHP 8.3; a config força resolução sempre em 8.3
- `@vitejs/plugin-react@^6` em vez de `^4` — Vite 8 (gerado pelo skeleton Laravel 13) não era suportado pelo plugin-react v4; v6 é a versão compatível
- Tailwind v4 mantido — skeleton Laravel 13 usa `@tailwindcss/vite` (v4); spec dizia v3, mas "usar versões mais recentes" prevaleceu
- `reportUnmatchedIgnoredErrors: false` no `phpstan.neon` — ignoreErrors preventivos para falsos positivos futuros causavam erro no skeleton limpo
- `registry=https://registry.npmjs.org` adicionado ao `.npmrc` do projeto — registry global da máquina apontava para Nexus privado (BRY) inacessível
- CI configurado com `container: laravelsail/php83-composer` — decisão tomada durante o build para manter ambiente idêntico ao local; spec original usava runner direto
