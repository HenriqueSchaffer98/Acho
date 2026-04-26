# ADR-010: Painel Super Admin em Domínio Separado

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Como SaaS multi-tenant, a plataforma precisa de um painel operacional para os donos do produto (operadores) gerenciarem todas as imobiliárias clientes. Esse painel é distinto do painel admin do tenant — ele opera "acima" dos tenants.

Funcionalidades necessárias para esse painel super admin:

- Listar e gerenciar todas as imobiliárias
- Ativar, suspender, cancelar contas
- Visualizar uso e métricas por tenant
- Realizar suporte (login as)
- Gerenciar pagamentos e cobranças
- Configurar feature flags por tenant
- Comunicação com clientes em massa

A questão arquitetural é: onde esse painel fica?

Existem duas opções principais:

1. **Domínio separado** (`admin.seuapp.com.br`)
2. **Mesma aplicação** (`seuapp.com.br/admin`)

Cada opção tem implicações de segurança, complexidade de roteamento e isolamento.

---

## Decisão

Implementar o painel Super Admin em **domínio separado** (`admin.seuapp.com.br`), totalmente isolado do fluxo de tenant, com acesso restrito aos operadores da plataforma.

### Detalhamento

```
Arquitetura de Domínios
─────────────────────────────────────────

seuapp.com.br
  └── Landing page da plataforma
  └── Cadastro de novas imobiliárias

primoimoveis.seuapp.com.br
  └── Vitrine pública da Primo Imóveis
  └── Painel admin do tenant
  └── Tem RLS aplicado

casanova.seuapp.com.br
  └── Vitrine pública da Casa Nova
  └── Painel admin do tenant
  └── Tem RLS aplicado

admin.seuapp.com.br  ← Super Admin
  └── Painel dos operadores da plataforma
  └── Bypass controlado de RLS
  └── Acesso restrito (whitelist de e-mails)
```

```
Funcionalidades do Super Admin (MVP)
─────────────────────────────────────────

1. Gestão de Tenants
   ├── Listar todos os tenants
   ├── Filtrar por status, plano, data
   ├── Ativar / suspender / cancelar
   ├── Editar dados básicos
   └── Ver detalhes de uso

2. Pagamentos e Cobrança
   ├── Visualizar status financeiro por tenant
   ├── Histórico de cobranças (Pagar.me)
   ├── Marcar pagamento manual (recovery)
   └── Suspender por inadimplência

3. Suporte Operacional
   ├── "Login as" — assumir identidade de admin
   │   └── Token temporário (15min)
   │   └── Banner indicando impersonação
   │   └── Auditoria registrada
   └── Visualizar dados do tenant (read-only)

4. Métricas Globais
   ├── Total de tenants ativos
   ├── MRR (receita mensal recorrente)
   ├── Churn rate
   ├── Tenants em trial
   └── Crescimento mês a mês

5. Feature Flags
   ├── Ativar/desativar features por tenant
   ├── Beta testing com clientes selecionados
   └── Rollout gradual

6. Comunicação (v2)
   └── Envio de e-mail em massa para tenants
```

```
Modelo de Permissões
─────────────────────────────────────────

Tabela: super_admin_users
  ├── id (uuid)
  ├── name, email, password
  ├── role (operator, support, financial)
  └── created_at

Roles:
  ├── operator    → acesso total
  ├── support     → suporte (login as), sem financeiro
  └── financial   → cobrança, sem login as
```

```
Bypass Controlado de RLS
─────────────────────────────────────────

Super Admin precisa ler dados de qualquer tenant
sem aplicar filtro automático.

Mecanismo:
  └── Super Admin não passa pelo middleware de tenant
  └── Conexão usa role Postgres com BYPASSRLS
  └── Auditoria registra todo acesso a dados
  └── Logs de "login as" são imutáveis

Cuidado especial:
  └── Conexão Super Admin é separada (não pool padrão)
  └── Operações sensíveis exigem confirmação
  └── Toda ação é auditada (tabela super_admin_audit_log)
```

```
Funcionalidade "Login as"
─────────────────────────────────────────

Fluxo:
  1. Super admin acessa lista de tenants
  2. Clica em "Acessar como admin" no tenant X
  3. Sistema gera token temporário (15min)
     └── JWT com flag impersonating: true
     └── operator_id e tenant_id no payload
  4. Redireciona para subdomínio do tenant
  5. Cookie de sessão criado com flag de impersonação
  6. Banner visível em todas as páginas:
     "Você está visualizando como [nome do admin]
      [Sair da impersonação]"
  7. Toda ação é logada com flag de impersonação
  8. Token expira em 15min ou ao "sair"
```

---

## Justificativa

A escolha por domínio separado se justifica por:

1. **Isolamento de fluxo** — Super Admin não passa pelo middleware de tenant, evitando complexidade
2. **Segurança aumentada** — Domínio separado permite WAF/regras específicas
3. **Facilita auditoria** — Todo acesso a `admin.*` é claramente identificável
4. **Permite restrições de IP** — Pode-se exigir VPN ou IP whitelisted no futuro
5. **Cookies isolados** — Sem risco de vazamento entre painéis
6. **Clareza arquitetural** — Separação física reflete separação de responsabilidades

A alternativa (`seuapp.com.br/admin`) foi rejeitada porque:
- Middleware de tenant precisaria conhecer rota especial
- Cookies de tenant poderiam interferir
- Análise de logs ficaria mais confusa

---

## Alternativas Consideradas

### Alternativa A — Subdomínio Especial (`app.seuapp.com.br/admin`)

- **Descrição:** Painel super admin em rota da aplicação principal.
- **Pontos fortes:** Mesma codebase, deploy unificado.
- **Pontos fracos:** Middleware precisa lógica especial. Cookies podem vazar.
- **Por que não foi escolhida:** Complexidade desnecessária. Domínio separado é mais limpo.

### Alternativa B — Aplicação Completamente Separada

- **Descrição:** Repositório e deploy completamente isolados.
- **Pontos fortes:** Isolamento máximo.
- **Pontos fracos:** Duplicação de código (Models, Services). Sincronização de schemas.
- **Por que não foi escolhida:** Overkill para MVP. Mesma aplicação com domínio separado resolve.

### Alternativa C — Acesso Via SSH (Sem Painel Web)

- **Descrição:** Operações via tinker/CLI no servidor.
- **Pontos fortes:** Sem desenvolvimento de painel.
- **Pontos fracos:** Inviável conforme escala. Muito propenso a erro.
- **Por que não foi escolhida:** Funciona para 1-2 tenants. Insustentável a partir disso.

---

## Consequências

### Positivas

- Painel claramente separado do fluxo de tenant
- Segurança em camadas (domínio + auth + permissões)
- Auditoria simplificada
- Suporte ao cliente facilitado por "Login as"
- Possibilidade de restrições futuras (IP whitelist, VPN)

### Negativas

- Subdomínio adicional para configurar (DNS + SSL)
- Lógica de bypass de RLS exige cuidado para não vazar
- "Login as" é poderoso e exige auditoria robusta
- Mais código para manter (admin separado dos tenants)

### Riscos

- **Risco:** Bypass de RLS expor dados acidentalmente
  - **Mitigação:** Conexão de banco específica para super admin (role com BYPASSRLS), logs detalhados, code review obrigatório.

- **Risco:** Login as comprometido vazar dados de tenants
  - **Mitigação:** TTL curto (15min), auditoria de toda ação, banner visível, opção de revogar acesso.

- **Risco:** Subdomínio admin descoberto e atacado
  - **Mitigação:** Cloudflare WAF mais agressivo nessa origem. Whitelist de IPs (futuro).

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Equipe crescer (2+ pessoas) e exigir mais controles de acesso
- Compliance exigir VPN/IP whitelisting
- Volume de tenants justificar painel separado por região
- Surgir necessidade de operações em batch (auto-scaling, etc.)

---

## Referências

- ADRs relacionadas: `ADR-001` (Database), `ADR-016` (Subdomain Routing), `ADR-022` (Security)
- Documentação Filament: https://filamentphp.com (usado também no super admin)

---

## Notas de Implementação

- Filament configurado com 2 panels:
  - `app/Filament/Tenant/` — para subdomínios de tenants
  - `app/Filament/SuperAdmin/` — para `admin.seuapp.com.br`
- Middleware `RestrictToSuperAdmin` valida domínio + role
- Conexão Postgres separada em `config/database.php`:
  - `pgsql_admin` com role `super_admin_role` (BYPASSRLS)
- Tabela `super_admin_audit_log` registra:
  - operator_id, action, tenant_id (se aplicável), timestamp, ip
- Login as gera token JWT com `impersonating: true`
- Banner de impersonação injetado via middleware no subdomínio
- Sair da impersonação invalida token e limpa cookie especial
- Configuração de DNS: `admin.seuapp.com.br` aponta para mesma origem
- SSL coberto pelo wildcard `*.seuapp.com.br`
