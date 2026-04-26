# ADR-011: Onboarding Automatizado de Imobiliárias

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

Como SaaS de auto-atendimento, o processo de onboarding de novas imobiliárias precisa ser totalmente automatizado. Sem automação, cada novo cliente exigiria intervenção manual da equipe (criar tenant, gerar subdomínio, enviar credenciais), inviabilizando escala.

O onboarding precisa cobrir:

- Cadastro self-service via landing page
- Validação de empresa (CNPJ válido)
- Provisionamento automático de subdomínio
- Criação de usuário admin do tenant
- Acesso imediato após cadastro
- Período de trial sem cartão de crédito

A pesquisa sobre o novo formato de CNPJ revelou ponto técnico importante:

> A partir de 6 de julho de 2026, a Receita Federal permite CNPJs alfanuméricos (formato `AB12C3D4/0E9F-45`). O sistema precisa suportar ambos os formatos desde já, pois clientes existentes mantêm CNPJs numéricos.

Decidir o fluxo de onboarding exige equilibrar:

- **Velocidade** (UX rápida, baixa fricção)
- **Validação** (evitar cadastros fraudulentos)
- **Conversão** (não exigir cartão upfront)
- **Suporte** (lidar com edge cases)

---

## Decisão

Implementar onboarding totalmente automatizado com cadastro self-service na landing page, provisionamento instantâneo de subdomínio e trial gratuito de 14 dias sem cartão de crédito.

### Detalhamento

```
Fluxo Completo de Onboarding
─────────────────────────────────────────

1. Landing Page (seuapp.com.br)
   └── CTA principal: "Comece grátis por 14 dias"
       │
       ▼
2. Formulário de Cadastro
   ├── Razão social
   ├── CNPJ (validação em tempo real)
   ├── Nome do responsável
   ├── E-mail (será o login)
   ├── Senha (validação de força)
   ├── Subdomínio (sugerido automaticamente)
   ├── Telefone WhatsApp
   ├── Aceite dos Termos de Uso
   └── Aceite da Política de Privacidade
       │
       ▼
3. Validações Backend
   ├── CNPJ válido (módulo 11, numérico ou alfanumérico)
   ├── CNPJ único na plataforma
   ├── E-mail válido e único na plataforma
   ├── Senha forte
   ├── Subdomínio disponível
   └── Subdomínio não-reservado
       │
       ▼
4. Criação Atômica (Transaction)
   ├── Cria tenant no banco
   ├── Cria usuário admin vinculado
   ├── Define plano "trial" (14 dias)
   ├── Gera token de primeiro acesso (JWT, 15min)
   ├── Configurações default do tenant
   └── Eventos: TenantCreated, UserRegistered
       │
       ▼
5. Resposta ao Usuário
   ├── Tela: "Verifique seu e-mail"
   ├── E-mail enviado com link de primeiro acesso:
   │   primoimoveis.seuapp.com.br/auth?token=xyz
   └── Link expira em 15min
       │
       ▼
6. Primeiro Acesso (subdomínio do tenant)
   ├── Usuário clica no link do e-mail
   ├── Sistema valida token
   ├── Cria sessão no subdomínio
   ├── Redireciona para onboarding guiado
   └── Login automático como Admin do tenant
```

### Validação de CNPJ

```
Suporte a Ambos os Formatos:

Numérico (atual): 12.345.678/0001-90
  ├── 14 dígitos
  ├── Validação clássica módulo 11
  └── Maioria dos cadastros até 2026

Alfanumérico (julho/2026): AB12C3D4/0E9F-45
  ├── 14 caracteres
  ├── 8 primeiros: alfanuméricos
  ├── 4 seguintes: numéricos
  ├── 2 últimos: dígitos verificadores
  └── Validação módulo 11 com conversão ASCII

Implementação:
  ├── Campo no banco: VARCHAR(18)
  │   (suporta máscara completa)
  ├── Validação aceita ambos os formatos
  ├── Normalização: armazenar sem máscara
  └── Suíte de testes com CNPJs válidos
      do simulador da Receita Federal
```

### Sugestão de Subdomínio

```
Geração Automática:

Razão social: "Primo Imóveis Ltda"
Sistema sugere: "primoimoveis"

Algoritmo:
  1. Remove acentos
  2. Converte para minúsculas
  3. Remove "ltda", "me", "eireli", "sa"
  4. Remove caracteres não-alfanuméricos
  5. Limita a 30 caracteres

Validação em tempo real:
  ├── Debounce de 500ms
  ├── Disponível? indicação visual
  ├── Em uso? sugere alternativas:
  │   primoimoveis2, primoimoveis-rj, etc.
  └── Reservado? mensagem clara

Lista de Slugs Reservados:
  ├── Operacional: admin, www, api, app, mail, ftp
  ├── Marca: seuapp, noreply, contato
  ├── Técnico: dev, staging, test, preview, cdn
  └── Marketing: blog, help, support, docs
```

### Trial Automático

```
Período de Trial:
  ├── Duração: 14 dias
  ├── Sem cartão de crédito necessário
  ├── Acesso completo ao produto
  └── Sem limites de uso durante o trial

Gatilhos de Comunicação:
  ├── Dia 0: e-mail de boas-vindas
  ├── Dia 7: lembrete (meio do trial)
  └── Dia 13: alerta (fim próximo, escolha plano)

Fim do Trial (sem upgrade):
  └── Vai para plano limitado (ADR-012)
  └── Não é "suspensão" — é downgrade
  └── Mantém acesso, mas com limites
```

### Onboarding Guiado

```
Após primeiro login, exibir checklist:

[ ] Adicionar logo da imobiliária
[ ] Definir cor primária da identidade visual
[ ] Cadastrar primeiro imóvel
[ ] Convidar primeiro corretor
[ ] Configurar horários padrão de visita
[ ] Cadastrar bairros que atende

Características:
  ├── Visível no dashboard
  ├── Progresso percentual
  ├── Pode dispensar (mas reaparece)
  └── Reduz drop-off precoce
```

---

## Justificativa

A automação do onboarding se justifica por:

1. **Escalabilidade desde o dia 1** — Não exige equipe para cadastrar cada cliente
2. **Velocidade de adoção** — Imobiliária tem produto rodando em minutos
3. **Sem fricção financeira** — Trial sem cartão remove barreira de entrada
4. **Validação prévia** — CNPJ obrigatório evita cadastros frívolos
5. **Onboarding guiado reduz churn** — Cliente que configura completa fica

A escolha de não exigir cartão no trial se justifica:
- Setor imobiliário tem ciclo longo de decisão
- Cartão upfront cria fricção desnecessária no MVP
- Conversão pode ser otimizada via demonstração de valor durante 14 dias

---

## Alternativas Consideradas

### Alternativa A — Onboarding Manual (Equipe Cadastra)

- **Descrição:** Cliente preenche formulário, equipe processa em até 24h.
- **Pontos fortes:** Controle total sobre quem entra na plataforma.
- **Pontos fracos:** Não escala. Cliente perde momentum.
- **Por que não foi escolhida:** Inviável para SaaS bootstrap. Vai contra a proposta de valor.

### Alternativa B — Trial com Cartão Obrigatório

- **Descrição:** Exigir cartão no cadastro, cobrando após 14 dias.
- **Pontos fortes:** Maior taxa de conversão pós-trial (compromisso já dado).
- **Pontos fracos:** Fricção alta no cadastro inicial. Reduz conversão de cadastro.
- **Por que não foi escolhida:** Fricção no topo do funil é mais cara que perda na conversão pós-trial. Pode ser testado depois.

### Alternativa C — Período de Trial Longo (30 dias)

- **Descrição:** 30 dias de trial em vez de 14.
- **Pontos fortes:** Mais tempo para experimentar.
- **Pontos fracos:** Aumenta tempo até receita. Reduz urgência de decisão.
- **Por que não foi escolhida:** 14 dias é o padrão do mercado e tempo suficiente para avaliar.

### Alternativa D — Validação de CNPJ na Receita Federal

- **Descrição:** Consultar API da Receita para verificar situação cadastral.
- **Pontos fortes:** Bloqueia CNPJs irregulares.
- **Pontos fracos:** APIs disponíveis são instáveis ou pagas.
- **Por que não foi escolhida (no MVP):** Validação módulo 11 é suficiente. Pode ser adicionada na v2.

---

## Consequências

### Positivas

- Onboarding totalmente automatizado e escalável
- Cliente em produção em minutos
- Trial sem cartão maximiza conversão de topo
- Suporte a CNPJ futuro (alfanumérico) garantido
- Onboarding guiado reduz churn precoce

### Negativas

- Risco de cadastros fraudulentos (CNPJ válido mas empresa fictícia)
- Sem validação de empresa real até pagamento
- Cliente pode esquecer de upgrade após trial
- Desperdício de recursos em cadastros que nunca convertem

### Riscos

- **Risco:** Atacante automatizar criação de tenants
  - **Mitigação:** Rate limiting (3 cadastros/IP/hora), reCAPTCHA invisível, validação de e-mail.

- **Risco:** Cliente perder e-mail de primeiro acesso
  - **Mitigação:** Botão "Reenviar link" na tela de "verifique seu e-mail". Token novo gerado.

- **Risco:** Subdomínio escolhido conflitar com marca
  - **Mitigação:** Lista de reservados protege casos óbvios. Termos permitem revisão posterior se necessário.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Volume de cadastros fraudulentos justificar verificação na Receita
- Taxa de conversão pós-trial for baixa (testar com cartão obrigatório)
- 3+ clientes solicitarem trial mais longo ou mais curto
- Surgir necessidade de aprovação manual (compliance, segurança)

---

## Referências

- ADRs relacionadas: `ADR-002` (Tenancy Model), `ADR-012` (Trial and Plans), `ADR-014` (Authentication)
- Receita Federal — Simulador de CNPJ Alfanumérico
- Pacote Laravel: stancl/tenancy

---

## Notas de Implementação

- Service `CreateTenantService` orquestra criação atômica:
  - Cria tenant
  - Cria admin
  - Define trial
  - Gera token
  - Dispara eventos
- Validação de CNPJ em `app/Rules/ValidCnpj.php`:
  - Aceita formato com ou sem máscara
  - Suporta numérico e alfanumérico
  - Suite de testes com casos válidos e inválidos
- Tabela `tenants` deve incluir:
  - `cnpj` (varchar 18)
  - `slug` (varchar 30, unique, lowercase)
  - `trial_ends_at` (timestamp)
  - `plan_id` (referência ao plano atual)
- Endpoint público: `POST /api/cadastro` (rate limited)
- Endpoint público: `GET /api/subdomain/check?slug=xxx` (validação tempo real)
- Token de primeiro acesso: JWT assinado, claim `purpose: 'first_access'`
- E-mail de boas-vindas: template em React Email, com:
  - Saudação personalizada
  - Link de primeiro acesso (botão grande)
  - Próximos passos sugeridos
  - Contato de suporte
