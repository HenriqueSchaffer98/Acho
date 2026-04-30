# Spec: [Nome da Feature]

> Spec é a especificação detalhada de uma feature antes de codar. Substitui "ir codando e descobrir os requisitos no caminho". Uma boa spec responde: o quê, por quê, como (alto nível), edge cases, e como medir sucesso.

---

## Status

`[Rascunho | Em revisão | Aprovada | Em implementação | Implementada | Cancelada]`

**Data:** `YYYY-MM-DD`
**Autor:** `Nome`
**Implementação prevista:** `vXX (MVP / v2 / etc)`

---

## Contexto

Por que essa feature existe? O que motivou? Que problema resolve? Que feedback de cliente / observação levou a ela?

Mantenha curto: 2-3 parágrafos. Aponte para vision/ADRs se precisar mais profundidade.

---

## Critérios de Aceitação

User stories no formato Given/When/Then. Cada item é verificável.

- [ ] **GIVEN** [contexto inicial] **WHEN** [ação] **THEN** [resultado esperado]
- [ ] **GIVEN** ... **WHEN** ... **THEN** ...
- [ ] ...

Exemplo:

- [ ] **GIVEN** um visitante anônimo na vitrine pública, **WHEN** ele clica em "Agendar visita" em um imóvel, **THEN** o sistema deve exibir modal de cadastro/login antes de prosseguir.
- [ ] **GIVEN** um cliente já cadastrado e logado, **WHEN** ele clica em "Agendar visita", **THEN** o sistema deve exibir o calendário de horários disponíveis.

---

## Requisitos Funcionais

Detalhamento do comportamento esperado.

### Fluxo principal

Descrição passo a passo:

1. Usuário ...
2. Sistema ...
3. ...

### Variações e fluxos alternativos

- Caso A: o que acontece se ...
- Caso B: o que acontece se ...

---

## Requisitos Não-Funcionais

### Performance

- Página carrega em < X segundos no mobile 4G
- Endpoint responde em < X ms (p95)
- Suporta X requisições concorrentes

### Segurança

- Autenticação obrigatória? Sim/Não
- Permissões: quais perfis podem acessar?
- Dados sensíveis envolvidos?
- Rate limiting necessário?

### UX

- Mobile-first?
- Acessibilidade (a11y) — qual nível?
- Internacionalização? (no MVP, só pt-BR)

---

## Modelo de Dados

### Tabelas afetadas

```sql
CREATE TABLE nova_tabela (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    -- campos específicos
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    deleted_at TIMESTAMP NULL
);

ALTER TABLE existente
ADD COLUMN nova_coluna VARCHAR(100);
```

### Migrations necessárias

- `create_xxx_table` — descrição
- `add_yyy_to_zzz_table` — descrição

---

## API Design

### Endpoints

| Método | URL                    | Descrição                  |
|--------|------------------------|----------------------------|
| POST   | `/api/...`             | ...                        |
| GET    | `/api/.../{id}`        | ...                        |

### Request

```json
{
  "campo": "valor"
}
```

### Response (sucesso)

```json
{
  "data": {
    "id": "uuid",
    "campo": "valor"
  }
}
```

### Response (erro)

```json
{
  "message": "...",
  "errors": {
    "campo": ["..."]
  }
}
```

---

## UI / UX

### Telas afetadas

- Tela X: descrição da mudança
- Tela Y: descrição da mudança

### Wireframes / mockups

(Linkar arquivos em `docs/specs/assets/` ou descrever em texto)

### Estados de UI

- Loading
- Erro
- Vazio
- Sucesso

---

## Eventos e Listeners

Eventos novos que serão disparados:

- `XxxEvent` — disparado quando ...
  - Listener: `YyyListener` — faz ...
  - Listener: `ZzzListener` — faz ...

---

## Edge Cases

Casos de borda mapeados:

- O que acontece se o usuário ...?
- E se o registro não existir mais?
- E se houver concorrência (2 usuários ao mesmo tempo)?
- E se o serviço externo falhar?
- E se o tenant for suspenso durante a operação?

---

## Considerações de Segurança

Riscos identificados e mitigações:

| Risco                                  | Mitigação                              |
|----------------------------------------|----------------------------------------|
| Vazamento entre tenants                | RLS + middleware + Policy              |
| Brute force                            | Rate limiting de X tentativas por minuto |
| Upload malicioso                       | Validação de magic bytes + re-encoding |

---

## Métricas de Sucesso

Como saberemos que a feature funciona?

- Métrica 1: ...
- Métrica 2: ...
- Métrica 3: ...

Exemplos: "% de visitantes que completam agendamento", "tempo médio entre interesse e visita confirmada".

---

## Plano de Testes

### Unit

- Service `XxxService` — casos a cobrir
- Rule `YyyRule` — casos a cobrir

### Feature (HTTP)

- Endpoint POST — casos de sucesso e erro
- Authorization — perfis com e sem permissão
- Tenant isolation — verificar não vazamento

### Manual / E2E

- Fluxo completo no browser
- Mobile (responsivo)
- Edge cases não cobertos por testes automatizados

---

## Dependências

### ADRs relevantes

- `ADR-XXX (Nome)` — explica decisão de ...
- `ADR-YYY` — ...

### Convenções relevantes

- `01-architecture.md` — para estrutura de Service
- `04-database.md` — para padrão de tabela

### Specs relacionadas

- `001-cadastro-imobiliaria.md` — depende para ...

### Bibliotecas / serviços externos

- Pacote X
- API externa Y (rate limit, custo, fallback)

---

## Plano de Rollout

Como essa feature será disponibilizada?

- Feature flag? Sim, `flag_xxx`
- Liberação gradual? Por % de tenants? Whitelisted?
- Migração de dados existentes necessária?
- Comunicação aos clientes?

---

## O que NÃO está nesta spec

Para evitar scope creep, listar explicitamente o que ficou de fora:

- ❌ Funcionalidade A (justificativa)
- ❌ Funcionalidade B (será spec separada XXX)

---

## Notas de Implementação

Observações para quem for implementar. Detalhes que não cabem nas seções acima.

- Ponto de atenção 1
- Ponto de atenção 2
- Decisão técnica X foi escolhida porque ...

---

## Histórico

- `YYYY-MM-DD`: Spec criada
- `YYYY-MM-DD`: Aprovada após revisão
- `YYYY-MM-DD`: Implementação iniciada
- `YYYY-MM-DD`: Implementada e mergeada
