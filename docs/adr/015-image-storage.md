# ADR-015: Storage de Imagens

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

A plataforma armazena fotos de imóveis (até 10 por imóvel) e outros assets visuais (logos de imobiliárias, fotos de corretores). A escolha do storage afeta:

- **Performance da vitrine** — Fotos lentas matam conversão (53% abandono em mobile a 4G se LCP > 3s)
- **Custo de infraestrutura** — Storage e banda podem dominar custos em escala
- **Custo de banda (egress)** — Vitrine serve milhares de visualizações por imóvel
- **Complexidade operacional** — Upload, processamento, CDN

A análise comparativa entre AWS S3 + CloudFront vs Cloudflare R2 + CDN revelou diferença gritante:

```
50 tenants ativos, ~150 GB storage, ~1 TB tráfego/mês:
  ├── AWS S3 + CloudFront: ~$91/mês
  └── Cloudflare R2 + CDN: ~$8/mês
                            ↑
              91% mais barato (egress free)
```

A decisão também precisa considerar ambientes de desenvolvimento — não faz sentido pagar storage cloud durante desenvolvimento local.

---

## Decisão

Adotar **Cloudflare R2** como storage de produção (com CDN da Cloudflare gratuita), e **filesystem local** em ambiente de desenvolvimento, abstraídos por interface única no código.

### Detalhamento

```
Arquitetura por Ambiente
─────────────────────────────────────────

LOCAL (desenvolvimento)
  Provider: filesystem
  Path: storage/app/public/
  URL: http://localhost:8000/storage/{path}
  Custo: $0
  Setup: Laravel padrão

PRODUÇÃO
  Provider: Cloudflare R2
  Bucket: seuapp-prod
  CDN: cdn.seuapp.com.br (Cloudflare Workers)
  Custo:
    ├── 10 GB free
    ├── Storage: $0.015/GB acima de 10 GB
    └── Egress: $0 (sempre gratuito)
```

```
Abstração no Código
─────────────────────────────────────────

Interface: StorageProvider
  ├── upload(file, path): string (url)
  ├── delete(path): void
  ├── getUrl(path): string
  └── getSignedUrl(path, ttl): string

Implementações:
  ├── LocalStorage (dev)
  ├── R2Storage (prod)
  └── S3Storage (preparado para migração futura)

Configuração via .env:
  STORAGE_PROVIDER=local | r2
  R2_BUCKET=seuapp-prod
  R2_ACCESS_KEY=...
  R2_SECRET_KEY=...
  R2_ENDPOINT=...
```

```
Fluxo de Upload
─────────────────────────────────────────

1. Usuário seleciona fotos no painel admin
       │
       ▼
2. Frontend valida (tamanho, tipo)
       │
       ▼
3. Backend gera Signed URLs (uma por foto)
       │
       ▼
4. Upload direto para R2 (não passa pelo backend)
       │
       ▼
5. R2 dispara webhook → processamento
   ├── Validação de magic bytes
   ├── Re-encoding (strip EXIF)
   ├── Geração de 4 versões
   └── Conversão para WebP
       │
       ▼
6. Metadados salvos no banco
       │
       ▼
7. URLs disponíveis via CDN
```

```
Versões Geradas Automaticamente
─────────────────────────────────────────

Versão       Dimensão       Uso              Tamanho típico
───────────────────────────────────────────────────────────
thumbnail    400x300        Cards listagem    ~30 KB
medium       800x600        Galeria mobile    ~80 KB
large        1600x1200      Galeria desktop   ~200 KB
original     conservada     Download/print    ~3 MB
───────────────────────────────────────────────────────────

Browser pede a versão certa via <picture>:
  Mobile     → medium
  Desktop    → large
  Retina 2x  → large com srcset
```

```
Estrutura de Pastas
─────────────────────────────────────────

seuapp-prod/
├── tenants/
│   ├── primoimoveis/
│   │   ├── imoveis/
│   │   │   ├── {imovel-uuid}/
│   │   │   │   ├── original/foto1.jpg
│   │   │   │   ├── large/foto1.webp
│   │   │   │   ├── medium/foto1.webp
│   │   │   │   └── thumbnail/foto1.webp
│   │   ├── corretores/
│   │   │   └── {user-uuid}/avatar.webp
│   │   └── tenant/
│   │       └── logo.webp
│   │
│   └── casanova/
│       └── ...

Vantagens dessa estrutura:
  ├── Fácil deletar tudo de um tenant
  ├── Auditoria por imobiliária
  └── Migração futura de tenant simplificada
```

```
Validações de Segurança
─────────────────────────────────────────

Frontend (UX, não segurança):
  ├── Tipo: image/jpeg, image/png, image/heic
  ├── Tamanho máximo: 10 MB
  └── Preview antes de upload

Backend (segurança real):
  ├── Magic bytes (não confiar em extensão)
  │   ├── jpg: FF D8 FF
  │   ├── png: 89 50 4E 47
  │   └── heic: FT YP
  ├── MIME type real via finfo
  ├── Re-encoding obrigatório (remove payload escondido)
  ├── Strip EXIF (privacidade — remove geolocalização)
  ├── Limite de tamanho (10 MB)
  └── Limite de fotos por imóvel (10)

Bucket R2:
  ├── Sem permissão de execução
  ├── Acesso via CDN apenas (não direto)
  ├── Origin não exposto publicamente
  └── Hotlink protection no Cloudflare
```

```
Limites no MVP
─────────────────────────────────────────

Por imóvel:
  ├── Máximo 10 fotos
  ├── Máximo 10 MB por foto
  └── Total: 100 MB de fotos brutas / imóvel

Estimativa para 50 tenants:
  ├── 50 tenants × 50 imóveis × 10 fotos = 25.000 fotos
  ├── ~150 GB storage total (com 4 versões)
  └── Cabe confortavelmente no R2 (free tier 10GB
      cobre ~3-5 tenants iniciais, escala custa $0.015/GB)
```

---

## Justificativa

A escolha por Cloudflare R2 + abstração local se justifica por:

1. **Custo absurdamente menor** — Egress gratuito muda jogo (91% mais barato em escala)
2. **CDN incluso** — Cloudflare CDN gratuito serve direto do R2
3. **Compatibilidade S3** — API idêntica permite migração futura
4. **Desenvolvimento local sem custo** — Filesystem local zera custo durante dev
5. **Abstração protege futuro** — Trocar provider é refatoração mínima

Por que não AWS S3 desde o início:
- Egress paga pela mesma feature (servir imagens muitas vezes)
- Sem CDN incluso (CloudFront cobra também)
- Curva de aprendizado IAM/Bucket Policies maior
- Lock-in mais difícil de sair

Por que não MinIO local em dev:
- Filesystem é mais simples ainda
- MinIO adiciona container Docker desnecessário
- Sem benefício real para desenvolvimento solo

---

## Alternativas Consideradas

### Alternativa A — AWS S3 + CloudFront

- **Descrição:** Stack AWS tradicional para storage e CDN.
- **Pontos fortes:** Padrão da indústria, integrações AWS.
- **Pontos fracos:** Egress caro ($0.085/GB), CloudFront cobra também.
- **Por que não foi escolhida:** Custo desnecessariamente alto. Não há benefício técnico que justifique.

### Alternativa B — Cloudflare R2 Sem Abstração (Direto em Dev Também)

- **Descrição:** Usar R2 em todos os ambientes.
- **Pontos fortes:** Paridade total entre dev e prod.
- **Pontos fracos:** Custo (mínimo, mas existe), dependência de internet em dev.
- **Por que não foi escolhida:** Filesystem local é mais simples. Abstração já garante paridade funcional.

### Alternativa C — Backblaze B2

- **Descrição:** Outro provedor com egress barato.
- **Pontos fortes:** Mais barato que AWS.
- **Pontos fracos:** Sem CDN incluso. Comunidade menor.
- **Por que não foi escolhida:** R2 é mais integrado com Cloudflare CDN que já vamos usar.

### Alternativa D — Self-hosted MinIO

- **Descrição:** Rodar próprio storage S3-compatible.
- **Pontos fortes:** Sem custo de provedor.
- **Pontos fracos:** Custo de servidor + complexidade operacional.
- **Por que não foi escolhida:** Inviável para bootstrap. Apenas faria sentido em escala enterprise.

---

## Consequências

### Positivas

- Custo de storage e CDN próximo de zero no MVP
- Performance excelente (CDN global da Cloudflare)
- Desenvolvimento local sem custo
- Migração futura para S3 viável (mesma API)
- Fotos otimizadas automaticamente (WebP, múltiplas versões)
- Egress gratuito permite escala sem surpresa

### Negativas

- Lock-in com ecossistema Cloudflare
- Cloudflare Workers para processamento exige aprendizado
- R2 é serviço relativamente novo (lançado 2022)
- Sem alguns recursos avançados do S3 (Lifecycle complexo, tags ricas)

### Riscos

- **Risco:** Cloudflare R2 ter problema operacional
  - **Mitigação:** Backup periódico em outro provider (S3 ou local). Versioning ativo no R2.

- **Risco:** Abstração de storage não cobrir caso específico
  - **Mitigação:** Interface bem desenhada cobre casos comuns. Casos extremos podem usar adapter específico.

- **Risco:** Upload malicioso passar das validações
  - **Mitigação:** Magic bytes + re-encoding + bucket sem execução. Defesa em profundidade.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Volume passar de 1 TB de storage (negociar contrato Cloudflare)
- Surgir necessidade de Lifecycle complexo (S3 tem mais opções)
- Cloudflare aumentar significativamente preços
- Compliance exigir provider específico (ex: hosted in Brazil)

---

## Referências

- ADRs relacionadas: `ADR-007` (Admin Module), `ADR-021` (Infrastructure), `ADR-022` (Security)
- Cloudflare R2: https://developers.cloudflare.com/r2/
- Spatie Media Library: https://spatie.be/docs/laravel-medialibrary

---

## Notas de Implementação

- Pacote: `spatie/laravel-medialibrary` para gerenciar mídia
- Driver S3 padrão do Laravel funciona com R2 (compatibilidade)
- Configuração em `config/filesystems.php`:
  - Disco `r2` apontando para Cloudflare R2
  - Disco `local` para desenvolvimento
- Service `ImageProcessingService`:
  - Recebe upload original
  - Gera 4 versões via Intervention Image
  - Strip EXIF
  - Upload para storage configurado
- Job assíncrono para processamento (não bloqueia request)
- URLs públicas via CDN: `https://cdn.seuapp.com.br/{path}`
- Cloudflare Workers para image transformation (opcional, futuro)
- Backup: snapshot do bucket R2 para outro local mensalmente
- Limpeza:
  - Imóvel deletado → fotos marcadas para remoção
  - Job semanal: deleta órfãos
- HTML usa `<picture>` com srcset para responsive images
- Lazy loading nativo: `loading="lazy"` em imagens fora do viewport
