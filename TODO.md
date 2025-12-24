# TODO - Repostea Server

## High Priority

### Missing Controller HTTP Tests (before refactoring)

#### SubController - 17 endpoints sin tests HTTP
- [ ] **CRUD**: destroy, uploadIcon (~4 tests)
- [ ] **Post Moderation**: pendingPosts, approvePost, rejectPost, hidePost, unhidePost, hiddenPosts (~12 tests)
- [ ] **Membership**: removeMember, membershipRequests, approveMembershipRequest, rejectMembershipRequest (~8 tests)
- [ ] **Moderators**: moderators, addModerator, removeModerator (~6 tests)
- [ ] **Ownership**: claimOwnership, claimStatus (~4 tests)

#### After tests complete
- [ ] **SubController refactoring** - Consider extracting SubModeratorService (997 lines)
- [ ] **CommentController review** - Check if more extraction needed (778 lines)

### Missing Service Tests
- None remaining in high priority

## Medium Priority

### Stub Implementations
- None remaining (all completed Dec 2024)

### OAuth Service Tests (External API dependencies)
- All completed (see below)

### Code Quality
- [x] Review and add missing PHPDoc blocks ✅ (PHP 8 style)
- [x] Check for any remaining Spanish comments to translate ✅
- [x] Review error handling consistency across services ✅

## Low Priority

### Performance
- [ ] Review database query optimization opportunities
- [x] Check for N+1 query issues ✅ (Dec 2024)
- [x] Review caching strategies ✅ (TagController TTL, CommentObserver invalidation)

### Documentation (long-term, when needed)
- [ ] API endpoint documentation (Scribe) - when third-party apps demand it
- [ ] CONTRIBUTING.md - when attracting contributors

## Completed

### Service Tests Added (Dec 2024)
- [x] SubMembershipService - 18 tests
- [x] AchievementService - 16 tests
- [x] StreakService - 9 tests (+ bug fixes)
- [x] NotificationService - 6 tests
- [x] RealtimeBroadcastService - 14 tests
- [x] WebPushService - 15 tests
- [x] SubModerationService - 16 tests
- [x] ImageService - 19 tests
- [x] CommentModerationService - 21 tests
- [x] ActivityPubService - 32 tests
- [x] MultiActorActivityPubService - 33 tests
- [x] MastodonOAuthService - 24 tests
- [x] MbinOAuthService - 24 tests
- [x] TelegramAuthService - 16 tests
- [x] TwitterService - 29 tests

### Refactoring Completed
- [x] SubController - Extracted membership logic to SubMembershipService
- [x] SubController - Extracted moderation logic to SubModerationService
- [x] CommentController - Extracted moderation logic to CommentModerationService
- [x] Fixed StreakService bug (diffInDays type issue)
- [x] Fixed Sub model (orphaned_at not in fillable)

### Stub Implementations Fixed (Dec 2024)
- [x] SubController::destroy() - Added owner permission check + soft delete
- [x] SubController::rules() - Returns rules from database instead of hardcoded
- [x] SubController::createMembershipRequest() - Uses SubMembershipService::join()
- [x] SubController::membershipRequests() - Already implemented with SubMembershipService
- [x] SubController::approveMembershipRequest() - Already implemented with SubMembershipService
- [x] SubController::rejectMembershipRequest() - Already implemented with SubMembershipService

### N+1 Query Fixes (Dec 2024)
- [x] UserController - Achievement queries optimized (3 instances → 1 query)
- [x] SavedListController::posts() - Votes eager loaded instead of N queries
- [x] PostResource - Reports only included when eager loaded
- [x] UserController::show() - Uses withCount instead of separate count queries
- [x] NotificationController::getSummary() - Pre-calculate new counts in single query
- [x] PostRelationshipController::index() - Pre-load user votes before loop
- [x] WebPushService::cleanupExpiredSubscriptions() - Single delete query

### Security Improvements (Dec 2024)
- [x] Rate limiting on auth endpoints (register, forgot-password, magic-link)
- [x] Rate limiting on OAuth callbacks (Mastodon, Mbin, Telegram)
- [x] Rate limiting on user search (30/min)
- [x] PostController::registerImpressions() - Input validation + 100 post limit
- [x] ImageService - EXIF stripped via WebP conversion (verified)
