#!/usr/bin/env bash
set -e

# Creates two Postgres roles required for RLS-based multi-tenancy (ADR-001):
#
#   acho_app      — application role, no BYPASSRLS.
#                   Used by all HTTP requests via the default 'pgsql' connection.
#
#   acho_migrator — privileged role, WITH BYPASSRLS.
#                   Used only by migrations, seeds, and tenant lookups.
#
# Both roles share DB_PASSWORD (POSTGRES_PASSWORD) in local dev.
# In production, set distinct passwords per role via environment variables.

psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB" <<-SQL
    DO \$\$
    BEGIN
        IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'acho_app') THEN
            CREATE ROLE acho_app
                WITH LOGIN PASSWORD '${POSTGRES_PASSWORD}'
                NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION;
        END IF;
    END;
    \$\$;

    DO \$\$
    BEGIN
        IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'acho_migrator') THEN
            CREATE ROLE acho_migrator
                WITH LOGIN PASSWORD '${POSTGRES_PASSWORD}'
                NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOREPLICATION BYPASSRLS;
        END IF;
    END;
    \$\$;

    GRANT ALL PRIVILEGES ON DATABASE "${POSTGRES_DB}" TO acho_migrator;
    GRANT CONNECT ON DATABASE "${POSTGRES_DB}" TO acho_app;

    GRANT CREATE, USAGE ON SCHEMA public TO acho_migrator;
    GRANT USAGE ON SCHEMA public TO acho_app;

    ALTER DEFAULT PRIVILEGES FOR ROLE acho_migrator IN SCHEMA public
        GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO acho_app;

    ALTER DEFAULT PRIVILEGES FOR ROLE acho_migrator IN SCHEMA public
        GRANT USAGE, SELECT ON SEQUENCES TO acho_app;
SQL

# Grant on testing databases. Sail creates a database named "testing";
# we also check for the "<db>_testing" pattern as a fallback.
grant_on_db() {
    local db="$1"
    DB_EXISTS=$(psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -tAc "SELECT 1 FROM pg_database WHERE datname='${db}'" 2>/dev/null || echo "")
    if [ "$DB_EXISTS" = "1" ]; then
        psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$db" <<-SQL
            GRANT ALL PRIVILEGES ON DATABASE "${db}" TO acho_migrator;
            GRANT CONNECT ON DATABASE "${db}" TO acho_app;

            GRANT CREATE, USAGE ON SCHEMA public TO acho_migrator;
            GRANT USAGE ON SCHEMA public TO acho_app;

            -- Testing-only: allow acho_app to CREATE in public so RefreshDatabase
            -- (Pest/PHPUnit) can run migrate:fresh as acho_app. Scoped to test DBs
            -- via grant_on_db; production retains the least-privilege baseline.
            GRANT CREATE ON SCHEMA public TO acho_app;

            -- Default privileges: tables created by acho_migrator are accessible by acho_app.
            ALTER DEFAULT PRIVILEGES FOR ROLE acho_migrator IN SCHEMA public
                GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO acho_app;

            ALTER DEFAULT PRIVILEGES FOR ROLE acho_migrator IN SCHEMA public
                GRANT USAGE, SELECT ON SEQUENCES TO acho_app;

            -- Default privileges: tables created by acho_app are accessible by acho_migrator.
            -- Needed because RefreshDatabase (Pest/PHPUnit) runs migrate:fresh as acho_app
            -- (trait method precedence prevents TestCase override), so tables end up
            -- owned by acho_app. acho_migrator (used by TenantService) needs SELECT access.
            ALTER DEFAULT PRIVILEGES FOR ROLE acho_app IN SCHEMA public
                GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO acho_migrator;

            ALTER DEFAULT PRIVILEGES FOR ROLE acho_app IN SCHEMA public
                GRANT USAGE, SELECT ON SEQUENCES TO acho_migrator;

            -- Ensure acho_migrator has access to any pre-existing tables owned by acho_app.
            GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO acho_migrator;
            GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO acho_migrator;
SQL
    fi
}

grant_on_db "testing"
grant_on_db "${POSTGRES_DB}_testing"
