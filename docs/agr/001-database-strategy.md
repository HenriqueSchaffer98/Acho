# ADR-001: Estratégia de Banco de Dados Multi-Tenant

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

O projeto SaaS Imobiliário precisa atender múltiplas imobiliárias (tenants) em uma mesma plataforma, com isolamento de dados entre elas. A escolha da estratégia de tenancy a nível de banco de dados é uma das decisões arquiteturais mais críticas do projeto, pois afeta diretamente:

- Custo de infraestrutura
- Complexidade operacional (migrations, backups, monitoramento)
- Risco de vazamento de dados entre tenants
- Capacidade de escalar conforme o produto cresce

O projeto inicia com objetivo de suportar até 50 imobiliárias no MVP, com foco em bootstrap (custo mínimo) e crescimento gradual.

### Estratégias Existentes no Mercado

1. **Database por tenant** (isolamento físico total)
2. **Schema por tenant** (isolamento lógico forte)
3. **Row-level com tenant_id** (isolamento via aplicação)
4. **Row-level com RLS no banco** (isolamento via banco)

---

## Decisão

Adotar estratégia de **banco de dados único compartilhado com Row-Level Security (RLS) do PostgreSQL**, combinando isolamento via aplicação (middleware) e via banco (políticas RLS).

### Detalhamento

```
Estrutura de isolamento em 3 camadas:

Camada 1 — Aplicação (Middleware)
  └── Identifica tenant pelo subdomínio
  └── Injeta tenant_id no contexto da requisição
  └── Define app.tenant_id no Postgres antes de queries

Camada 2 — Modelos (Eloquent Global Scope)
  └── Todo Model herda de BaseTenantModel
  └── BaseTenantModel aplica scope automático de tenant_id

Camada 3 — Banco (PostgreSQL RLS)
  └── Toda tabela de negócio possui RLS habilitado
  └── Políticas garantem que queries só retornem dados
      onde tenant_id = current_setting('app.tenant_id')
```

```sql
-- Exemplo de política RLS
ALTER TABLE imoveis ENABLE ROW LEVEL SECURITY;

CREATE POLICY tenant_isolation ON imoveis
  USING (tenant_id = current_setting('app.tenant_id')::uuid);

-- Antes de queries, aplicação executa:
SET app.tenant_id = 'uuid-do-tenant-atual';
```

### Estrutura Padrão de Tabelas

Toda tabela de negócio segue o padrão:

```sql
CREATE TABLE exemplo (
  id          uuid PRIMARY KEY,
  tenant_id   uuid NOT NULL REFERENCES tenants(id),
  -- demais colunas
  created_at  timestamp NOT NULL,
  updated_at  timestamp NOT NULL,
  deleted_at  timestamp NULL
);

CREATE INDEX idx_exemplo_tenant ON exemplo(tenant_id);
ALTER TABLE exemplo ENABLE ROW LEVEL SECURITY;
```

---

## Justificativa

A estratégia escolhida atende aos critérios mais importantes do projeto:

1. **Custo de infraestrutura mínimo** — uma única instância PostgreSQL serve todos os tenants
2. **Operação simplificada** — uma migration roda em todos os tenants simultaneamente
3. **Isolamento robusto** — RLS no banco protege mesmo contra erros na aplicação
4. **Escalabilidade adequada** — suporta confortavelmente até centenas de tenants
5. **Defesa em profundidade** — múltiplas camadas independentes de isolamento

A abordagem alinha-se com práticas de SaaS modernos como Shopify, Linear e Notion, que utilizam variações desta estratégia em escala.

---

## Alternativas Consideradas

### Alternativa A — Database Por Tenant (Aurora Serverless v2)

- **Descrição:** Cada imobiliária recebe um cluster Aurora Serverless dedicado.
- **Pontos fortes:** Isolamento físico total, sem possibilidade de vazamento entre tenants.
- **Pontos fracos:** Custo mínimo de ~$43/mês por cluster (50 tenants = ~$2.150/mês). Migrations são complexas (precisa rodar em N bancos).
- **Por que não foi escolhida:** Inviabilidade financeira para o estágio atual (0–50 tenants) e overhead operacional desproporcional.

### Alternativa B — RDS t3.micro Por Tenant

- **Descrição:** Instância RDS dedicada por imobiliária.
- **Pontos fortes:** Isolamento total com custo mais previsível.
- **Pontos fracos:** ~$15/mês por instância (50 tenants = ~$750/mês). Complexidade operacional alta.
- **Por que não foi escolhida:** Custo ainda inviável para bootstrap e overhead de gerenciar 50 instâncias.

### Alternativa C — Schema Por Tenant

- **Descrição:** Um banco PostgreSQL com schema separado para cada tenant.
- **Pontos fortes:** Isolamento lógico forte, custo baixo.
- **Pontos fracos:** Migrations precisam rodar em cada schema individualmente. Quando o número de schemas cresce (>100), começa a ter limitações de performance no Postgres.
- **Por que não foi escolhida:** Complexidade operacional crescente conforme escala.

### Alternativa D — Row-Level Apenas Via Aplicação (sem RLS)

- **Descrição:** Filtro de tenant_id apenas no nível do ORM.
- **Pontos fortes:** Implementação mais simples.
- **Pontos fracos:** Vazamento garantido se uma única query escapar do filtro.
- **Por que não foi escolhida:** Risco de segurança inaceitável para banco compartilhado entre múltiplos clientes.

---

## Consequências

### Positivas

- Custo de infraestrutura inicial ~$30–80/mês (vs $850+ em alternativas)
- Migrations simples (uma migration = todos os tenants atualizados)
- Backup unificado (um único banco para fazer backup)
- Isolamento de dados garantido em nível de banco (RLS)
- Defesa em profundidade contra erros de código

### Negativas

- Não permite customizações de schema por tenant
- Tenant individual não pode ser "exportado" facilmente como banco separado
- Performance de queries pode degradar em cenários extremos (>1M registros por tabela)
- Backup individual de tenant é mais complexo (precisa filtrar dados)

### Riscos

- **Risco:** Erro humano ao desabilitar RLS em produção
  - **Mitigação:** Usuário de aplicação sem permissão de ALTER TABLE ou alteração de políticas RLS. Apenas superuser pode modificar.

- **Risco:** Cache de tenant envenenado servindo dados errados
  - **Mitigação:** TTL curto no cache (60s) e invalidação explícita ao atualizar tenant.

- **Risco:** Query manual durante debug pulando RLS
  - **Mitigação:** Documentação clara, ambientes de produção sem acesso direto via psql, todas as queries devem passar por contexto de aplicação.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Número de tenants ativos ultrapassar 500
- Cliente enterprise solicitar isolamento físico de dados (compliance)
- Performance de queries começar a degradar consistentemente
- Surgir requisito de customização de schema por tenant

---

## Referências

- ADRs relacionadas: `ADR-002` (Tenancy Model), `ADR-016` (Subdomain Routing), `ADR-022` (Security)
- Documentação PostgreSQL RLS: https://www.postgresql.org/docs/current/ddl-rowsecurity.html
- Pacote Laravel: stancl/tenancy

---

## Notas de Implementação

- Toda nova tabela de negócio deve incluir `tenant_id` desde a primeira migration
- Toda Model de negócio deve estender `BaseTenantModel` que aplica scope automaticamente
- Testes obrigatórios verificam isolamento entre tenants para toda nova feature
- Monitorar logs do Postgres para queries que tentem acessar dados de outro tenant
