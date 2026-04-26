# ADR-017: Estratégia Multi-Ambiente Bootstrap

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Projetos SaaS profissionais tipicamente operam com 3-4 ambientes (local, dev, staging, production), cada um com propósito específico. Essa estratégia tem custo proporcional ao número de ambientes.

Para um projeto solo founder em fase de bootstrap, manter 4 ambientes online significa custo desproporcional ao retorno. A análise inicial calculou:

```
Estratégia profissional completa:
  ├── Local:        $0
  ├── Dev:          ~$20/mês
  ├── Staging:      ~$40/mês
  └── Production:   ~$50–80/mês
  Total:            ~$110–140/mês
```

Para um produto com 0 clientes pagantes ainda, esse custo é difícil de justificar. A pergunta arquitetural é: **como manter qualidade e segurança sem todos os ambientes?**

A análise revelou que muitos riscos cobertos por staging podem ser mitigados com técnicas modernas:

- Preview Deployments por Pull Request
- Feature Flags para rollout gradual
- Tenant de teste em produção
- Suíte robusta de testes automatizados

---

## Decisão

Operar com apenas **2 ambientes ativos**: `local` (desenvolvimento) e `production` (operação). O ambiente de staging permanente fica postergado para v2 (5+ clientes pagantes).

### Detalhamento

```
Ambientes Ativos
─────────────────────────────────────────

LOCAL ($0)
  ├── Cada dev na sua máquina
  ├── Tudo via Docker Compose
  ├── Reset rápido (1 comando)
  ├── Funciona offline
  └── Custo: $0

PRODUCTION
  ├── Hetzner CX21 + Laravel Forge (~$16,50/mês)
  ├── Neon Pro (~$19/mês)
  ├── Cloudflare R2 (free tier)
  ├── Cloudflare Free (DNS/SSL/CDN)
  ├── Resend (free tier)
  ├── GitHub Actions (free tier)
  └── Custo total: ~$38/mês

Removidos do MVP:
  ❌ Ambiente DEV separado
  ❌ Ambiente STAGING permanente
```

```
Substitutos do Staging
─────────────────────────────────────────

1. Preview Deployments (por Pull Request)
   ├── Cada PR gera URL temporária
   ├── feature-X.preview.seuapp.com.br
   ├── Banco: cópia efêmera (Neon branching)
   ├── Vida: enquanto PR aberto
   └── Custo: $0 (Neon free tier branches)

2. Feature Flags
   ├── Toggle de features por tenant
   ├── Rollout gradual: você → 1 cliente → todos
   ├── Pacote: spatie/laravel-feature-flags
   └── Permite "ligar" feature em produção sem risk

3. Tenant de Teste em Produção
   ├── Tenant interno: "teste-interno"
   ├── Usado para validar features novas
   ├── Isolado dos clientes reais via tenant_id
   └── Você usa antes de habilitar para outros

4. Testes Automatizados Robustos
   ├── Pest framework
   ├── Cobertura mínima 70% nos paths críticos
   ├── Testes de integração para fluxos completos
   └── CI bloqueia merge se testes falharem
```

```
Setup Local Detalhado
─────────────────────────────────────────

docker-compose.yml inclui:
  ├── PHP 8.3 (com extensões Laravel)
  ├── PostgreSQL 16 (banco)
  ├── Redis (cache + queue)
  ├── MailHog (visualizar e-mails)
  ├── MinIO (S3-compatible local) — opcional
  └── Nginx

Configuração:
  ├── .env.local versionado (template)
  ├── .env (gitignored, customizado por dev)
  ├── docker-compose.yml versionado
  └── make commands para tasks comuns:
      ├── make up      → sobe ambiente
      ├── make down    → para ambiente
      ├── make fresh   → reset completo
      ├── make test    → roda testes
      └── make lint    → roda Pint + Larastan

Acesso local:
  ├── App: http://localhost:8000
  ├── MailHog: http://localhost:8025
  └── Subdomínios via dnsmasq:
      └── *.local → 127.0.0.1
```

```
Subir Produção EARLY (antes do MVP completo)
─────────────────────────────────────────

Estratégia:
  ├── Mês 1-2: 100% local (construir core)
  ├── Mês 3: Sobe produção mesmo incompleto
  │   ├── Domínio funcionando
  │   ├── Deploy automatizado funcionando
  │   ├── Banco em Neon Pro
  │   └── Apenas para validar pipeline
  └── Mês 4+: Itera com produção real desde o início

Por quê?
  └── Pipeline de deploy validada cedo
  └── Problemas de produção descobertos com calma
  └── Quando MVP estiver pronto, deploy é tranquilo
  └── Evita "syndrome do dia do lançamento"
```

```
Variáveis de Ambiente
─────────────────────────────────────────

LOCAL (.env, gitignored):
  APP_ENV=local
  APP_DEBUG=true
  DB_HOST=postgres
  STORAGE_PROVIDER=local
  MAIL_MAILER=mailhog
  PAGARME_ENV=sandbox

PRODUCTION (Forge env, criptografado):
  APP_ENV=production
  APP_DEBUG=false
  DB_HOST={neon-host}
  STORAGE_PROVIDER=r2
  MAIL_MAILER=resend
  PAGARME_ENV=production

Template versionado: .env.example
  └── Contém todas as variáveis sem valores reais
  └── Comentado explicando cada uma
```

---

## Justificativa

A escolha por bootstrap minimal se justifica por:

1. **Custo proporcional ao estágio** — Sem clientes, não justifica $110/mês em ambientes
2. **Substitutos modernos cobrem o gap** — Preview deployments + feature flags + testes
3. **Foco em entregar valor** — Tempo investido em features, não em infra
4. **Adicionar staging depois é fácil** — Migração não exige refatoração
5. **Disciplina de testes melhora qualidade** — Compensa ausência de staging

Os 2 riscos aceitos conscientemente:
- **Bug em produção é bug com cliente real** → mitigado por testes + feature flags
- **Migrations podem quebrar prod** → mitigado por backups automáticos + rollback

Estratégia de evolução clara: quando atingir 5+ clientes pagantes, criar staging permanente.

---

## Alternativas Consideradas

### Alternativa A — 4 Ambientes Completos

- **Descrição:** Local, Dev, Staging, Production desde o dia 1.
- **Pontos fortes:** Padrão profissional, máxima segurança.
- **Pontos fracos:** ~$110/mês fixo, complexidade operacional para solo founder.
- **Por que não foi escolhida:** Custo desproporcional ao estágio. Pode ser adicionado depois.

### Alternativa B — Apenas Production (Sem Local Estruturado)

- **Descrição:** Desenvolver direto em servidor remoto.
- **Pontos fortes:** Setup ainda mais minimal.
- **Pontos fracos:** Lento, perigoso, sem capacidade offline.
- **Por que não foi escolhida:** Local com Docker é o mínimo aceitável para qualidade.

### Alternativa C — Local + Staging (Sem Production Direta)

- **Descrição:** Subir só staging primeiro, production depois.
- **Pontos fortes:** Mais cautela.
- **Pontos fracos:** Sem cliente real, staging vira production de fato.
- **Por que não foi escolhida:** Production direta com testes é mais honesto.

---

## Consequências

### Positivas

- Custo bootstrap mínimo (~$38/mês total)
- Setup local profissional desde o dia 1
- Pipeline de produção validada cedo
- Disciplina de testes e feature flags
- Migração futura para staging é simples

### Negativas

- Bugs podem chegar em produção (mitigado por testes)
- Migrations exigem cuidado extra (mitigado por backup)
- Sem ambiente "neutro" para QA externa
- Demos exigem cuidado (usar tenant de teste em produção)

### Riscos

- **Risco:** Migration quebrar produção
  - **Mitigação:** Backup automático antes de toda migration. Migrations sempre reversíveis. Testar localmente com cópia de prod.

- **Risco:** Bug crítico chegar em produção sem detecção
  - **Mitigação:** Suíte de testes robusta (cobertura >70% em paths críticos). Sentry para erros. Feature flags para reverter rapidamente.

- **Risco:** Demo para cliente potencial mostrar dado real
  - **Mitigação:** Sempre usar tenant "teste-interno" para demos. Procedimento documentado.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- 5+ clientes pagantes em produção (justifica staging)
- Time crescer para 2+ pessoas (precisa ambiente compartilhado)
- Compliance exigir ambiente de testes formal
- Receita justificar $20-40/mês adicional

---

## Referências

- ADRs relacionadas: `ADR-018` (Git Strategy), `ADR-021` (Infrastructure)
- Trunk-Based Development: https://trunkbaseddevelopment.com

---

## Notas de Implementação

- Repositório com:
  - `docker-compose.yml` versionado
  - `.env.example` versionado (com TODAS as variáveis comentadas)
  - `Makefile` com comandos comuns
  - `README.md` com instruções de setup local
- Setup de produção:
  - Hetzner CX21 (€7,55/mês)
  - Laravel Forge ($12/mês)
  - Neon Pro ($19/mês)
  - Cloudflare Free ($0)
  - Resend Free ($0)
- Preview Deployments:
  - GitHub Actions ao abrir PR
  - Provisão temporária de banco (Neon branch)
  - URL única por PR
  - Limpeza automática ao fechar PR
- Feature Flags:
  - Pacote: spatie/laravel-feature-flags
  - Tabela: features
  - Tabela: feature_tenant (relacionamento)
- Tenant de teste:
  - Slug: "teste-interno"
  - Nunca aparece em listagens públicas
  - Usado para validação interna
- Documentação obrigatória:
  - `docs/runbooks/deploy.md` — como fazer deploy
  - `docs/runbooks/rollback.md` — como reverter
  - `docs/runbooks/incident.md` — em caso de problema
