# ADR-021: Infraestrutura por Fase

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A infraestrutura de um SaaS evolui conforme o produto cresce. Decidir todas as escolhas de uma vez para "estado final" pode levar a:

- Custos desproporcionais ao estágio
- Complexidade desnecessária
- Tempo perdido em otimizações prematuras

Por outro lado, decidir nada e improvisar leva a:

- Lock-in com soluções inadequadas
- Refatorações dolorosas em escala
- Surpresas operacionais

A solução é planejar infraestrutura **por fase**, com gatilhos claros para evolução. Isso permite:

- Custo proporcional ao estágio atual
- Visibilidade do que vem pela frente
- Decisões já mapeadas (não improvisadas no momento)
- Validação de escala antes de escalar

Esta ADR define a infraestrutura por fase e os gatilhos para mudança.

---

## Decisão

Adotar evolução de infraestrutura em 3 fases, com gatilhos definidos para passagem entre elas. MVP opera em fase 1 (bootstrap minimal).

### Detalhamento

```
FASE 1 — BOOTSTRAP (MVP, 0–5 clientes pagantes)
─────────────────────────────────────────

Objetivo:
  ├── Custo mínimo absoluto
  ├── Velocidade de iteração
  └── Validar produto

Stack de Infraestrutura:

  Servidor Aplicação:
    ├── Hetzner CX21 (€7.55/mês)
    ├── 2 vCPU, 4GB RAM, 40GB SSD
    └── Datacenter EU (latência aceitável BR)

  Provisão e Deploy:
    ├── Laravel Forge ($12/mês)
    └── Deploy automático via webhook

  Banco de Dados:
    ├── Neon Pro ($19/mês)
    ├── PostgreSQL 16
    ├── 10 GB storage incluso
    ├── Backup automático
    └── Branching para preview deployments

  Storage:
    ├── Cloudflare R2 (free tier 10GB)
    └── CDN incluso

  E-mail:
    ├── Resend (free tier 3k/mês)
    └── Domain verification: SPF, DKIM, DMARC

  DNS / SSL / CDN / WAF:
    ├── Cloudflare Free
    ├── Wildcard SSL gratuito
    └── DDoS protection básico

  CI/CD:
    ├── GitHub Actions (free 2k min/mês)
    └── Workflows definidos em .github/workflows/

  Monitoramento (mínimo):
    ├── Sentry Free (5k events/mês)
    ├── UptimeRobot (free, 50 monitors)
    └── Logs do Forge (built-in)

  Pagamento:
    ├── Pagar.me (sem mensalidade)
    └── Taxa por transação

CUSTO TOTAL FASE 1: ~$39/mês fixo
```

```
FASE 2 — TRAÇÃO (5-20 clientes pagantes)
─────────────────────────────────────────

Objetivo:
  ├── Confiabilidade aumentada
  ├── Suporte a crescimento
  └── Visibilidade operacional

Mudanças vs Fase 1:

  Servidor Aplicação:
    └── Hetzner CX31 (€14.32/mês)
        ├── 4 vCPU, 8GB RAM, 80GB SSD
        └── Mais headroom para picos

  Banco de Dados:
    └── Neon Scale ($69/mês)
        ├── Mais compute, mais storage
        └── Auto-scaling habilitado

  Monitoramento:
    ├── Sentry Team ($26/mês — para mais events)
    ├── BetterStack ($25/mês — uptime + logs)
    └── New Relic Free (APM básico)

  Backup:
    └── Snapshot Neon → S3 (auditoria)

  Staging:
    └── Adicionar staging permanente (~$20/mês)

CUSTO TOTAL FASE 2: ~$200/mês fixo
```

```
FASE 3 — ESCALA (20+ clientes pagantes)
─────────────────────────────────────────

Objetivo:
  ├── Performance otimizada
  ├── Disponibilidade alta
  └── Escala horizontal

Mudanças vs Fase 2:

  Servidor Aplicação:
    ├── 2x VPS (load balanced)
    └── Hetzner Cloud Load Balancer

  Banco de Dados:
    ├── Neon Scale + Read Replica
    └── Connection pooling otimizado

  Cache:
    └── Redis dedicado (managed)

  CDN:
    └── Considerar BunnyCDN se escala R2 não for suficiente

  Worker Queues:
    └── Servidor dedicado para queue workers

  Monitoramento:
    ├── Datadog ou New Relic Pro
    ├── PagerDuty para incidentes críticos
    └── Logs centralizados (BetterStack ou Loki)

CUSTO TOTAL FASE 3: ~$600-1.000/mês fixo
```

```
Gatilhos de Upgrade (Fase 1 → 2)
─────────────────────────────────────────

Trigger A: 5+ clientes pagantes
  └── Receita justifica investimento em confiabilidade

Trigger B: CPU médio > 70% por 3 dias seguidos
  └── Servidor está saturando, upgrade preventivo

Trigger C: 80% do free tier do Sentry consumido
  └── Migrar para plano pago antes de perder events

Trigger D: 3+ incidentes em 30 dias
  └── Investir em observabilidade

Ação ao detectar gatilho:
  ├── Reavaliar fase atual
  ├── Documentar decisão (mini-ADR)
  └── Executar upgrade gradual
```

```
Gatilhos de Upgrade (Fase 2 → 3)
─────────────────────────────────────────

Trigger A: 20+ clientes pagantes
  └── Receita suporta arquitetura distribuída

Trigger B: Latência p99 > 500ms consistente
  └── Performance precisa otimização estrutural

Trigger C: Picos de 1000+ req/min
  └── Single server vira gargalo

Trigger D: Cliente enterprise com requisito de SLA
  └── Disponibilidade alta necessária

Ação ao detectar gatilho:
  ├── Plano de migração documentado
  ├── Migração com janela de manutenção
  └── Validação de melhoria após migração
```

```
Backup e Disaster Recovery
─────────────────────────────────────────

Fase 1 (MVP):
  ├── Neon backup automático (point-in-time recovery 7 dias)
  ├── Snapshot manual antes de migrations grandes
  └── Storage R2 com versioning ativo

Fase 2:
  ├── Backup diário do banco para S3 externo
  ├── Retenção: 30 dias
  └── Teste de restore mensal

Fase 3:
  ├── Backup contínuo via streaming replication
  ├── Multi-região de backup
  └── DR runbook testado trimestralmente

RTO (Recovery Time Objective):
  ├── Fase 1: 4 horas
  ├── Fase 2: 1 hora
  └── Fase 3: 15 minutos

RPO (Recovery Point Objective):
  ├── Fase 1: 1 hora (backup PITR)
  ├── Fase 2: 15 minutos
  └── Fase 3: < 1 minuto
```

```
Observabilidade por Fase
─────────────────────────────────────────

Fase 1 (Mínimo Viável):
  ├── Sentry para erros
  ├── UptimeRobot para uptime
  ├── Logs do Forge (Nginx + PHP)
  └── Manual: tail -f em produção quando precisar

Fase 2 (Visibilidade Estruturada):
  ├── Logs centralizados (BetterStack)
  ├── APM básico (New Relic free)
  ├── Métricas custom (DB queries, API latency)
  └── Alertas configurados

Fase 3 (Operações Maduras):
  ├── Datadog ou similar
  ├── Tracing distribuído
  ├── Custom dashboards por tenant
  └── On-call rotation
```

---

## Justificativa

A abordagem por fase se justifica por:

1. **Custo proporcional ao estágio** — Não paga $600/mês com 0 clientes
2. **Decisões antecipadas** — Sabe o que vem pela frente
3. **Gatilhos claros** — Sem dúvida sobre quando upgradar
4. **Migração gradual** — Cada fase é evolução, não revolução
5. **Aprendizado incremental** — Operação se sofistica conforme necessidade real

A escolha de Hetzner se justifica:
- Custo significativamente menor que AWS/GCP
- Performance excelente (servidores dedicados de qualidade)
- Latência aceitável para BR (~150-200ms)
- Sem armadilhas de billing (preço fixo)

A escolha de Neon se justifica:
- Branching de banco (preview deployments)
- Auto-scaling (não desperdiça recursos)
- Backup automático
- Compatível com Postgres standard (sem lock-in)

---

## Alternativas Consideradas

### Alternativa A — AWS desde o Dia 1

- **Descrição:** Stack completa AWS (EC2, RDS, S3, CloudFront).
- **Pontos fortes:** Padrão da indústria, ecossistema robusto.
- **Pontos fracos:** Custo 5-10x maior, complexidade IAM/VPC.
- **Por que não foi escolhida:** Inviável para bootstrap. Migração futura é viável.

### Alternativa B — Vercel + Supabase

- **Descrição:** Deploy serverless + Postgres managed.
- **Pontos fortes:** Setup ultra rápido, escala automática.
- **Pontos fracos:** Costos podem disparar. Vercel não é ideal para Laravel.
- **Por que não foi escolhida:** Stack Laravel encaixa melhor com VPS.

### Alternativa C — Render.com

- **Descrição:** Plataforma PaaS unificada.
- **Pontos fortes:** Setup simples, deploy automático.
- **Pontos fracos:** Mais caro que Hetzner. Postgres limitado.
- **Por que não foi escolhida:** Hetzner + Forge dá controle a custo menor.

### Alternativa D — DigitalOcean Droplet

- **Descrição:** VPS na DO em vez de Hetzner.
- **Pontos fortes:** Marca conhecida, datacenter Brasil.
- **Pontos fracos:** Mais caro que Hetzner (~$20/mês para mesmo tier).
- **Por que não foi escolhida:** Hetzner é mais barato. Migração futura é viável.

---

## Consequências

### Positivas

- Custo bootstrap mínimo (~$39/mês)
- Escala mapeada com gatilhos claros
- Sem otimização prematura
- Lock-in mínimo (Postgres standard, S3-compatible storage)
- Visibilidade do que vem pela frente

### Negativas

- Hetzner Europa adiciona ~150ms latência para BR
- Cada upgrade entre fases exige migração
- Single server na fase 1 é ponto único de falha
- Sem failover automático no MVP

### Riscos

- **Risco:** Single server cair e derrubar tudo
  - **Mitigação:** Backup automático + Forge facilita reprovisão. Aceitar como trade-off no MVP.

- **Risco:** Hetzner ter outage prolongada
  - **Mitigação:** Plano de migração documentado. Backups em provedor diferente.

- **Risco:** Crescimento mais rápido que esperado pegar de surpresa
  - **Mitigação:** Monitorar gatilhos ativamente. Estar pronto para upgrade em 24-48h.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Atingir gatilhos definidos (5 ou 20 clientes pagantes)
- Performance ou disponibilidade impactarem negócio
- Compliance exigir hospedagem específica (Brasil, EU)
- Concorrentes oferecerem features que exijam infra diferente

---

## Referências

- ADRs relacionadas: `ADR-017` (Environments), `ADR-019` (Tech Stack), `ADR-022` (Security)
- Hetzner: https://www.hetzner.com/cloud
- Neon: https://neon.tech
- Forge: https://forge.laravel.com

---

## Notas de Implementação

- Documentar credenciais em vault (1Password ou similar)
- Forge configurado com:
  - PHP 8.3
  - PostgreSQL client
  - Redis
  - Node.js 20
  - Daemon para queue worker
  - Cron para scheduler
- Cloudflare configurado:
  - Plano Free
  - SSL: Full (strict)
  - Always HTTPS
  - Auto Minify
  - Caching rules para assets
- Sentry configurado:
  - DSN no .env
  - Filtros para não-erros (validation, 404)
  - Alertas para erros 500+
- UptimeRobot:
  - Monitor de homepage
  - Monitor de health endpoint
  - Notificação por e-mail
- Runbook de incidente em `docs/runbooks/incident-response.md`
- Runbook de deploy em `docs/runbooks/deploy.md`
- Runbook de rollback em `docs/runbooks/rollback.md`
- Health check endpoint: `GET /health`
  - Retorna status do banco, redis, queue
  - Usado pelo UptimeRobot
- Considerar status page público (futuro)
