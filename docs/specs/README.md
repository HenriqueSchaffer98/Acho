# Specs

Especificações detalhadas de cada feature antes da implementação.

## Para que serve

Spec é o documento que responde "o quê e por quê" antes de começar a codar. Ela traduz a decisão arquitetural (ADR) e a visão de produto em requisitos concretos e testáveis para uma feature específica.

**Spec ≠ ADR:**

- ADR responde "que decisão arquitetural tomamos e por quê"
- Spec responde "como uma feature específica deve se comportar"

## Quando criar

Crie uma spec **antes** de começar a implementar uma feature significativa. Vale para:

- Features novas que envolvem múltiplos componentes
- Features com lógica de negócio complexa
- Features que afetam UX/UI
- Refatorações arquiteturais grandes

**Não precisa criar spec para:**

- Bugfixes pontuais
- Mudanças triviais
- Refactor interno sem mudança de comportamento

## Como criar

1. Copie `_template.md` para `XXX-nome-da-feature.md` (numeração sequencial)
2. Preencha todas as seções relevantes
3. Self-review (mesmo solo founder)
4. Implementação segue a spec
5. Atualize a spec se mudar algo no caminho

## Hierarquia

Em conflito:

- ADR > Convention > Spec > Código

Spec deve obedecer convenções e ADRs. Se a spec contradiz uma convenção, ou a convenção precisa mudar, ou a spec precisa ser ajustada.

## Numeração

Sequencial: 001, 002, 003...

Não há "famílias" de specs. Numeração simples evita debates sobre categorização.

## Estrutura

Cada spec contém:

- Contexto e motivação
- Critérios de aceitação (Given/When/Then)
- Requisitos funcionais e não-funcionais
- Modelo de dados afetado
- API design
- UI/UX
- Edge cases
- Considerações de segurança
- Métricas de sucesso
- Plano de testes
- Dependências
- Plano de rollout

Ver `_template.md` para estrutura completa.

## Lista de specs

(Atualizar conforme criadas)

| # | Spec | Status | Implementação |
|---|------|--------|---------------|
| 001 | (a criar) | - | - |
