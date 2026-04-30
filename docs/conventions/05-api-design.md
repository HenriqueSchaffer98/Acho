# Convenção: API Design

## Princípios

A API é **interna** no MVP — consumida pelo próprio frontend (Inertia + React). Não é pública para terceiros.

Ainda assim, segue convenções REST consistentes para:

- Facilitar consumo no frontend
- Preparar para v2 onde pode virar API pública
- Permitir testes integrados claros
- Manter previsibilidade

---

## Padrão REST

### Endpoints

| Método  | URL                              | Ação                           |
|---------|----------------------------------|--------------------------------|
| GET     | `/api/imoveis`                   | Lista imóveis                  |
| GET     | `/api/imoveis/{id}`              | Detalhes de um imóvel          |
| POST    | `/api/imoveis`                   | Cria imóvel                    |
| PUT     | `/api/imoveis/{id}`              | Atualiza imóvel completo       |
| PATCH   | `/api/imoveis/{id}`              | Atualiza imóvel parcial        |
| DELETE  | `/api/imoveis/{id}`              | Deleta (soft delete) imóvel    |

### Recursos aninhados

```
GET    /api/imoveis/{id}/fotos                  # Fotos de um imóvel
POST   /api/imoveis/{id}/fotos                  # Adiciona foto
DELETE /api/imoveis/{id}/fotos/{fotoId}         # Remove foto

GET    /api/corretores/{id}/agendamentos        # Agendamentos de um corretor
```

### Ações que não são CRUD

Quando uma ação não cabe no CRUD (publicar, pausar, confirmar), use **subrecurso ou verbo no path**:

```
POST   /api/imoveis/{id}/publicar              # ✅ verbo claro
POST   /api/imoveis/{id}/pausar
POST   /api/agendamentos/{id}/confirmar
POST   /api/agendamentos/{id}/cancelar
```

**Não** use:

```
PUT    /api/imoveis/{id}?action=publicar       # ❌ ação em query string
POST   /api/publicar-imovel/{id}                # ❌ verbo no path raiz
```

---

## Status Codes

### Sucesso

| Código | Quando usar                                      |
|--------|--------------------------------------------------|
| `200`  | OK (GET, PUT, PATCH bem-sucedidos)              |
| `201`  | Created (POST que criou recurso)                 |
| `202`  | Accepted (operação assíncrona enfileirada)       |
| `204`  | No Content (DELETE bem-sucedido, sem corpo)      |

### Erro do cliente

| Código | Quando usar                                      |
|--------|--------------------------------------------------|
| `400`  | Bad Request (input malformado)                   |
| `401`  | Unauthorized (sem autenticação)                  |
| `403`  | Forbidden (autenticado mas sem permissão)        |
| `404`  | Not Found (recurso não existe)                   |
| `409`  | Conflict (ex: slug já em uso)                    |
| `422`  | Unprocessable Entity (validação de input)        |
| `429`  | Too Many Requests (rate limit)                   |

### Erro do servidor

| Código | Quando usar                                      |
|--------|--------------------------------------------------|
| `500`  | Internal Server Error (erro inesperado)          |
| `502`  | Bad Gateway (erro de upstream, ex: Pagar.me)     |
| `503`  | Service Unavailable (manutenção)                 |
| `504`  | Gateway Timeout                                  |

---

## Estrutura de Response

### Sucesso (recurso único)

```json
{
  "data": {
    "id": "uuid-aqui",
    "titulo": "Casa em Botafogo",
    "preco_centavos": 150000000,
    "tipo": "venda",
    "corretor": {
      "id": "uuid",
      "nome": "Maria Silva"
    }
  }
}
```

### Sucesso (lista paginada)

```json
{
  "data": [
    { "id": "...", "titulo": "..." },
    { "id": "...", "titulo": "..." }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 25,
    "total": 150,
    "last_page": 6
  },
  "links": {
    "first": "https://api/imoveis?page=1",
    "last": "https://api/imoveis?page=6",
    "prev": null,
    "next": "https://api/imoveis?page=2"
  }
}
```

### Erro de validação (422)

```json
{
  "message": "Os dados fornecidos são inválidos.",
  "errors": {
    "titulo": ["O campo título é obrigatório."],
    "preco_centavos": ["O preço deve ser maior que zero."],
    "corretor_id": ["O corretor selecionado não existe."]
  }
}
```

### Erro genérico

```json
{
  "message": "Você não tem permissão para acessar este recurso.",
  "code": "FORBIDDEN"
}
```

### Operação assíncrona (202)

```json
{
  "message": "Processamento em andamento",
  "job_id": "uuid",
  "status_url": "/api/jobs/uuid"
}
```

---

## API Resources (Transformers)

Use Laravel API Resources para padronizar serialização:

```php
class ImovelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'titulo' => $this->titulo,
            'preco_centavos' => $this->preco_centavos,
            'tipo' => $this->tipo,
            'status' => $this->status,
            'corretor' => UserResource::make($this->whenLoaded('corretor')),
            'fotos' => ImovelFotoResource::collection($this->whenLoaded('fotos')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

**Princípios:**

- Sempre use Resource (não retorne Model direto)
- `whenLoaded()` evita N+1 queries
- Datas em formato ISO 8601 (`toIso8601String()`)
- Valores monetários em centavos (cliente formata)

---

## Filtros, Ordenação, Paginação

### Filtros (query string)

```
GET /api/imoveis?tipo=venda&bairro=botafogo&preco_max=200000000
GET /api/imoveis?status=disponivel
GET /api/imoveis?corretor_id=uuid
```

### Ordenação

```
GET /api/imoveis?sort=created_at      # crescente (padrão)
GET /api/imoveis?sort=-created_at     # decrescente (prefixo `-`)
GET /api/imoveis?sort=preco,-created_at  # múltiplos
```

### Paginação

```
GET /api/imoveis?page=2&per_page=50   # default per_page=25, max=100
```

### Busca textual

```
GET /api/imoveis?q=apartamento+vista+mar
```

Search é livre, backend decide o que faz match (full-text PostgreSQL).

---

## Autenticação

### Header padrão

```
Authorization: Bearer {access_token}
```

Access Token é JWT validado via middleware `AuthenticateJWT` (ver `ADR-014`).

### Refresh Token

Vai em **cookie HttpOnly**, não em header. Frontend nunca toca nele.

### Sem autenticação

Endpoints públicos da vitrine não exigem auth:

```
GET /api/public/imoveis              # vitrine pública
GET /api/public/imoveis/{slug}       # detalhes públicos
GET /api/public/corretores/{slug}    # perfil público
```

---

## Versionamento

**No MVP:** sem versionamento explícito. API é interna, evolui junto com frontend.

**Quando virar pública (v2):**

- Prefixo: `/api/v1/imoveis`
- Múltiplas versões em paralelo durante deprecation
- Mudanças breaking exigem nova versão

---

## Rate Limiting

Aplicado por endpoint via Laravel RateLimiter:

| Endpoint                           | Limite                 |
|------------------------------------|------------------------|
| `/api/auth/login`                  | 5 / minuto / IP + 5 / minuto / email |
| `/api/auth/register`               | 3 / hora / IP          |
| `/api/auth/password-reset`         | 3 / hora / email       |
| `/api/public/*`                    | 100 / minuto / IP      |
| `/api/*` (autenticado)             | 60 / minuto / user     |
| `/api/webhooks/pagarme`            | 50 / minuto            |

Excedido → resposta `429`:

```json
{
  "message": "Muitas requisições. Tente novamente em alguns instantes.",
  "retry_after": 60
}
```

Header: `Retry-After: 60`.

---

## Webhooks recebidos

Endpoints que recebem webhook de serviços externos:

```
POST /api/webhooks/pagarme          # Eventos de pagamento
```

**Princípios:**

- Verificar assinatura HMAC antes de processar
- Idempotência: não processar mesmo evento 2x
- Tabela `webhook_events` registra tudo
- Processamento assíncrono via Queue
- Resposta `200` rápida, processamento depois

---

## Endpoints especiais

### Health Check

```
GET /health
```

Resposta:

```json
{
  "status": "ok",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "queue": "ok"
  },
  "version": "1.0.0",
  "timestamp": "2026-04-26T14:30:00Z"
}
```

Usado por UptimeRobot e load balancer (futuro).

### Validação de subdomínio (público)

```
GET /api/subdomain/check?slug=primoimoveis
```

Resposta:

```json
{
  "available": false,
  "suggestions": ["primoimoveis2", "primoimoveis-rj", "imoveisprimo"]
}
```

---

## Princípios

### Consistência

Todos os endpoints seguem mesma estrutura. Cliente sabe o que esperar.

### Previsibilidade

Mesmas convenções para mesmas operações em recursos diferentes.

### Errors são informativos

Mensagens de erro ajudam debug, sem vazar info sensível:

```json
// ✅ Bom
{ "message": "E-mail ou senha inválidos." }

// ❌ Ruim (vaza info)
{ "message": "Usuário existe mas senha está errada." }

// ❌ Ruim (sem contexto)
{ "message": "Erro." }
```

### Idempotência onde possível

`PUT` e `DELETE` são idempotentes naturalmente. Para operações sensíveis (pagamento), considere `Idempotency-Key` header.

---

## Referências

- ADRs relacionadas: `ADR-014` (Authentication), `ADR-022` (Security), `ADR-025` (Project Patterns)
- Outras conventions: `01-architecture.md`, `07-error-handling.md`
