# Discovery: Infraestrutura de Desenvolvimento Local
**Data:** 2026-04-26

## Problema
Criar a infraestrutura de desenvolvimento local antes de qualquer código de domínio. O projeto precisa de um ambiente reproduzível com Laravel 13 + Sail (PostgreSQL 16, Redis, Mailpit), dnsmasq para resolução de `*.local`, Makefile com comandos comuns, README de setup, e tooling completo (Pint, Larastan nível 8, Pest, Husky, TypeScript strict, ESLint, Prettier).

## Usuários e Benefício
Dev solo (e futuros colaboradores). O benefício é ter o projeto rodando e pronto para desenvolvimento seguindo os critérios das ADRs — ambiente consistente, reproduzível e sem fricção para começar a codar.

## Critério de Sucesso
- `make up` sobe o ambiente em menos de 30 segundos (após primeira build)
- `make test` roda e passa (com ao menos 1 teste dummy)
- `*.local` resolve corretamente para `127.0.0.1` via dnsmasq
- CI no GitHub Actions verde (lint + test passando)

## Fora do Escopo
- Multi-tenancy — Etapa 02
- Qualquer feature de domínio (models, migrations de negócio, etc.)
- Deploy ou configuração de produção

## Riscos e Dependências
Nenhuma restrição identificada. Usar as versões mais recentes disponíveis de todas as ferramentas.

**ADRs relevantes:** ADR-017 (Environments), ADR-019 (Tech Stack)
