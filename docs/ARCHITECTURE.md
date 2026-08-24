# GiftOH Architecture Documentation

## Project Overview

GiftOH (Gift of Hope) is a Laravel-based charity funding platform with a hybrid architecture transitioning to **Firebase Realtime Database** as the primary data source.

## Current State

### Technology Stack

- **Backend**: Laravel 12.x with PHP 8.2+
- **Frontend**: Blade templates + TypeScript/Vue (hybrid)
- **Database**: Firebase Realtime Database (primary) + SQL (fallback)
- **Frontend Build**: Vite + Tailwind CSS
- **AI Integration**: Google Gemini API for funding request scoring

### Language Composition

- PHP: 56.6%
- TypeScript: 21.8%
- Blade: 19.9%
- CSS: 1.5%
- Other: 0.2%

## Architecture Layers

### 1. Controllers (HTTP Entry Points)

**Location**: `app/Http/Controllers/`

Controllers handle HTTP requests and delegate to services.

- `PageController`: Renders views
- `AccountController`: User authentication and account management
- `AdminController`: Admin dashboard and funding approval workflows
- `VerificationController`: Email verification and 2FA logic
- `ConfigController`: Public configuration endpoints

**Current Issue**: Controllers are too fat; business logic is mixed with HTTP handling.

**Recommendation**: Inject services into constructors; delegates business logic to services.

### 2. Services (Business Logic)

**Location**: `app/Services/`

Services encapsulate business logic and coordinate between repositories and external APIs.

#### Existing Services

- **FirebaseService**: Initializes Firebase connection (Kreait SDK wrapper)
- **VerificationService**: Handles email verification code generation, storage, and verification

#### New Services (Phase 1-3)

- **AiScoringService**: Handles Gemini API calls for funding request evaluation
- **FundingService**: Coordinates funding request creation, scoring, approval workflows
- **AuthenticationService**: (Planned) Centralizes login/register logic

**Pattern**: Services receive repositories via dependency injection and implement single responsibility.

### 3. Repositories (Data Access Layer)

**Location**: `app/Repositories/`

Repositories abstract data access and provide a consistent interface regardless of backend (Firebase/SQL).

#### Existing Repositories

- `FirebaseRepository` (base class)
- `FirebaseUserRepository`
- `FirebaseFundingRepository`
- `FirebaseDonationRepository`
- `FirebaseApprovalRepository`
- `FirebaseAppealRepository`
- `FirebaseVerificationRepository`
- `FirebaseLogRepository`
- `FirebasePasswordResetRepository`

**Pattern**: Each repository extends `FirebaseRepository` and overrides `nodeKey()` to specify the Firebase node path.

### 4. Contracts (Interfaces)

**Location**: `app/Contracts/`

Contracts define the public interface for repositories and services. This enables:
- Swapping implementations (Firebase ↔ SQL)
- Better testing with mocks
- Clear API documentation

#### Phase 1-3 Contracts

- `UserRepositoryContract`: Defines user CRUD operations
- `FundingRepositoryContract`: Defines funding request operations

**Benefit**: Services depend on contracts, not concrete implementations.

### 5. Models (Data Structures)

**Location**: `app/Models/`

Models represent data structures. Currently uses Eloquent ORM for SQL fallback.

**Note**: `FirebaseUser` implements `Authenticatable` for custom auth provider.

**Recommendation**: Gradually migrate Eloquent models to use Firebase repositories.

## Data Flow

### Request Flow

```
HTTP Request
    ↓
Route (routes/web.php)
    ↓
Controller (PageController, AccountController, etc.)
    ↓
Service (FundingService, VerificationService, etc.)
    ↓
Repository (FirebaseFundingRepository, etc.)
    ↓
FirebaseService → Kreait SDK → Firebase Database
```

### Example: Creating a Funding Request

```php
// 1. Controller receives HTTP request
class FundingController {
    public function __construct(private FundingService $fundingService) {}
    
    public function store(Request $request) {
        // 2. Delegate to service
        $funding = $this->fundingService->createRequest($request->validated());
        return redirect()->route('funding.show', $funding['id']);
    }
}

// 2. Service orchestrates business logic
class FundingService {
    public function createRequest(array $data): array {
        // Validate user
        $user = $this->userRepository->findById($data['user_id']);
        
        // Create via repository
        $funding = $this->fundingRepository->create($data);
        
        // Score via AI service
        $this->scoreRequest($funding['id']);
        
        return $funding;
    }
}

// 3. Repository handles data access
class FirebaseFundingRepository {
    public function create(array $data): array {
        $id = $data['id'] ?? Str::uuid();
        $reference = $this->firebase->getDatabase()->getReference('funding_requests/' . $id);
        $reference->set([...$data, 'created_at' => now()->toIso8601String()]);
        return $data;
    }
}
```

## Firebase Node Structure

**Location**: `config/firebase.php`

Defines the JSON paths in Firebase Realtime Database:

```
firebase-root/
├── users/
│   ├── {user_id}
│   │   ├── fname
│   │   ├── email
│   │   ├── password
│   │   └── ...
├── funding_requests/
│   ├── {request_id}
│   │   ├── user_id
│   │   ├── title
│   │   ├── ai_score
│   │   └── ...
├── donations/
├── funding_approvals/
├── audit_logs/
└── ...
```

## Authentication

### Current Implementation

- **Provider**: `FirebaseUserProvider` (custom Laravel auth provider)
- **Credentials**: Firebase Realtime Database user records
- **Session**: Database-backed sessions (Laravel default)

**Location**: `app/Auth/FirebaseUserProvider.php`

**Flow**:

```php
// Authenticate
Auth::guard('web')->login($firebaseUser);

// Check authentication
if (Auth::check()) {
    $user = Auth::user(); // Returns FirebaseUser instance
}
```

## Verification & 2FA

### Email Verification Codes

- **Storage**: Firebase at `verification_codes/{type}/{email}`
- **Service**: `VerificationService`
- **Flow**:
  1. Generate 6-digit code
  2. Store hashed in Firebase
  3. Send via email
  4. User submits code
  5. Service verifies hash match
  6. Cleanup after use or expiry

### Password Reset

- **Storage**: Firebase at `password_reset_tokens/{email}`
- **Expiry**: 15 minutes
- **Flow**: Same as email verification

## Configuration Management

### Environment Configuration

**File**: `.env`

```env
DATA_DRIVER=firebase          # Primary data source
USE_FIREBASE=true             # Enable Firebase
FIREBASE_CREDENTIALS=...      # Server-side credentials (private)
FIREBASE_DATABASE_URL=...     # Server-side database URL
FIREBASE_API_KEY=...          # Client-side public key
```

### Config Files

- `config/datasource.php`: Data source configuration
- `config/firebase.php`: Firebase node mappings
- `config/services.php`: Third-party service credentials

## Frontend Integration

### Firebase Client SDK

**Location**: `resources/js/firebase.ts`

Fetches public Firebase config from backend API:

```typescript
// Fetch config from backend
const config = await fetch('/config/firebase').then(r => r.json());

// Initialize Firebase
const app = initializeApp(config);
```

**Benefits**:
- ✅ No hardcoded secrets in frontend
- ✅ Easy credential rotation
- ✅ Single source of truth (server)

### TypeScript Path Aliases

**File**: `tsconfig.json`

```json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@/*": ["resources/js/*"],
      "@services/*": ["app/Services/*"]
    }
  }
}
```

## Development Workflow

### Setup

```bash
# 1. Clone repository
git clone https://github.com/mnssrnc-a11y/giftOH.git

# 2. Install dependencies
composer install
npm install

# 3. Configure environment
cp .env.example .env
php artisan key:generate

# 4. Verify Firebase connectivity
php artisan firebase:health

# 5. Start development server
composer run dev
```

### Running Tests

```bash
# Run PHP tests
php artisan test

# Run frontend tests
npm run test
```

### Debugging

- **Laravel Debugbar**: Enabled in local environment
- **Firebase Rules Console**: `firebase.google.com` → Database Rules
- **Browser DevTools**: Inspect network requests to `/config/firebase`

## Refactoring Roadmap (Phase 1-3)

### Phase 1: Establish Firebase-First Architecture ✅

- [x] Create `DataSourceConfig` to define Firebase as primary driver
- [x] Add `DATA_DRIVER` and `USE_FIREBASE` to `.env.example`
- [x] Document migration status in config

### Phase 2: Simplify Controllers ✅

- [x] Extract `AiScoringService` with isolated Gemini API logic
- [x] Extract `FundingService` for funding business logic
- [x] Update controllers to inject services
- [ ] (Future) Implement job queues for long-running operations

### Phase 3: Standardize Firebase Access ✅

- [x] Create `UserRepositoryContract` interface
- [x] Create `FundingRepositoryContract` interface
- [x] Update controllers to use repository contracts
- [x] Add configuration endpoint `/config/firebase`
- [x] Move Firebase SDK initialization to backend

## Future Improvements

### Phase 4: Consolidate Configuration

- Merge `config/firebase.php` into `config/datasource.php`
- Create unified service provider for all Firebase bindings

### Phase 5: Fix Frontend Integration

- Migrate remaining TypeScript components to consume backend API
- Remove hardcoded Firebase config from `resources/js/app.js`
- Implement proper error handling for config fetch failures

### Phase 6: Reorganize Directory Structure

- Group services by domain (e.g., `Services/Funding/`, `Services/Auth/`)
- Create organized repository structure
- Add clear separation of concerns

## Common Patterns

### Dependency Injection

```php
// Service receives dependencies via constructor
class FundingService {
    public function __construct(
        private FundingRepositoryContract $fundingRepository,
        private UserRepositoryContract $userRepository,
        private AiScoringService $aiScoringService,
    ) {}
}

// Controller injects services
class AdminController {
    public function __construct(
        private FundingService $fundingService,
    ) {}
}
```

### Repository Queries

```php
// Consistent interface across implementations
$user = $userRepository->findByEmail('user@example.com');
$fundings = $fundingRepository->findByUserId($userId);
$pending = $fundingRepository->findByStatus('pending');
```

### Service Logging

```php
Log::info('Event description', [
    'user_id' => $userId,
    'entity_id' => $entityId,
    'timestamp' => now(),
]);
```

## Testing Strategy

### Unit Tests

```php
// Tests for Services
class FundingServiceTest extends TestCase {
    public function test_create_request_validates_user() {
        // Mock repository
        $mockRepository = Mockery::mock(FundingRepositoryContract::class);
        // Assert
    }
}
```

### Integration Tests

```php
// Tests for Controllers
class FundingControllerTest extends TestCase {
    public function test_store_creates_and_scores_request() {
        // Use real services with fake Firebase
        // Assert request was created and scored
    }
}
```

## Security Considerations

1. **Firebase Credentials**: Keep `FIREBASE_CREDENTIALS` private; never commit
2. **Client Config**: Only expose public keys in `/config/firebase` endpoint
3. **Authentication**: Use Laravel's session middleware for CSRF protection
4. **Email Verification**: Codes are 6-digit, hashed, and expiring
5. **Admin Actions**: Verify authorization in service layer

## Troubleshooting

### Firebase Connection Issues

```bash
# Check Firebase health
php artisan firebase:health

# View Firebase user
php artisan firebase:user email@example.com

# Inspect repositories
php artisan firebase:repositories
```

### Authentication Failures

- Verify `auth.providers.users.driver` is set to `firebase`
- Check `FirebaseUserProvider` registration in `AppServiceProvider`
- Ensure Firebase credentials are valid

### AI Scoring Issues

- Verify `GEMINI_API_KEY` is set in `.env`
- Check API rate limits and quotas
- Review logs in `storage/logs/`

## References

- [Laravel Documentation](https://laravel.com/docs)
- [Firebase Realtime Database](https://firebase.google.com/docs/database)
- [Kreait Firebase PHP SDK](https://github.com/kreait/firebase-php)
- [Google Gemini API](https://ai.google.dev/)
