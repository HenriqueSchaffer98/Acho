# ADR-003: Perfis de Usuário do Sistema

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

O sistema precisa atender diferentes tipos de usuários com permissões e funcionalidades distintas. Definir os perfis de usuário desde o início é crítico para:

- Estruturar o sistema de autenticação e autorização
- Modelar o banco de dados corretamente
- Definir telas e fluxos por perfil
- Estabelecer limites de permissão por papel

Em um SaaS imobiliário multi-tenant, três tipos principais de usuários interagem com o sistema:

1. Pessoas que gerenciam a imobiliária (admins)
2. Pessoas que atendem clientes (corretores)
3. Pessoas que buscam imóveis (clientes finais)

Adicionalmente, há um quarto contexto de usuário externo ao tenant: a equipe da plataforma que opera o SaaS.

---

## Decisão

Adotar 3 perfis de usuário dentro do tenant no MVP, com hierarquia clara de permissões. O perfil de Super Admin (operadores da plataforma) é tratado em ADR separada.

### Detalhamento

```
Hierarquia de Perfis no Tenant
─────────────────────────────────────────

ADMIN
  └── Permissão total dentro do tenant
  └── Gestão de imóveis, corretores, agendamentos
  └── Configurações da imobiliária
  └── Acesso a relatórios e dashboards
  └── Convida e gerencia outros usuários

CORRETOR
  └── Gerencia seus próprios imóveis e leads
  └── Visualiza e responde agendamentos
  └── Acessa dados de clientes que agendaram
  └── Configurações pessoais (horários, foto)
  └── Não acessa dados financeiros do tenant

CLIENTE
  └── Cadastro auto-serviço na vitrine pública
  └── Agenda visitas em imóveis
  └── Visualiza histórico de agendamentos
  └── Atualiza próprios dados
  └── Não acessa o painel administrativo
```

```
Perfis Fora do MVP (v2 ou futuro)
─────────────────────────────────────────

GERENTE (v2)
  └── Supervisiona corretores
  └── Não acessa configurações financeiras
  └── Hierarquia intermediária entre admin e corretor

PROPRIETÁRIO (v2)
  └── Dono do imóvel cadastrado
  └── Acompanha visitas em seu imóvel
  └── Recebe relatórios de interesse
```

```
Estrutura no Banco
─────────────────────────────────────────

Tabela: users
  ├── id (uuid)
  ├── tenant_id (uuid, NULL para super admin)
  ├── name, email, password
  └── ...

Tabela: roles (Spatie Permission)
  ├── admin
  ├── corretor
  └── cliente

Tabela: model_has_roles
  └── Vincula usuário a role(s)

E-mail é único POR TENANT (não global):
  └── maria@gmail.com pode existir em
      múltiplos tenants como contas distintas
```

### Matriz de Permissões

| Recurso              | Admin | Corretor | Cliente |
|----------------------|-------|----------|---------|
| Gerenciar imóveis    | ✅    | Próprios | ❌      |
| Gerenciar corretores | ✅    | ❌       | ❌      |
| Configurar tenant    | ✅    | ❌       | ❌      |
| Ver agendamentos     | Todos | Próprios | Próprios|
| Confirmar visitas    | ✅    | Próprios | ❌      |
| Cadastro público     | -     | -        | ✅      |
| Editar próprio perfil| ✅    | ✅       | ✅      |

---

## Justificativa

A escolha por 3 perfis no MVP equilibra:

1. **Simplicidade de implementação** — Menos perfis = menos lógica de autorização
2. **Cobertura suficiente** — 3 perfis atendem 95% dos casos das imobiliárias pequenas e médias
3. **Evolução natural** — Adicionar Gerente ou Proprietário no futuro não exige refatoração
4. **Separação clara de responsabilidades** — Cada perfil tem propósito distinto

Perfis adicionais (Gerente, Proprietário) foram conscientemente postergados porque:
- Adicionam complexidade ao MVP sem valor proporcional
- Imobiliárias pequenas (foco do MVP) raramente precisam dessa estrutura
- Podem ser adicionados na v2 sem quebrar arquitetura existente

---

## Alternativas Consideradas

### Alternativa A — Perfil Único (Apenas "Usuário do Tenant")

- **Descrição:** Um único perfil com permissões granulares atribuídas individualmente.
- **Pontos fortes:** Máxima flexibilidade, sem hierarquia rígida.
- **Pontos fracos:** Configuração de permissões fica complexa, UX confusa para o admin.
- **Por que não foi escolhida:** Trade-off de UX não compensa a flexibilidade extra no MVP.

### Alternativa B — 5+ Perfis Desde o Início

- **Descrição:** Admin, Gerente, Corretor Sênior, Corretor, Cliente, Proprietário.
- **Pontos fortes:** Cobre todos os cenários imagináveis.
- **Pontos fracos:** Aumenta complexidade de autorização e UI desnecessariamente.
- **Por que não foi escolhida:** Excesso de granularidade no MVP. Adicionar depois é trivial.

### Alternativa C — Permissões Granulares por Função

- **Descrição:** Sistema RBAC com dezenas de permissões atribuíveis.
- **Pontos fortes:** Customização total por imobiliária.
- **Pontos fracos:** Complexidade alta de implementação e configuração.
- **Por que não foi escolhida:** Overkill para MVP. Spatie Permission já permite migrar para isso depois.

---

## Consequências

### Positivas

- Sistema de autorização simples e previsível
- UX clara para o admin (3 perfis para escolher)
- Fácil de testar (menos combinações de permissão)
- Pacote Spatie Permission integra naturalmente
- Filament respeita roles automaticamente

### Negativas

- Imobiliárias com hierarquias complexas precisam adaptar
- Cliente final não pode ter "níveis" (ex: VIP, regular)
- Sem suporte nativo a delegação parcial de permissões

### Riscos

- **Risco:** Cliente reclamar da falta de perfil intermediário (Gerente)
  - **Mitigação:** Coletar feedback e priorizar adição na v2 se for tema recorrente

- **Risco:** Corretor com permissão sobre todos os imóveis do tenant
  - **Mitigação:** Decisão consciente — corretor vê todos os imóveis para ajudar colegas, mas só edita os próprios

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- 3+ clientes solicitarem perfil de Gerente intermediário
- Surgir necessidade de cadastro de Proprietários como feature
- Concorrentes oferecerem hierarquia mais granular como diferencial
- Imobiliárias enterprise (>50 corretores) virarem público-alvo

---

## Referências

- ADRs relacionadas: `ADR-009` (Auth Module), `ADR-014` (Authentication), `ADR-010` (Super Admin), `ADR-022` (Security)
- Pacote: spatie/laravel-permission

---

## Notas de Implementação

- Roles devem ser seedadas em migração inicial
- Toda Policy do Laravel deve verificar role + tenant_id
- Filament Resources respeitam Policy automaticamente
- Cliente nunca acessa rotas de `/admin` (verificação no middleware)
- Convite de corretor envia e-mail com token (48h validade)
- Cliente faz auto-cadastro pela vitrine pública (sem necessidade de convite)
