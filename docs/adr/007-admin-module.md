# ADR-007: Módulo Admin do Tenant

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

O painel administrativo é a principal ferramenta de trabalho do cliente pagante (imobiliária). É onde os usuários do tenant gerenciam todo o conteúdo e operação da plataforma.

A qualidade do admin impacta diretamente:

- Tempo de produtividade (quão rápido o admin cria um imóvel)
- Adoção pela equipe da imobiliária
- Percepção de valor da plataforma
- Tempo de onboarding de novos corretores
- Retenção do cliente pagante

Decidir o escopo do admin no MVP exige:

- Cobrir operações essenciais (CRUD de imóveis, gestão de equipe)
- Manter simplicidade de interface
- Permitir customização mínima por tenant
- Evitar features que não geram valor imediato

A escolha tecnológica do Filament 3 (definida na ADR-019) acelera muito o desenvolvimento desta camada.

---

## Decisão

O painel administrativo do tenant será construído em Filament 3 e incluirá as seguintes funcionalidades:

### Detalhamento

```
Funcionalidades Incluídas no MVP
─────────────────────────────────────────

1. Gestão de Imóveis
   ├── Listagem com filtros e busca
   ├── Criar, editar, pausar, excluir
   ├── Upload de até 10 fotos por imóvel
   ├── Ordenação de fotos (drag-and-drop)
   ├── Status: disponível, reservado, vendido
   ├── Atribuir corretor responsável
   └── Pausar imóvel (ocultar da vitrine)

2. Gestão de Corretores
   ├── Listar corretores ativos/inativos
   ├── Convidar novo corretor (e-mail com token)
   ├── Ativar / desativar acesso
   ├── Visualizar imóveis de cada corretor
   └── Alterar dados básicos (admin pode)

3. Gestão de Agendamentos
   ├── Listar todos os agendamentos do tenant
   ├── Filtrar por status, corretor, data
   ├── Visualizar detalhes do cliente
   ├── Confirmar / cancelar / reagendar
   └── Histórico de visitas por imóvel

4. Configuração do Tenant
   ├── Logo da imobiliária
   ├── Cor primária (color picker)
   ├── Dados cadastrais (CNPJ, endereço, telefone)
   ├── E-mail de contato público
   ├── WhatsApp principal
   ├── Cadastro de bairros (lista própria)
   └── Configuração de horários padrão

5. Configuração Pessoal (Corretor)
   ├── Foto e dados de perfil
   ├── Horários de disponibilidade para visitas
   ├── Telefone (WhatsApp)
   └── Mudança de senha
```

```
Funcionalidades FORA do MVP (v2+)
─────────────────────────────────────────

❌ Relatórios e dashboards avançados
   └── Apenas contagem básica no dashboard inicial

❌ Gestão financeira
   └── Comissões, fechamentos, repasses

❌ Integração com portais externos
   └── ZAP, OLX, VivaReal

❌ Automações de follow-up
   └── Régua de comunicação automática

❌ Importação em massa de imóveis
   └── XML/CSV de portais

❌ Personalização avançada de tema
   └── Apenas logo e cor primária no MVP

❌ API pública para imobiliária integrar
   └── Apenas painel web no MVP

❌ App mobile nativo
   └── Web responsivo serve no MVP
```

### Estrutura do Filament

```
Filament Resources (telas auto-geradas)
─────────────────────────────────────────

ImovelResource
  └── ListImoveis, CreateImovel, EditImovel
  └── Tabela com colunas, filtros, busca
  └── Form com upload, validação, relacionamentos

CorretorResource (User com role corretor)
  └── ListCorretores, InviteCorretor, EditCorretor
  └── Action: ativar/desativar
  └── Action: ver imóveis do corretor

AgendamentoResource
  └── ListAgendamentos, ViewAgendamento
  └── Actions: confirmar, cancelar, reagendar
  └── Filtros por data, status, corretor

BairroResource (config do tenant)
  └── ListBairros, CreateBairro, EditBairro
  └── Tabela simples
```

```
Páginas Customizadas
─────────────────────────────────────────

Dashboard (página inicial do admin)
  ├── Widget: Imóveis ativos
  ├── Widget: Visitas agendadas (próximos 7 dias)
  ├── Widget: Novos leads (mês)
  └── Widget: Status do trial/plano

Configurações do Tenant (página única)
  ├── Aba: Identidade visual (logo, cor)
  ├── Aba: Dados da imobiliária
  ├── Aba: Bairros
  └── Aba: Plano e cobrança

Perfil do Usuário
  ├── Dados pessoais
  ├── Foto
  ├── Horários (apenas corretor)
  └── Mudança de senha
```

### Permissões por Perfil

```
Admin do tenant:
  ✅ Acesso total a todos os Resources
  ✅ Gerencia configurações do tenant
  ✅ Vê dados de qualquer corretor
  ✅ Confirma/cancela agendamentos de qualquer corretor

Corretor:
  ✅ Vê todos os imóveis do tenant (visibilidade)
  ✅ Edita apenas imóveis sob sua responsabilidade
  ✅ Vê e gerencia seus próprios agendamentos
  ❌ Não acessa configurações do tenant
  ❌ Não convida outros corretores
```

---

## Justificativa

A escolha do escopo se justifica por:

1. **Foco em operações essenciais** — CRUD de imóveis + agendamentos cobre o ciclo crítico
2. **Filament acelera entrega** — 80% do código gerado, foco apenas em customizações
3. **Configuração mínima viável** — Logo + cor já personaliza o suficiente
4. **Permissões claras** — Admin x Corretor é simples de entender e implementar
5. **Funcionalidades v2 não bloqueiam validação** — Imobiliária consegue operar sem relatórios avançados

A escolha consciente de NÃO incluir relatórios avançados, integrações com portais e automações vem de:
- Cada feature dessas exige 1-2 semanas de dev
- Sem clientes pagantes, não há feedback para guiar prioridade
- Tempo é o recurso mais escasso para solo founder

---

## Alternativas Consideradas

### Alternativa A — Admin Construído Manualmente (sem Filament)

- **Descrição:** Construir todo o painel em Inertia + React.
- **Pontos fortes:** Stack unificada com a vitrine, controle total.
- **Pontos fracos:** 6-8 semanas adicionais de desenvolvimento.
- **Por que não foi escolhida:** Tempo de entrega é crítico. Filament resolve com excelência.

### Alternativa B — Incluir Importação de XML de Portais

- **Descrição:** Permitir importar imóveis em massa de ZAP/OLX.
- **Pontos fortes:** Reduz fricção de migração para imobiliárias estabelecidas.
- **Pontos fracos:** Cada portal tem schema diferente, mapeamento complexo.
- **Por que não foi escolhida:** Adiciona 2-3 semanas. Pode ser feito manual no início.

### Alternativa C — Dashboard Avançado com Métricas

- **Descrição:** Dashboard rico com gráficos, conversão, funil completo.
- **Pontos fortes:** Diferencial competitivo, demonstra valor.
- **Pontos fracos:** Sem dados reais (poucos clientes), métricas pouco úteis no MVP.
- **Por que não foi escolhida:** Volume de dados não justifica. Widgets simples bastam.

---

## Consequências

### Positivas

- Painel admin construído rapidamente (Filament reduz código em 70%+)
- UI consistente e profissional sem necessidade de designer
- Multi-tenancy nativo do Filament cobre necessidades
- Permissões integradas com Spatie Permission
- Fácil adicionar novos Resources conforme cresce

### Negativas

- Visual do admin "padronizado Filament" (limitado para personalização extrema)
- Customizações avançadas exigem aprender Livewire (não React)
- Stack diferente do front (Filament usa Livewire, vitrine usa React)
- Algumas features complexas podem não caber bem no padrão Filament

### Riscos

- **Risco:** Cliente solicitar customização visual do admin que Filament limita
  - **Mitigação:** Comunicar claramente que admin tem visual padrão. Customização é só para vitrine pública.

- **Risco:** Curva de aprendizado de Filament atrasar primeiras semanas
  - **Mitigação:** Reservar 1-2 semanas para aprendizado. Documentação do Filament é excelente.

- **Risco:** Permissões complexas exigirem código fora do padrão Filament
  - **Mitigação:** Spatie Permission integra naturalmente. Policies do Laravel cobrem casos avançados.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- 3+ clientes pagantes solicitarem relatórios avançados
- Imobiliárias migrarem de portais (XML import vira prioridade)
- Filament limitar funcionalidade necessária impossivelmente
- Dashboard precisar de gráficos e métricas reais (volume justifica)

---

## Referências

- ADRs relacionadas: `ADR-003` (User Profiles), `ADR-019` (Tech Stack), `ADR-014` (Authentication)
- Documentação Filament: https://filamentphp.com/docs/3.x

---

## Notas de Implementação

- Filament Resources em `app/Filament/Tenant/Resources/`
- Painel acessível em `/admin` dentro do subdomínio do tenant
- Middleware verifica role (admin ou corretor) antes de acessar
- Cliente final NUNCA acessa o painel (verificação no middleware)
- Configurar Filament com tema customizado para aplicar cor primária do tenant
- Upload de fotos via Spatie Media Library + abstração de storage (ADR-015)
- Form de imóvel com validação inline e preview de imagens
- Tabelas com paginação (25 itens por página default)
- Dashboard com widgets simples (sem charts complexos no MVP)
