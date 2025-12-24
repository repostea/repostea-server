# Admin Panel Components

Componentes Blade reutilizables para mantener consistencia en todo el panel de administración.

## Componentes Disponibles

### Badge
Badge para estados y categorías con colores predefinidos.

**Uso:**
```blade
<x-admin.badge type="success" label="Published" />
<x-admin.badge type="warning" label="Draft" />
<x-admin.badge type="danger" label="Hidden" />
<x-admin.badge type="info" label="Info" />
```

**Tipos disponibles:**
- `success`, `published`, `active` → Verde
- `warning`, `draft`, `pending` → Amarillo
- `danger`, `hidden`, `banned` → Rojo
- `info` → Azul
- `purple` → Púrpura
- `default` → Gris

### Action Link
Link de acción con estilo consistente y soporte para links externos.

**Uso:**
```blade
<x-admin.action-link :href="route('admin.posts.view', $post)">
    Edit
</x-admin.action-link>

<x-admin.action-link :href="config('app.client_url') . '/post/' . $post->id" :external="true">
    View in app
</x-admin.action-link>
```

### Mobile Label
Label para móvil con formato consistente.

**Uso:**
```blade
<x-admin.mobile-label label="Status" />
<x-admin.mobile-label label="Created" />
```

### Search Form
Formulario de búsqueda reutilizable con filtros opcionales.

**Uso:**
```blade
<x-admin.search-form placeholder="Search posts...">
    <x-slot name="filters">
        <select name="status" class="...">
            <option value="">All</option>
            <option value="published">Published</option>
        </select>
    </x-slot>
</x-admin.search-form>
```

### Empty State (Desktop)
Estado vacío para tablas en desktop.

**Uso:**
```blade
<x-admin.empty-state
    icon="file-alt"
    message="No posts found"
    colspan="6"
/>
```

### Empty State Mobile
Estado vacío para cards en móvil.

**Uso:**
```blade
<x-admin.empty-state-mobile
    icon="users"
    message="No users found"
/>
```

### Table Wrapper
Wrapper completo para tablas con soporte desktop/móvil.

**Uso:**
```blade
<x-admin.table :headers="[
    ['label' => 'Post', 'class' => ''],
    ['label' => 'Author', 'class' => 'hidden lg:table-cell'],
    ['label' => 'Status', 'class' => ''],
    ['label' => 'Actions', 'class' => '']
]">
    <x-slot name="search">
        <x-admin.search-form placeholder="Search..." />
    </x-slot>

    <x-slot name="desktop">
        @foreach($items as $item)
            <tr>
                <td>{{ $item->title }}</td>
                ...
            </tr>
        @endforeach
    </x-slot>

    <x-slot name="mobile">
        @foreach($items as $item)
            <div class="p-3">
                <p>{{ $item->title }}</p>
                ...
            </div>
        @endforeach
    </x-slot>

    <x-slot name="pagination">
        {{ $items->links() }}
    </x-slot>
</x-admin.table>
```

## Clases CSS Comunes

En `public/css/admin-common.css` hay clases reutilizables:

### Tabla
- `.admin-table` - Tabla base
- `.admin-table thead` - Header de tabla
- `.admin-table th` - Celdas de header
- `.admin-table tbody` - Body de tabla
- `.admin-table td` - Celdas de datos

### Badges
- `.admin-badge` - Badge base
- `.admin-badge-success` - Verde
- `.admin-badge-warning` - Amarillo
- `.admin-badge-danger` - Rojo
- `.admin-badge-info` - Azul

### Links
- `.admin-action-link` - Link de acción normal
- `.admin-action-link-danger` - Link de acción peligroso

### Cards
- `.admin-card` - Card base
- `.admin-card-header` - Header de card
- `.admin-card-body` - Body de card

### Utilidades Móvil
- `.admin-mobile-label` - Label en móvil
- `.admin-mobile-card` - Card en móvil
- `.admin-mobile-info` - Info en móvil
- `.admin-author` - Autor en cursiva

### Formularios
- `.admin-search-input` - Input de búsqueda
- `.admin-search-btn` - Botón de búsqueda

### Empty State
- `.admin-empty` - Container de empty state
- `.admin-empty-icon` - Icono de empty state

## Convenciones de Diseño

### Responsive Breakpoints
- `md` (768px): Separador desktop/móvil principal
- `lg` (1024px): Mostrar columnas adicionales
- `xl` (1280px): Mostrar todas las columnas

### Badges de Estado
Usar badges consistentes para estados:
- **Published/Active**: `type="success"`
- **Draft/Pending**: `type="warning"`
- **Hidden/Banned**: `type="danger"`

### Autor en Cursiva
Siempre mostrar nombres de usuario de autores en cursiva:
```blade
<a class="italic">{{ $user->username }}</a>
```

### Prefijos en Móvil
En móvil usar prefijos para claridad:
```blade
<x-admin.mobile-label label="Status" />
<x-admin.badge type="success" label="Published" />
```

### Links de Acción
Usar links simples en lugar de botones para acciones principales:
```blade
<x-admin.action-link :href="route('admin.posts.view', $post)">
    Edit
</x-admin.action-link>
```
