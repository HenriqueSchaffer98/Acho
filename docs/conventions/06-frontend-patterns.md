# Convenção: Padrões de Frontend

## Stack

- **React 18** + TypeScript (strict mode)
- **Inertia.js** para integração com Laravel
- **Tailwind CSS 3** para estilização
- **Tanstack Query** para estado de servidor
- **React Hook Form + Zod** para formulários
- **Vite** para build

Stack completa em `ADR-019`.

---

## Princípios

1. **TypeScript strict mode sempre.** Sem `any` exceto em casos justificados (com comentário explicando).
2. **Componentes funcionais com hooks.** Sem class components.
3. **Tailwind utility-first.** CSS custom apenas em casos raros.
4. **Mobile-first.** Estilos default são para mobile, breakpoints expandem para desktop.
5. **Composição sobre herança.** Componentes pequenos compostos.

---

## Estrutura de página Inertia

```tsx
// resources/js/Pages/Public/ImovelDetail.tsx
import { Head } from '@inertiajs/react';
import { ImovelGallery } from '@/Components/imoveis/ImovelGallery';
import { ScheduleVisitButton } from '@/Components/imoveis/ScheduleVisitButton';
import type { Imovel, Tenant } from '@/Types/domain';

interface Props {
  imovel: Imovel;
  tenant: Tenant;
}

export default function ImovelDetail({ imovel, tenant }: Props) {
  return (
    <>
      <Head>
        <title>{imovel.titulo} | {tenant.nome}</title>
        <meta name="description" content={imovel.descricaoCurta} />
      </Head>

      <div className="container mx-auto px-4 py-8">
        <ImovelGallery fotos={imovel.fotos} />

        <div className="mt-6 lg:grid lg:grid-cols-3 lg:gap-8">
          <div className="lg:col-span-2">
            {/* conteúdo principal */}
          </div>

          <aside className="mt-6 lg:mt-0">
            <ScheduleVisitButton imovel={imovel} />
          </aside>
        </div>
      </div>
    </>
  );
}
```

**Pontos importantes:**

- Default export (Inertia exige)
- Props tipadas com interface
- Head tag para SEO em páginas públicas
- Layout responsivo com Tailwind
- Componentes específicos importados de `@/Components/`

---

## Estrutura de componente

### Componente "burro" (UI puro)

```tsx
// resources/js/Components/ui/Button.tsx
import { forwardRef } from 'react';
import { cn } from '@/Lib/utils';

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  size?: 'sm' | 'md' | 'lg';
  isLoading?: boolean;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = 'primary', size = 'md', isLoading, className, children, disabled, ...props }, ref) => {
    return (
      <button
        ref={ref}
        disabled={disabled || isLoading}
        className={cn(
          'inline-flex items-center justify-center rounded-lg font-medium transition-colors',
          'focus:outline-none focus:ring-2 focus:ring-offset-2',
          'disabled:opacity-50 disabled:cursor-not-allowed',
          {
            'bg-primary-600 text-white hover:bg-primary-700': variant === 'primary',
            'bg-gray-200 text-gray-900 hover:bg-gray-300': variant === 'secondary',
            'text-gray-700 hover:bg-gray-100': variant === 'ghost',
            'bg-red-600 text-white hover:bg-red-700': variant === 'danger',
            'h-9 px-3 text-sm': size === 'sm',
            'h-11 px-4 text-base': size === 'md',
            'h-12 px-6 text-lg': size === 'lg',
          },
          className,
        )}
        {...props}
      >
        {isLoading ? <Spinner /> : children}
      </button>
    );
  },
);

Button.displayName = 'Button';
```

### Componente "esperto" (com lógica)

```tsx
// resources/js/Components/imoveis/ImovelCard.tsx
import { Link } from '@inertiajs/react';
import type { Imovel } from '@/Types/domain';
import { formatCurrency } from '@/Lib/format';

interface ImovelCardProps {
  imovel: Imovel;
  showStatus?: boolean;
}

export function ImovelCard({ imovel, showStatus = false }: ImovelCardProps) {
  const fotoPrincipal = imovel.fotos[0]?.urls.medium;

  return (
    <Link
      href={`/imoveis/${imovel.slug}`}
      className="group block overflow-hidden rounded-lg border border-gray-200 hover:shadow-lg transition-shadow"
    >
      <div className="aspect-[4/3] overflow-hidden bg-gray-100">
        {fotoPrincipal ? (
          <img
            src={fotoPrincipal}
            alt={imovel.titulo}
            loading="lazy"
            className="h-full w-full object-cover group-hover:scale-105 transition-transform"
          />
        ) : (
          <div className="flex h-full items-center justify-center text-gray-400">
            Sem foto
          </div>
        )}
      </div>

      <div className="p-4">
        <h3 className="font-semibold text-gray-900 line-clamp-2">
          {imovel.titulo}
        </h3>

        <p className="mt-2 text-2xl font-bold text-primary-600">
          {formatCurrency(imovel.precoCentavos)}
        </p>

        <div className="mt-3 flex gap-3 text-sm text-gray-600">
          <span>{imovel.quartos} quartos</span>
          <span>{imovel.banheiros} banheiros</span>
          <span>{imovel.areaM2}m²</span>
        </div>

        {showStatus && (
          <span className="mt-3 inline-block px-2 py-1 text-xs rounded bg-green-100 text-green-800">
            {imovel.status}
          </span>
        )}
      </div>
    </Link>
  );
}
```

---

## Formulários (React Hook Form + Zod)

### Esquema Zod compartilhado

```tsx
// resources/js/Lib/schemas/imovel.ts
import { z } from 'zod';

export const createImovelSchema = z.object({
  titulo: z.string().min(5, 'Mínimo 5 caracteres').max(200),
  precoCentavos: z.number().int().positive('Preço deve ser positivo'),
  tipo: z.enum(['venda', 'aluguel']),
  quartos: z.number().int().min(0).max(20),
  banheiros: z.number().int().min(0).max(20),
  areaM2: z.number().positive(),
  bairroId: z.string().uuid(),
  corretorId: z.string().uuid(),
  descricao: z.string().max(5000).optional(),
});

export type CreateImovelInput = z.infer<typeof createImovelSchema>;
```

### Componente de formulário

```tsx
// resources/js/Components/forms/ImovelForm.tsx
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/Button';
import { FormField } from '@/Components/forms/FormField';
import { createImovelSchema, type CreateImovelInput } from '@/Lib/schemas/imovel';

interface ImovelFormProps {
  defaultValues?: Partial<CreateImovelInput>;
  onSuccess?: () => void;
}

export function ImovelForm({ defaultValues, onSuccess }: ImovelFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<CreateImovelInput>({
    resolver: zodResolver(createImovelSchema),
    defaultValues,
  });

  const onSubmit = handleSubmit((data) => {
    router.post('/admin/imoveis', data, {
      onSuccess: () => onSuccess?.(),
    });
  });

  return (
    <form onSubmit={onSubmit} className="space-y-6">
      <FormField label="Título" error={errors.titulo?.message}>
        <input
          type="text"
          {...register('titulo')}
          className="form-input"
        />
      </FormField>

      <FormField label="Preço (centavos)" error={errors.precoCentavos?.message}>
        <input
          type="number"
          {...register('precoCentavos', { valueAsNumber: true })}
          className="form-input"
        />
      </FormField>

      {/* ... outros campos ... */}

      <Button type="submit" isLoading={isSubmitting}>
        Salvar imóvel
      </Button>
    </form>
  );
}
```

---

## Tanstack Query (estado de servidor)

Use Tanstack Query quando precisar de dados que **Inertia não traz** (ex: validação em tempo real, dados que mudam frequentemente).

Para dados de página, prefira **props do Inertia** (mais simples, SSR-friendly).

### Hook customizado

```tsx
// resources/js/Hooks/useSubdomainAvailability.ts
import { useQuery } from '@tanstack/react-query';
import { useDebounce } from '@/Hooks/useDebounce';
import axios from 'axios';

interface SubdomainCheckResponse {
  available: boolean;
  suggestions: string[];
}

export function useSubdomainAvailability(slug: string) {
  const debouncedSlug = useDebounce(slug, 500);

  return useQuery({
    queryKey: ['subdomain-check', debouncedSlug],
    queryFn: async () => {
      const { data } = await axios.get<SubdomainCheckResponse>(
        '/api/subdomain/check',
        { params: { slug: debouncedSlug } },
      );
      return data;
    },
    enabled: debouncedSlug.length >= 3,
    staleTime: 30_000,
  });
}
```

### Uso

```tsx
function SubdomainInput() {
  const [slug, setSlug] = useState('');
  const { data, isLoading } = useSubdomainAvailability(slug);

  return (
    <div>
      <input value={slug} onChange={(e) => setSlug(e.target.value)} />
      {isLoading && <span>Verificando...</span>}
      {data && !data.available && (
        <span className="text-red-600">Indisponível</span>
      )}
    </div>
  );
}
```

---

## Tailwind: padrões

### Mobile-first

Estilos default são mobile. Breakpoints expandem para desktop:

```tsx
<div className="
  grid grid-cols-1 gap-4         /* mobile: 1 coluna */
  md:grid-cols-2                  /* tablet: 2 colunas */
  lg:grid-cols-3 lg:gap-6         /* desktop: 3 colunas, gap maior */
">
```

### Cores do tenant

Cor primária do tenant é injetada como CSS variable:

```css
/* gerada dinamicamente baseado no tenant */
:root {
  --color-primary-50: ...;
  --color-primary-500: ...;
  --color-primary-600: ...;
  --color-primary-700: ...;
}
```

Tailwind config mapeia para classes:

```js
// tailwind.config.js
theme: {
  extend: {
    colors: {
      primary: {
        50: 'rgb(var(--color-primary-50) / <alpha-value>)',
        500: 'rgb(var(--color-primary-500) / <alpha-value>)',
        // ...
      },
    },
  },
}
```

Uso:

```tsx
<button className="bg-primary-600 hover:bg-primary-700">
```

### Helper `cn` para condicional

```tsx
// resources/js/Lib/utils.ts
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
```

Uso:

```tsx
<div
  className={cn(
    'px-4 py-2 rounded',
    isActive && 'bg-primary-600 text-white',
    isDisabled && 'opacity-50 cursor-not-allowed',
    className,
  )}
>
```

---

## Performance

### Lazy loading de imagens

```tsx
<img
  src={imovel.fotoPrincipal}
  alt={imovel.titulo}
  loading="lazy"
  className="..."
/>
```

### Code splitting

```tsx
// Para componentes pesados não-críticos
import { lazy, Suspense } from 'react';

const Mapa = lazy(() => import('@/Components/imoveis/Mapa'));

function ImovelDetail() {
  return (
    <Suspense fallback={<MapaPlaceholder />}>
      <Mapa lat={imovel.lat} lng={imovel.lng} />
    </Suspense>
  );
}
```

### Memo onde faz sentido

```tsx
import { memo } from 'react';

export const ImovelCard = memo(function ImovelCard({ imovel }: Props) {
  // ...
});
```

**Quando usar memo:**

- Componente caro de renderizar
- Recebe props que mudam pouco
- Aparece em listas grandes

**Quando NÃO usar memo:**

- Componente simples (overhead não compensa)
- Props mudam toda renderização

---

## Acessibilidade (a11y)

### Mínimo aceitável

- Labels em todos os inputs (`<label>` ou `aria-label`)
- Botões com texto descritivo (não só ícone)
- Touch targets ≥ 44x44px no mobile
- Contraste mínimo 4.5:1 (WCAG AA)
- Navegação por teclado funcional
- `alt` em todas as imagens (vazio se decorativa)

### Foco visível

```tsx
<button className="
  focus:outline-none
  focus:ring-2 focus:ring-primary-500 focus:ring-offset-2
">
```

### ARIA básico

```tsx
<button
  aria-label="Fechar modal"
  aria-pressed={isPressed}
  aria-disabled={isDisabled}
>
```

---

## Tipos compartilhados

```tsx
// resources/js/Types/domain.ts

export type Uuid = string;

export interface Tenant {
  id: Uuid;
  slug: string;
  nome: string;
  logo: string | null;
  corPrimaria: string;
}

export type ImovelTipo = 'venda' | 'aluguel';
export type ImovelStatus = 'disponivel' | 'reservado' | 'vendido' | 'pausado';

export interface Imovel {
  id: Uuid;
  slug: string;
  titulo: string;
  descricao: string;
  precoCentavos: number;
  tipo: ImovelTipo;
  status: ImovelStatus;
  quartos: number;
  banheiros: number;
  areaM2: number;
  fotos: ImovelFoto[];
  corretor: Corretor;
  bairro: Bairro;
  createdAt: string;
}

export interface ImovelFoto {
  id: Uuid;
  ordem: number;
  urls: {
    thumbnail: string;
    medium: string;
    large: string;
  };
}

// ...
```

---

## O que NÃO fazer

- ❌ Class components (use functional + hooks)
- ❌ `any` sem justificativa explícita
- ❌ Lógica de negócio no componente (delegue para hooks/services)
- ❌ Fetch direto sem Tanstack Query (a menos que seja Inertia visit)
- ❌ CSS custom além do necessário (prefira Tailwind)
- ❌ Inline styles (use Tailwind)
- ❌ Componentes gigantes (>200 linhas) — divida
- ❌ Props drilling pesado (use Context se necessário)

---

## Referências

- ADRs relacionadas: `ADR-006` (Listing Module), `ADR-019` (Tech Stack), `ADR-025` (Project Patterns)
- Outras conventions: `01-architecture.md`, `02-folder-structure.md`
