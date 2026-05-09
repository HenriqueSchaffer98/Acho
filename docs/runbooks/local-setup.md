# Runbook: Setup do Ambiente Local

## Pré-requisitos

- Docker Desktop instalado e rodando
- Node.js 20+
- PHP 8.3+ com extensões: `pdo_pgsql`, `redis`, `pcntl`
- Composer 2.x

---

## 1. Clone e dependências

```bash
git clone <repo-url>
cd Acho

composer install
npm install
cp .env.example .env
php artisan key:generate
```

---

## 2. DNS Wildcard local (`*.acho.test`)

O projeto usa subdomínios dinâmicos (`tenant1.acho.test`, `teste-interno.acho.test`).
`/etc/hosts` não suporta wildcard — use `dnsmasq`.

### macOS (Homebrew)

```bash
# Instalar dnsmasq
brew install dnsmasq

# Redirecionar *.acho.test para localhost
echo "address=/.acho.test/127.0.0.1" >> $(brew --prefix)/etc/dnsmasq.conf

# Iniciar e habilitar no boot
sudo brew services start dnsmasq

# Criar resolver para o TLD .test
sudo mkdir -p /etc/resolver
echo "nameserver 127.0.0.1" | sudo tee /etc/resolver/test

# Verificar
ping -c 1 qualquer-coisa.acho.test    # deve resolver para 127.0.0.1
ping -c 1 outro-tenant.acho.test      # deve resolver para 127.0.0.1
```

### Linux (systemd-resolved)

```bash
# Instalar dnsmasq
sudo apt install dnsmasq   # ou equivalente

# Adicionar ao /etc/dnsmasq.conf
echo "address=/.acho.test/127.0.0.1" | sudo tee -a /etc/dnsmasq.conf

sudo systemctl restart dnsmasq
```

### Alternativa sem dnsmasq (limitada)

Edite `/etc/hosts` manualmente para cada tenant que precisar testar:

```
127.0.0.1  acho.test
127.0.0.1  www.acho.test
127.0.0.1  teste-interno.acho.test
127.0.0.1  exemplo.acho.test
```

Limitação: não resolve slugs novos automaticamente.

---

## 3. Subir o ambiente Docker

```bash
make up
```

O `docker compose up` sobe:
- **PostgreSQL 16** na porta 5432 — com roles `acho_app` e `acho_migrator` criadas automaticamente pelo script `docker/postgres/20-create-roles.sh`
- **Redis** na porta 6379
- **Mailpit** nas portas 1025 (SMTP) e 8025 (dashboard)
- **Laravel Sail** na porta 80

### Verificar roles Postgres

```bash
./vendor/bin/sail exec pgsql psql -U sail -d acho -c "\du"
```

Saída esperada (trecho):
```
 acho_app      | Cannot login            |
 acho_migrator | Bypass RLS             |
 sail          | Superuser, ...          |
```

---

## 4. Migrations e seeds

```bash
make fresh
# equivalente a: sail artisan migrate:fresh --seed
```

Cria:
- Todas as tabelas
- Tenant `teste-interno` (status `active`, acessível em `http://teste-interno.acho.test`)

---

## 5. Verificar setup

```bash
# Acho.test raiz deve responder
curl http://acho.test
# → "Acho — plataforma em construção"

# Tenant seedado deve responder
curl http://teste-interno.acho.test
# → "Tenant teste-interno resolvido"

# Tenant inexistente deve retornar 404
curl -o /dev/null -s -w "%{http_code}" http://nao-existe.acho.test
# → 404
```

---

## 6. Rodar testes

```bash
make test
```

---

## 7. Lint e análise estática

```bash
make lint
```

Roda:
- `pint` — formatação PHP
- `phpstan` — análise estática nível 8
- `eslint` — lint TypeScript
- `prettier --check` — formatação TypeScript

---

## Roles Postgres — referência

| Role | BYPASSRLS | Uso |
|------|-----------|-----|
| `sail` | sim (superuser) | apenas init/admin |
| `acho_migrator` | sim | migrations, seeds, lookup de tenant |
| `acho_app` | não | todas as requests HTTP |

**Por que separar?**

A role `acho_app` não tem `BYPASSRLS`, o que significa que o RLS do Postgres é aplicado a ela automaticamente. Mesmo que um bug na aplicação tente fazer uma query cross-tenant, o banco bloqueia. A `acho_migrator` tem `BYPASSRLS` e é usada apenas onde é necessário acesso irrestrito: migrations, seeds e o lookup inicial de tenant no `TenantService` (antes de `app.tenant_id` ser setado).

Ver: `ADR-001`, `config/database.php` (conexão `pgsql_migrator`), `docker/postgres/20-create-roles.sh`.

---

## Troubleshooting

### Roles não foram criadas

O script de init só roda quando o volume do Postgres é criado pela primeira vez.
Para recriar do zero:

```bash
make down
docker volume rm acho_sail-pgsql   # ou o nome do volume correspondente
make up
```

### `pg_isready` falha no healthcheck

Verifique se `DB_SUPERUSER` está definido no `.env`. O healthcheck usa essa role para checar se o Postgres está pronto.

### DNS não resolve `*.acho.test`

```bash
# Verificar se dnsmasq está rodando
sudo brew services list | grep dnsmasq

# Forçar flush de DNS no macOS
sudo dscacheutil -flushcache && sudo killall -HUP mDNSResponder

# Verificar resolver
scutil --dns | grep -A5 "domain : test"
```
