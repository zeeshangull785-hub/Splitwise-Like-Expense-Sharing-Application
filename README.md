# Splitwise-Like Expense Sharing Application Backend

A robust backend REST API application built with **Laravel** and **MongoDB** modeled closely after Splitwise. Allows users to create groups, record and split expenses (equal, exact amounts, and percentage splits), track real-time net balances, simplify debts via a min-cash-flow algorithm, and record debt settlements.

---

## 🛠 Technology Stack & Requirements Compliance

- **Framework**: Laravel 11.x
- **Database**: MongoDB (via official `mongodb/laravel-mongodb` v5.0 package)
- **Database Rules**: **100% MongoDB document-oriented** (No SQL/MySQL/PostgreSQL/SQLite used).
- **Authentication**: **Manual Random Token Authentication** (No JWT, No Sanctum, No Passport).
- **Token Generation**: Cryptographically secure 64-character tokens via `bin2hex(random_bytes(32))`, persisted in MongoDB `tokens` collection.
- **Middleware**: Custom `AuthenticateWithToken` middleware resolving `Authorization: Bearer <token>` against MongoDB.
- **Layered Architecture**: Controllers $\to$ Form Requests $\to$ Service Layer $\to$ API Resources $\to$ MongoDB.
- **Atomicity**: Transactions handling with automatic replica-set / standalone fallback.
- **Testing**: Complete automated feature test suite (PHPUnit).

---

## 📂 MongoDB Collections & Design Architecture

| Collection | Model Class | Strategy | Rationale |
|---|---|---|---|
| `users` | [User](file:///d:/Split/app/Models/User.php) | Standalone document | Independent identity querying, unique email indexing. |
| `tokens` | [Token](file:///d:/Split/app/Models/Token.php) | Reference to `user_id` | Allows multi-device sessions and rapid token invalidation on logout. |
| `groups` | [Group](file:///d:/Split/app/Models/Group.php) | Reference to `owner_id`, embedded array of `member_ids` | Fast membership verification with indexed arrays (`member_ids`). |
| `expenses` | [Expense](file:///d:/Split/app/Models/Expense.php) | Reference to `group_id` & `paid_by`, **embedded `participants` array** | Split shares belong strictly to the expense document; embedding guarantees atomic consistency when read/written together. |
| `settlements` | [Settlement](file:///d:/Split/app/Models/Settlement.php) | Reference to `group_id`, `paid_by`, and `paid_to` | High auditability and independent payment history tracking. |

### MongoDB Indexes Created
Execute `php artisan mongo:create-indexes` to set up:
- `users`: `{ email: 1 }` (unique)
- `tokens`: `{ api_token: 1 }` (unique), `{ user_id: 1 }`
- `groups`: `{ owner_id: 1 }`, `{ member_ids: 1 }`
- `expenses`: `{ group_id: 1, created_at: -1 }`, `{ paid_by: 1 }`, `{ group_id: 1, split_type: 1 }`, `{ group_id: 1, category: 1 }`
- `settlements`: `{ group_id: 1, created_at: -1 }`, `{ paid_by: 1 }`, `{ paid_to: 1 }`

---

## 🚀 Setup & Installation Instructions

### 1. Requirements
- PHP >= 8.2 with `mongodb` extension enabled in `php.ini` (`extension=mongodb`).
- MongoDB Community Server running locally on port `27017`.
- Composer 2.x

### 2. Environment Configuration
The `.env` is pre-configured for MongoDB:
```env
DB_CONNECTION=mongodb
DB_HOST=127.0.0.1
DB_PORT=27017
DB_DATABASE=splitwise_db
```

### 3. Install Dependencies & Set App Key
```bash
composer install
php artisan key:generate
```

### 4. Create Indexes & Seed Realistic Data
```bash
php artisan mongo:create-indexes
php artisan db:seed
```
*The database seeder populates:*
- **12 Users** (with active tokens, emails like `ali@example.com`, password: `password123`)
- **4 Groups** (`Dubai Trip`, `Apartment Expenses`, `Office Lunch`, `Weekend Getaway`)
- **12 Expenses** demonstrating:
  - Equal split (with cent remainder distribution)
  - Exact split (validated to total amount)
  - Percentage split (validated to 100%)
- **Settlement Payments** updating group balances.

### 5. Run the Local Development Server
```bash
php artisan serve
```
Base URL will be: `http://127.0.0.1:8000`

---

## 🧪 Running Automated Tests

Run the full PHPUnit test suite covering authentication, groups, expenses, splits, balances, and settlements:
```bash
php artisan test
```
All 22 tests pass with 82 assertions.

---

## 📡 API Reference Overview

All requests and responses use JSON and follow a unified envelope format:

### Standard Success Response
```json
{
    "success": true,
    "message": "Expense created successfully.",
    "data": { ... }
}
```

### Standard Error Response
```json
{
    "success": false,
    "message": "Validation error or business logic exception.",
    "errors": { ... }
}
```

### Authentication Header
For all protected routes, include:
```http
Authorization: Bearer <random_token>
```

### Endpoints Summary

#### 1. Authentication
- `POST /api/register` — Register user & receive random 64-char token.
- `POST /api/login` — Login with email/password & receive new random token.
- `POST /api/logout` — Invalidate current token (requires Auth).
- `GET /api/me` — View current authenticated user profile (requires Auth).

#### 2. Groups & Membership
- `GET /api/groups` — List groups for authenticated user.
- `POST /api/groups` — Create new group (creator becomes owner).
- `GET /api/groups/{group}` — View group details.
- `GET /api/groups/{group}/members` — View group members.
- `POST /api/groups/{group}/members` — Add member to group (owner only).
- `DELETE /api/groups/{group}/members/{user}` — Remove member from group (owner only).

#### 3. Expenses
- `GET /api/groups/{group}/expenses` — Paginated, filtered (`payer`, `split_type`, `category`, `date_from`, `date_to`, `search`) and sorted expenses.
- `POST /api/groups/{group}/expenses` — Create expense (supports `equal`, `exact`, `percentage` splits, optional category, receipt file).
- `GET /api/expenses/{expense}` — View expense details with participant shares.
- `PUT /api/expenses/{expense}` — Update expense & recalculate participant shares.
- `DELETE /api/expenses/{expense}` — Delete expense & recalculate balances.

#### 4. Balances & Settlements
- `GET /api/groups/{group}/balances` — Returns net balances for every member and simplified debts (who owes whom).
- `GET /api/groups/{group}/settlements` — List settlement payment history.
- `POST /api/groups/{group}/settlements` — Record debt payment between members (validates amount does not exceed debtor's outstanding balance).

---

## 📮 Postman Collection
The file [`Splitwise_API.postman_collection.json`](file:///d:/Split/Splitwise_API.postman_collection.json) is included in the project root. Import it into Postman to test all endpoints out-of-the-box.
