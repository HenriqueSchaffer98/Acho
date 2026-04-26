# ADR-024: Estratégia de Proof of Concept

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Antes de construir o MVP completo (estimado em 4-6 meses para solo founder), faz sentido validar **estruturalmente** a viabilidade técnica e de mercado da plataforma com uma POC (Proof of Concept) de duração curta.

A POC tem objetivos distintos do MVP:

- **MVP**: Versão mínima viável vendível, com clientes reais pagando
- **POC**: Validação de hipóteses técnicas críticas e mercado

Pular a POC e ir direto para MVP cria riscos:

- Investir 4-6 meses em algo tecnicamente inviável
- Descobrir limitações fundamentais tarde demais
- Pivotar com codebase grande já investida
- Validar mercado depois de produto pronto

A POC bem feita responde:

- Multi-tenancy com RLS funciona como esperado?
- Subdomínios resolvem em local development?
- Onboarding automático realmente funciona end-to-end?
- Filament integra com a stack?
- Imobiliárias enxergam valor no produto?
- Quanto tempo demanda cada feature crítica?

---

## Decisão

Executar **POC vertical de 4 semanas** focada em validar pipeline completo do produto, com tenant de teste real funcionando localmente. Em paralelo, validar mercado conversando com 5-10 imobiliárias-alvo.

### Detalhamento

```
Cronograma da POC: 4 Semanas
─────────────────────────────────────────

SEMANA 1 — Setup e Multi-Tenancy
─────────────────────────────────────────
Objetivo: validar arquitetura básica

Tarefas:
  ├── [ ] Setup Laravel 13 + Docker
  ├── [ ] PostgreSQL 16 com RLS configurado
  ├── [ ] dnsmasq para *.local
  ├── [ ] stancl/tenancy básico
  ├── [ ] BaseTenantModel com scope
  ├── [ ] Middleware TenantResolver
  ├── [ ] 2 tenants seed (primoimoveis.local, casanova.local)
  └── [ ] Test: tenant A não vê dados de tenant B

Validação ao fim da semana:
  ✅ Subdomínio identifica tenant
  ✅ RLS bloqueia acesso cross-tenant
  ✅ Cookies isolados por subdomínio
  
SEMANA 2 — Onboarding e Auth
─────────────────────────────────────────
Objetivo: validar criação automática de tenant

Tarefas:
  ├── [ ] Landing page simples
  ├── [ ] Form de cadastro com CNPJ
  ├── [ ] Validação de CNPJ (numérico + alfanumérico)
  ├── [ ] Geração automática de slug
  ├── [ ] CreateTenantService
  ├── [ ] Auth com JWT + Refresh Token
  ├── [ ] E-mail de primeiro acesso (Resend)
  ├── [ ] Login funciona em subdomínio gerado
  └── [ ] Logout invalida tokens

Validação ao fim da semana:
  ✅ Cadastro → tenant criado → e-mail enviado
  ✅ Link de e-mail funciona
  ✅ Login no subdomínio próprio
  ✅ Auth estável

SEMANA 3 — Painel Admin (Filament)
─────────────────────────────────────────
Objetivo: validar Filament 3 para casos de uso

Tarefas:
  ├── [ ] Setup Filament 3
  ├── [ ] Multi-panel (Tenant + Super Admin)
  ├── [ ] ImovelResource (CRUD básico)
  ├── [ ] CorretorResource
  ├── [ ] Spatie Permission integrado
  ├── [ ] Upload de fotos (local)
  ├── [ ] Configuração de tenant (logo, cor)
  └── [ ] Permissões por role

Validação ao fim da semana:
  ✅ Filament gera CRUD rapidamente
  ✅ Permissões funcionam corretamente
  ✅ Upload e exibição de fotos OK
  ✅ Curva de aprendizado é gerenciável

SEMANA 4 — Vitrine e Integração Final
─────────────────────────────────────────
Objetivo: vitrine funcional + deploy real

Tarefas:
  ├── [ ] Inertia.js + React + TypeScript setup
  ├── [ ] Listagem de imóveis na vitrine
  ├── [ ] Página individual do imóvel
  ├── [ ] Identidade visual aplicada (logo, cor)
  ├── [ ] Deploy em produção (Hetzner + Forge)
  ├── [ ] Domain real configurado (seuapp.com.br)
  ├── [ ] Wildcard DNS + SSL via Cloudflare
  ├── [ ] Tenant de teste em produção
  └── [ ] Validar fluxo completo em produção

Validação ao fim da semana:
  ✅ Vitrine mostra apenas imóveis do tenant
  ✅ Identidade visual personaliza por tenant
  ✅ Deploy automatizado funciona
  ✅ Pipeline completo de produção validado
```

```
Validação Local com dnsmasq
─────────────────────────────────────────

Por que dnsmasq?
  └── Subdomínios precisam funcionar localmente
  └── Editar /etc/hosts não suporta wildcard
  └── dnsmasq permite *.local → 127.0.0.1

Setup macOS:
  ├── brew install dnsmasq
  ├── echo "address=/.local/127.0.0.1" >> /usr/local/etc/dnsmasq.conf
  ├── sudo brew services start dnsmasq
  └── Configurar resolver em /etc/resolver/local

Setup Linux:
  ├── apt install dnsmasq
  ├── /etc/dnsmasq.d/local.conf:
  │   address=/.local/127.0.0.1
  └── systemctl restart dnsmasq

Resultado:
  ├── primoimoveis.local → 127.0.0.1
  ├── casanova.local → 127.0.0.1
  ├── qualquercoisa.local → 127.0.0.1
  └── Aplicação valida tenant existente

Documentação:
  └── docs/runbooks/local-setup.md
```

```
Validação de Mercado em Paralelo
─────────────────────────────────────────

Em paralelo com semanas técnicas:

Semana 1-2: Identificação
  ├── Listar 10-15 imobiliárias-alvo (porte, região)
  ├── Mapear contatos (LinkedIn, sites, indicações)
  └── Preparar perguntas-chave

Semana 2-3: Conversas Estruturadas (15-20 min cada)
  Perguntas-chave:
    1. Como vocês captam leads hoje?
    2. Qual % de leads convertem em visita?
    3. Quanto tempo demoram para responder um lead?
    4. Como gerenciam visitas hoje?
    5. Quanto pagariam por uma plataforma que faz X?
    6. Quais ferramentas usam atualmente?
    7. Que features seriam essenciais?

Semana 4: Validação com Demo
  ├── Mostrar POC funcional para 3-5 dos entrevistados
  ├── Coletar feedback estruturado
  ├── Identificar early adopters potenciais
  └── Propor descontos para early access

Métricas de Sucesso:
  ├── 3+ imobiliárias dispostas a testar grátis
  ├── 1+ disposta a pagar (mesmo após trial)
  └── Feedback consistente em features críticas

Se métricas não atingidas:
  ├── Reavaliar proposta de valor
  ├── Pivotar segmento ou modelo
  └── Não construir MVP às cegas
```

```
Critérios de Sucesso da POC
─────────────────────────────────────────

Técnicos:
  ✅ Multi-tenancy com RLS funciona
  ✅ Onboarding automatizado completa fluxo
  ✅ Filament resolve admin em < 30% do tempo previsto
  ✅ Deploy automatizado funciona
  ✅ Stack se mostrou produtiva
  ✅ Sem bloqueios técnicos identificados

De Mercado:
  ✅ 3+ imobiliárias topam testar
  ✅ Feedback indica produto resolve dor real
  ✅ Pricing de R$ XX-YY se mostra factível

Se ambos atingidos: prosseguir para MVP completo
Se algum não: revisar decisões antes de prosseguir
```

```
Subir Produção EARLY (Final da POC)
─────────────────────────────────────────

Contraintuitivo mas crítico:

Subir produção AO FINAL da POC, não antes do MVP

Razões:
  ├── Pipeline de deploy validada cedo
  ├── Problemas operacionais descobertos sem pressão
  ├── Quando MVP estiver pronto, deploy é tranquilo
  ├── Cliente pode visitar URL real para feedback
  └── Sem síndrome do "dia do lançamento"

O que vai estar em produção:
  ├── Aplicação Laravel funcional
  ├── Tenant de teste interno
  ├── Possivelmente 1-2 tenants beta de validação
  └── Limited features (POC scope)

O que NÃO vai estar:
  ❌ Pagamento real
  ❌ Compromisso comercial
  ❌ Marketing ativo
  ❌ Aceitação de novos clientes
```

```
Após a POC: Decisão Estratégica
─────────────────────────────────────────

Cenário A: POC bem-sucedida em ambos eixos
  ├── Prosseguir para MVP
  ├── Estimativa: 12-16 semanas adicionais
  ├── Featuras de POC viram base do MVP
  └── Refinamento + features faltantes

Cenário B: POC técnica OK, mercado fraco
  ├── Pausar e revisar produto
  ├── Conversar com mais imobiliárias
  ├── Considerar pivotar (segmento, modelo)
  └── Não construir MVP cego

Cenário C: POC mercado OK, técnica problemas
  ├── Revisar arquitetura
  ├── Considerar mudanças de stack
  ├── Resolver bloqueios antes de prosseguir
  └── Re-validar com nova arquitetura

Cenário D: POC mal-sucedida em ambos
  ├── Avaliar se vale persistir
  ├── Lições aprendidas
  └── Considerar projeto diferente
```

---

## Justificativa

A escolha por POC vertical de 4 semanas se justifica por:

1. **Reduz risco de investimento longo** — 4 semanas vs 6 meses para descobrir problemas
2. **Valida mercado em paralelo** — Não construir produto que ninguém quer
3. **Pipeline de deploy validada cedo** — Reduz risco do "dia do lançamento"
4. **Aprendizado prático com a stack** — Filament, Inertia, RLS antes de escalar
5. **Demo real para validação de mercado** — Mais persuasivo que mockups

A escolha de 4 semanas (não 2, não 8):
- 2 semanas: insuficiente para validação de stack
- 8 semanas: começa a virar MVP parcial sem disciplina
- 4 semanas: força foco e priorização

A escolha de subir produção ao final:
- Catch deployment issues sem stress de "lançamento"
- Cliente potencial pode visitar URL real
- Pipeline validada antes de ser crítica

---

## Alternativas Consideradas

### Alternativa A — Pular POC, Ir Direto para MVP

- **Descrição:** Construir MVP completo em 4-6 meses.
- **Pontos fortes:** Sem "tempo perdido" em POC.
- **Pontos fracos:** Risco enorme se descobrir problema fundamental tarde.
- **Por que não foi escolhida:** Risco desproporcional. POC paga seu custo em redução de risco.

### Alternativa B — POC de 1 Semana

- **Descrição:** Sprint intenso de 1 semana só validação técnica.
- **Pontos fortes:** Tempo mínimo investido.
- **Pontos fracos:** Não cobre validação completa. Sem deploy real. Sem feedback de mercado.
- **Por que não foi escolhida:** Curto demais para validação significativa.

### Alternativa C — POC de 8 Semanas

- **Descrição:** POC mais robusta cobrindo mais features.
- **Pontos fortes:** Mais aprendizado.
- **Pontos fracos:** Vira MVP parcial, perde foco.
- **Por que não foi escolhida:** 4 semanas força disciplina. 8 dilui.

### Alternativa D — POC Apenas Técnica (Sem Validação de Mercado)

- **Descrição:** Focar só em validar arquitetura.
- **Pontos fortes:** Mais simples de executar.
- **Pontos fracos:** Pode validar tecnicamente algo que ninguém quer.
- **Por que não foi escolhida:** Validação de mercado é tão importante quanto técnica.

---

## Consequências

### Positivas

- Risco de MVP reduzido significativamente
- Stack validada com uso real
- Pipeline de deploy testada antes de "ir ao vivo"
- Validação de mercado evita produto sem demanda
- Aprendizado prático antes de escalar
- Demo real para investidores potenciais (futuro)

### Negativas

- 4 semanas "atrasam" o MVP
- Disciplina necessária para não virar mini-MVP
- Validação de mercado requer hands-on (sair da bolha)
- POC pode revelar limitações que exigem refatoração

### Riscos

- **Risco:** POC virar MVP parcial sem disciplina
  - **Mitigação:** Escopo definido em ADR. Time-box rigoroso de 4 semanas. Avaliação ao final.

- **Risco:** Tempo investido se POC falhar
  - **Mitigação:** Falha é informação. Melhor falhar em 4 semanas que em 6 meses. Aprendizado mantém valor.

- **Risco:** Imobiliárias não topar conversar
  - **Mitigação:** Network LinkedIn, indicações, abordar valor antecipado. Aceitar quantidade menor (3+ é ok).

---

## Critérios de Revisão

Esta decisão deve ser revisitada se:

- POC não conseguir cobrir escopo em 4 semanas (avaliar continuidade)
- Validação de mercado revelar dor diferente (pivotar?)
- Stack escolhida se mostrar inadequada
- Surgir oportunidade para acelerar via parcerias

---

## Referências

- ADRs relacionadas: `ADR-001` a `ADR-023` (todas as decisões a validar na POC)
- Lean Startup methodology
- "The Mom Test" (validação de mercado)

---

## Notas de Implementação

- Repositório criado no início da semana 1
- Branch única `main` durante POC (sem feature branches)
- ADRs já criadas servem de norte (não criar novas durante POC)
- Specs leves para cada componente da POC
- Time-box rígido: 4 semanas, não 5
- Demo gravada (vídeo) para validação de mercado
- Lista de imobiliárias-alvo em planilha
- Roteiro de entrevista padronizado
- Feedback estruturado (template) por conversa
- Avaliação formal ao final da semana 4
- Decisão go/no-go documentada em ADR adicional
- Setup local guide:
  - `docs/runbooks/local-setup.md`
  - Inclui dnsmasq config
  - Inclui Docker setup
  - Inclui database seed
- Tenants de POC:
  - primoimoveis.local (tenant exemplo 1)
  - casanova.local (tenant exemplo 2)
  - admin.local (super admin)
- Após POC, criar ADR de "Lições Aprendidas" se decidir prosseguir
