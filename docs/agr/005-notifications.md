# ADR-005: Estratégia de Notificações

## Status

`Aceita`

**Data:** 2026-04-25
**Autor:** Equipe Fundadora

---

## Contexto

O sistema precisa enviar notificações em diversos pontos da jornada do usuário:

- Confirmação de cadastro de imobiliária
- Boas-vindas com link de primeiro acesso
- Confirmação de agendamento de visita ao cliente
- Notificação ao corretor sobre novo agendamento
- Recuperação de senha
- Lembretes de fim de trial
- Notificações de cobrança
- Convite de corretor

Existem diferentes canais possíveis (e-mail, SMS, WhatsApp, push) com custos e complexidades distintos. Para um MVP de bootstrap solo founder, é necessário definir uma estratégia que:

- Atenda os casos críticos sem custo proibitivo
- Mantenha simplicidade de implementação
- Permita evolução sem refatoração

A pesquisa de mercado sobre provedores de e-mail transacional revelou opções como AWS SES (mais barato, mais complexo), Resend (moderno, free tier generoso), Brevo, MailerSend e Mailchimp (caro, focado em marketing).

---

## Decisão

Adotar estratégia mista de notificações:

1. **E-mail transacional via Resend** — para todas as notificações automáticas
2. **WhatsApp via link `wa.me/`** — para comunicação direta com corretor (sem API)
3. **WhatsApp Business API** — postergado para v2

### Detalhamento

```
Canal: E-mail Transacional
─────────────────────────────────────────
Provedor:       Resend
Free tier:      3.000 e-mails/mês (suficiente para MVP)
Tipo:           Transacional (1:1, não marketing)
Templates:      React Email (componentizados)
Domínio:        seuapp.com.br (verificado via DKIM/SPF)

Casos de uso no MVP:
  ├── Boas-vindas pós-cadastro de imobiliária
  ├── Token de primeiro acesso
  ├── Convite de corretor
  ├── Confirmação de cadastro de cliente
  ├── Confirmação de agendamento (cliente)
  ├── Notificação de novo agendamento (corretor)
  ├── Recuperação de senha
  └── Avisos de fim de trial (dia 7 e 13)
```

```
Canal: WhatsApp (Link Direto)
─────────────────────────────────────────
Estratégia:     Link wa.me/ na interface
Custo:          $0
Limitação:      Não é notificação automática

Casos de uso no MVP:
  ├── Botão "Falar com corretor" na página do imóvel
  └── Botão de contato no perfil do corretor

Comportamento:
  └── Cliente clica → abre WhatsApp do corretor
  └── Mensagem pré-preenchida com contexto do imóvel
```

```
Canal: WhatsApp Business API (v2)
─────────────────────────────────────────
Provedores:     Twilio, Zenvia, Z-API
Custo:          ~$0,05–0,08 por mensagem
Complexidade:   Aprovação de templates pela Meta

Postergado porque:
  ├── Custo variável adicional ao MVP
  ├── Aprovação de templates leva semanas
  ├── Não é crítico para validar o produto
  └── Link wa.me/ resolve o caso principal
```

### Arquitetura de Notificações

```
Aplicação Laravel
       │
       ▼
Notification (Laravel Notification)
       │
       ├── via('mail')      → Resend (driver SMTP)
       └── via('database')  → registro interno
       
Cada notificação:
  ├── Pode ter múltiplos canais
  ├── É enviada em queue (não bloqueia request)
  ├── É registrada no banco para histórico
  └── Tem template versionado em React Email
```

---

## Justificativa

A escolha por Resend + Link WhatsApp se justifica por:

1. **Custo zero no MVP** — Free tier de 3.000 e-mails/mês cobre 0–10 imobiliárias ativas
2. **Simplicidade de integração** — Resend tem API moderna e SDK Laravel maduro
3. **Templates profissionais** — React Email permite criar templates HTML responsivos sem dor
4. **WhatsApp resolvido sem custo** — Link `wa.me/` cobre 80% do uso esperado
5. **Evolução natural** — Migrar para Twilio na v2 é trivial (mesma camada de Notification)

A escolha do Resend sobre alternativas:
- **Vs AWS SES:** Resend é mais simples e tem dashboard decente
- **Vs Mailchimp:** Mailchimp é orientado a marketing, caro para transacional
- **Vs Brevo:** Resend tem API mais limpa e React Email integrado
- **Vs Postmark:** Free tier do Postmark é muito limitado (100/mês)

---

## Alternativas Consideradas

### Alternativa A — AWS SES como Único Provedor

- **Descrição:** AWS SES é o mais barato em escala ($0,10/1.000 e-mails).
- **Pontos fortes:** Custo mínimo absoluto, infraestrutura robusta.
- **Pontos fracos:** Dashboard ruim, configuração de DKIM manual, sem React Email pronto.
- **Por que não foi escolhida:** Resend é mais produtivo no MVP. Migração futura é viável.

### Alternativa B — WhatsApp Business API no MVP

- **Descrição:** Notificações automáticas via WhatsApp.
- **Pontos fortes:** Engajamento maior que e-mail (taxa de abertura ~90%).
- **Pontos fracos:** Custo por mensagem, aprovação de templates demora, complexidade.
- **Por que não foi escolhida:** Postergada para v2 quando justificar investimento.

### Alternativa C — SMS via Twilio

- **Descrição:** Notificações por SMS para casos críticos.
- **Pontos fortes:** Confiabilidade, visibilidade alta.
- **Pontos fracos:** Custo (~R$ 0,15/SMS), invasivo, sem free tier.
- **Por que não foi escolhida:** E-mail cobre os casos críticos sem custo. SMS pode entrar na v2.

### Alternativa D — Notificações Push (Web Push)

- **Descrição:** Push notifications via Service Worker.
- **Pontos fortes:** Engajamento alto, custo zero.
- **Pontos fracos:** Requer permissão do usuário, complexidade de implementação.
- **Por que não foi escolhida:** Não é prioridade no MVP. Pode entrar como diferencial na v2.

---

## Consequências

### Positivas

- Custo zero no MVP (free tier do Resend)
- Templates de e-mail profissionais via React Email
- Simplicidade de implementação
- WhatsApp resolvido sem dependência externa
- Camada de Notification do Laravel facilita evolução

### Negativas

- Notificações automáticas limitadas a e-mail
- Cliente que não verifica e-mail pode perder confirmação
- Sem feedback de leitura/recebimento (e-mail é "fire and forget")
- WhatsApp depende de ação manual do usuário

### Riscos

- **Risco:** E-mail cair em spam afetando confirmações
  - **Mitigação:** Configurar DKIM, SPF e DMARC corretamente. Usar domínio dedicado. Monitorar deliverability via dashboard do Resend.

- **Risco:** Free tier do Resend acabar em pico de uso
  - **Mitigação:** Monitorar consumo. Upgrade para plano pago é trivial ($20/mês, 50k e-mails).

- **Risco:** Cliente esperar notificação por WhatsApp e não receber
  - **Mitigação:** Comunicar claramente que confirmações vão por e-mail. Considerar v2 com WhatsApp API.

---

## Critérios de Revisão

Esta decisão deve ser revisitada quando:

- Volume de e-mails ultrapassar free tier do Resend (>3.000/mês)
- 3+ clientes solicitarem WhatsApp automático como feature crítica
- Taxa de no-show em visitas crescer significativamente (lembretes)
- Concorrência usar WhatsApp automático como diferencial relevante

---

## Referências

- ADRs relacionadas: `ADR-008` (Scheduling Module), `ADR-009` (Auth Module)
- Provedor: https://resend.com
- Documentação React Email: https://react.email
- Comparação de provedores: documentação interna

---

## Notas de Implementação

- Configurar domínio `seuapp.com.br` no Resend com DKIM, SPF, DMARC
- Criar templates em `resources/views/emails/` ou via React Email
- Toda notificação deve ser registrada na tabela `notifications` para histórico
- Notificações enviadas via Queue (não bloqueia request)
- Em ambiente local, usar MailHog (capturar e-mails sem enviar)
- Domínios em `.env`:
  - `MAIL_MAILER=resend`
  - `RESEND_API_KEY=...`
  - `MAIL_FROM_ADDRESS=noreply@seuapp.com.br`
- Link WhatsApp segue padrão: `https://wa.me/55{telefone}?text={mensagem-encoded}`
