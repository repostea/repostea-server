# Plan de Cobertura de Tests

**Cobertura actual: 46.3%**
**Objetivo: 80%+**

---

## 1. Policies (0% - CRÍTICO)

### AchievementPolicy
- [ ] Test: usuario puede ver logros propios
- [ ] Test: usuario puede ver logros de otros (si son públicos)
- [ ] Test: admin puede ver todos los logros

### KarmaEventPolicy
- [ ] Test: usuario puede ver sus eventos de karma
- [ ] Test: usuario NO puede ver eventos de otros
- [ ] Test: admin puede ver todos

### KarmaHistoryPolicy
- [ ] Test: usuario puede ver su historial
- [ ] Test: usuario NO puede ver historial de otros

### KarmaLevelPolicy
- [ ] Test: todos pueden ver niveles de karma

### UserPolicy
- [ ] Test: usuario puede ver/editar su perfil
- [ ] Test: usuario NO puede editar otros perfiles
- [ ] Test: admin puede editar cualquier perfil
- [ ] Test: usuario puede eliminar su cuenta

### UserStreakPolicy
- [ ] Test: usuario puede ver su racha
- [ ] Test: privacidad de rachas

### VotePolicy
- [ ] Test: usuario autenticado puede votar
- [ ] Test: usuario NO puede votar su propio contenido
- [ ] Test: usuario NO puede votar dos veces
- [ ] Test: guest NO puede votar

---

## 2. Services (< 50%)

### NotificationService (42.9%)
- [ ] Test: crear notificación
- [ ] Test: marcar como leída
- [ ] Test: eliminar notificación
- [ ] Test: obtener notificaciones no leídas

### WebPushService (42.9%)
- [ ] Test: enviar push notification
- [ ] Test: suscribir dispositivo
- [ ] Test: desuscribir dispositivo
- [ ] Test: manejar errores de envío

### TwitterService (49.6%)
- [ ] Test: publicar tweet
- [ ] Test: validar credenciales
- [ ] Test: manejar rate limits
- [ ] Test: posts que no deben publicarse

### PluginManager (37.0%)
- [ ] Test: cargar plugins
- [ ] Test: habilitar/deshabilitar plugin
- [ ] Test: ejecutar hooks de plugins

---

## 3. Services (50-80%)

### CommentVoteService (58.6%)
- [ ] Test: votar comentario
- [ ] Test: cambiar voto
- [ ] Test: eliminar voto
- [ ] Test: tipos de voto (funny, interesting, etc.)

### RealtimeBroadcastService (53.1%)
- [ ] Test: broadcast de nuevo post
- [ ] Test: broadcast de nuevo comentario
- [ ] Test: broadcast de voto

### PostService (72.6%)
- [ ] Test: crear post draft
- [ ] Test: publicar post
- [ ] Test: editar post
- [ ] Test: eliminar post
- [ ] Test: cambiar status (draft -> published -> hidden)

---

## 4. Policies (50-80%)

### PostPolicy (56.5%)
- [ ] Test: autor puede editar su post
- [ ] Test: autor NO puede editar post de otro
- [ ] Test: moderador puede ocultar posts
- [ ] Test: admin puede todo

### CommentPolicy (68.8%)
- [ ] Test: autor puede editar su comentario
- [ ] Test: tiempo límite para editar
- [ ] Test: moderador puede ocultar comentarios

---

## 5. Refactoring - Strings Hardcodeados

### Ya completado
- [x] Post::STATUS_PUBLISHED, STATUS_DRAFT, STATUS_PENDING, STATUS_HIDDEN
- [x] Comment::STATUS_PUBLISHED, STATUS_HIDDEN, STATUS_DELETED_BY_MODERATOR, STATUS_DELETED_BY_AUTHOR
- [x] Report::STATUS_PENDING, STATUS_RESOLVED, STATUS_DISMISSED
- [x] LegalReport::STATUS_PENDING, STATUS_UNDER_REVIEW, STATUS_RESOLVED, STATUS_REJECTED
- [x] User::STATUS_PENDING, STATUS_ACTIVE, STATUS_APPROVED, STATUS_REJECTED
- [x] Sub::MEMBERSHIP_PENDING, MEMBERSHIP_ACTIVE, MEMBERSHIP_BANNED

### Uso de constantes en todo el codebase (Completado)
- [x] Controllers: PostController, SubController, UserController, ActivityPubController, etc.
- [x] Services: PostService, CommentModerationService, TwitterService, RealtimeBroadcastService, etc.
- [x] Jobs: PostToTwitter, DeliverActivityPubPost, DeliverPostUpdate
- [x] Policies: PostPolicy
- [x] Resources: CommentResource
- [x] Commands: PromotePendingPosts

### Pendiente
- [ ] Vote types: ya existen en Vote model, verificar uso consistente en controladores

---

## Comandos Útiles

```bash
# Tests con cobertura (terminal)
php -dxdebug.mode=coverage vendor/bin/pest --coverage

# Tests con reporte HTML
php -dxdebug.mode=coverage vendor/bin/pest --coverage --coverage-html=coverage-report

# Tests de un archivo
php -dxdebug.mode=off vendor/bin/pest tests/Feature/Api/ActivityFeedControllerTest.php

# Tests rápidos (sin coverage)
php -dxdebug.mode=off vendor/bin/pest --no-coverage

# Ver cobertura de un servicio específico
php -dxdebug.mode=coverage vendor/bin/pest --coverage --filter="NotificationService"
```

---

## Progreso

| Área | Antes | Después | Estado |
|------|-------|---------|--------|
| Total | 46.3% | - | En progreso |
| ActivityFeedController | 0% | 100% | ✅ Completado |
| Policies | 0% | - | Pendiente |
| NotificationService | 42.9% | - | Pendiente |

---

## Notas

1. **Priorizar código crítico**: Services y Controllers que manejan lógica de negocio
2. **Políticas son importantes**: Definen permisos y seguridad
3. **No perseguir 100%**: Algunos archivos (Providers, configs) no necesitan tests
4. **Tests de integración**: Más valiosos que tests unitarios aislados
