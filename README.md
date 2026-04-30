# Acho

SaaS multi-tenant white-label para imobiliárias brasileiras.

## Stack

- **Backend:** Laravel 13 + PHP 8.3
- **Frontend:** Inertia.js + React 18 + TypeScript
- **Admin:** Filament 3
- **Banco:** PostgreSQL 16
- **Cache/Queue:** Redis
- **Ambiente local:** Docker Compose (Laravel Sail)

---

## Pré-requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop) (macOS)
- [Node.js 22+](https://nodejs.org) — apenas para Husky (hooks de pre-commit)
- Git

Não é necessário PHP, Composer ou qualquer outra ferramenta instalada globalmente.

---

## Setup inicial

### 1. Clonar e configurar ambiente

```bash
git clone <repo-url> acho
cd acho

cp .env.example .env
```

### 2. Instalar dependências Node (necessário para Husky)

```bash
npm install
```

### 3. Subir os containers

Na primeira execução, o Docker vai baixar e construir as imagens — isso pode levar alguns minutos.
Nas execuções seguintes, o ambiente sobe em menos de 30 segundos.

```bash
make up
```

### 4. Instalar dependências PHP e gerar chave

```bash
./vendor/bin/sail composer install
./vendor/bin/sail artisan key:generate
```

### 5. Rodar as migrations

```bash
./vendor/bin/sail artisan migrate
```

### 6. Verificar que tudo está funcionando

```bash
make test   # deve passar com os testes dummy
```

---

## Configuração de DNS local (dnsmasq)

Para que `*.acho.local` resolva para `127.0.0.1` sem precisar editar `/etc/hosts` a cada novo subdomínio.

### 1. Instalar dnsmasq

```bash
brew install dnsmasq
```

### 2. Criar arquivo de configuração

```bash
mkdir -p /etc/dnsmasq.d
echo "address=/.acho.local/127.0.0.1" | sudo tee /etc/dnsmasq.d/acho.conf
```

### 3. Adicionar o arquivo ao dnsmasq principal

```bash
echo "conf-dir=/etc/dnsmasq.d/,*.conf" | sudo tee -a /opt/homebrew/etc/dnsmasq.conf
```

### 4. Iniciar e habilitar o serviço

```bash
sudo brew services start dnsmasq
```

### 5. Configurar macOS para usar dnsmasq como resolver

```bash
sudo mkdir -p /etc/resolver
echo "nameserver 127.0.0.1" | sudo tee /etc/resolver/acho.local
```

### 6. Testar

```bash
ping -c 1 acho.local
ping -c 1 qualquer-coisa.acho.local
# ambos devem responder de 127.0.0.1
```

> Se não funcionar de imediato, limpe o cache DNS do macOS:
> ```bash
> sudo dscacheutil -flushcache && sudo killall -HUP mDNSResponder
> ```

---

## Comandos do dia a dia

```bash
make up       # sobe os containers
make down     # para os containers
make test     # roda a suíte de testes (Pest)
make lint     # Pint + Larastan + ESLint + Prettier
make analyze  # apenas análise estática Larastan nível 8
make fresh    # reset completo do banco + seeders
```

### Comandos Sail diretos

```bash
./vendor/bin/sail artisan <comando>   # qualquer comando artisan
./vendor/bin/sail composer <comando>  # composer dentro do container
./vendor/bin/sail tinker              # REPL PHP interativo
./vendor/bin/sail bash                # shell dentro do container
```

---

## Serviços e portas

| Serviço    | URL / Porta                          |
|------------|--------------------------------------|
| App        | http://acho.local                    |
| Mailpit    | http://localhost:8025                |
| PostgreSQL | localhost:5432                       |
| Redis      | localhost:6379                       |

---

## Estrutura do projeto

```
app/
├── Http/Controllers/     # Controllers (thin — orquestram, não implementam)
├── Http/Requests/        # Form Requests (validação + autorização)
├── Services/             # Regra de negócio
├── Models/               # Eloquent Models (estendem BaseTenantModel)
├── Events/ & Listeners/  # Eventos de domínio
├── Jobs/                 # Queue jobs
├── Policies/             # Autorização
├── Data/                 # DTOs (Spatie Laravel Data)
└── Filament/             # Painéis admin (Tenant + SuperAdmin)

resources/js/
├── Pages/                # Páginas Inertia
├── Components/           # Componentes React
├── Hooks/                # Custom hooks
└── Types/                # TypeScript types

docs/
├── adr/                  # Architecture Decision Records
├── conventions/          # Padrões de implementação
└── vision/               # Visão de produto e roadmap

specs/                    # Specs de features (framework BRY)
```

---

## Documentação arquitetural

Antes de implementar qualquer feature, consulte:

- [`docs/adr/README.md`](docs/adr/README.md) — índice de todas as ADRs
- [`CLAUDE.md`](CLAUDE.md) — guia de trabalho para Claude Code

---

## CI

O pipeline roda automaticamente em todo push e pull request.

**Jobs:** Pint → Larastan → ESLint → Prettier → Type check → Pest

Ambiente: `laravelsail/php83-composer` + PostgreSQL 16 + Redis.
