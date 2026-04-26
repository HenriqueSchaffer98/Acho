# Architecture Decision Records (ADRs)

Este diretório contém o registro de todas as decisões arquiteturais tomadas no projeto SaaS Imobiliário.

## O Que São ADRs?

ADRs (Architecture Decision Records) são documentos curtos que capturam decisões arquiteturais importantes, junto com seu contexto e consequências. Eles servem como memória institucional do projeto.

## Princípios

1. **ADRs são imutáveis após aceitas.** Mudanças em decisões geram nova ADR que substitui a anterior.
2. **Toda decisão arquitetural significativa vira ADR.** Se não tiver ADR, não foi decidido.
3. **ADRs são a fonte de verdade.** Em caso de conflito, ADR vence.
4. **Hierarquia de verdade:** ADR > Convenção > Spec > Código.

## Estrutura de uma ADR

Cada ADR segue o template em [`_template.md`](./_template.md) e contém:

- Status (Aceita, Substituída, Obsoleta)
- Contexto que motivou a decisão
- A decisão em si
- Justificativa
- Alternativas consideradas
- Consequências (positivas, negativas, riscos)
- Critérios de revisão

## Índice das ADRs

### Conceituais e Estratégicas

- [ADR-001: Estratégia de Banco de Dados Multi-Tenant](./001-database-strategy.md)
- [ADR-002: Modelo de Negócio e Arquitetura de Tenancy](./002-tenancy-model.md)
- [ADR-003: Perfis de Usuário do Sistema](./003-user-profiles.md)
- [ADR-004: Contrato Digital Fora do MVP](./004-digital-contract.md)

### Escopo de Módulos

- [ADR-005: Estratégia de Notificações](./005-notifications.md)
- [ADR-006: Módulo Vitrine Pública](./006-listing-module.md)
- [ADR-007: Módulo Admin do Tenant](./007-admin-module.md)
- [ADR-008: Módulo de Agendamentos](./008-scheduling-module.md)
- [ADR-009: Módulo de Autenticação](./009-auth-module.md)

### Componentes Core

- [ADR-010: Painel Super Admin em Domínio Separado](./010-super-admin-domain.md)
- [ADR-011: Onboarding Automatizado de Imobiliárias](./011-automated-onboarding.md)
- [ADR-012: Trial e Estratégia de Planos](./012-trial-and-plans.md)
- [ADR-013: Gateway de Pagamento](./013-payment-gateway.md)
- [ADR-014: Estratégia de Autenticação](./014-authentication.md)
- [ADR-015: Storage de Imagens](./015-image-storage.md)
- [ADR-016: Roteamento por Subdomínio](./016-subdomain-routing.md)
- [ADR-017: Estratégia Multi-Ambiente Bootstrap](./017-environments.md)

### Stack Tecnológica

- [ADR-018: Estratégia de Git e CI/CD](./018-git-strategy.md)
- [ADR-019: Stack Tecnológica](./019-tech-stack.md)
- [ADR-020: Estratégia de Documentação SDD](./020-sdd-strategy.md)

### Infraestrutura e Segurança

- [ADR-021: Infraestrutura por Fase](./021-infrastructure.md)
- [ADR-022: Segurança Transversal](./022-security.md)
- [ADR-023: Criptografia de Senhas](./023-password-cryptography.md)

### Validação e Padrões

- [ADR-024: Estratégia de Proof of Concept](./024-poc-strategy.md)
- [ADR-025: Padrões de Projeto e Arquitetura](./025-project-patterns.md)

## Status Atual

Todas as ADRs estão em status **Aceita** e formam a base arquitetural do projeto.

## Como Criar Nova ADR

1. Copie [`_template.md`](./_template.md) para `XXX-titulo-curto.md`
2. Preencha todos os campos
3. Atualize este índice
4. Abra Pull Request com a nova ADR
5. Após aprovação, ADR vira imutável

## Como Substituir uma ADR

1. NÃO edite a ADR existente
2. Crie nova ADR com número subsequente
3. Marque a antiga com status "Substituída por: ADR-XXX"
4. Marque a nova com "Substitui: ADR-YYY"
5. Atualize este índice

---

**Última atualização:** Abril de 2026
