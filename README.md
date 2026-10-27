# Laravel Product API

Backend API for user accounts and privately owned products, built with Laravel 13, PHP 8.4, MySQL, Eloquent, and Laravel Sanctum. Authentication uses session cookies with CSRF protection.

## Features

- Sign up, sign in, and sign out with session regeneration and invalidation.
- View and update a profile, including password changes verified against the current password.
- Create, paginate, view, update, and delete products belonging to the signed-in user.
- Validate JSON requests and serialize public response fields through API resources.
- Limit login and registration attempts and return JSON errors.
- Run feature tests in an isolated database and exercise real HTTP requests using the included Postman collections.

## Stack

| Component | Technology |
| --- | --- |
| Runtime | PHP 8.4 |
| Framework | Laravel 13 |
| Database | MySQL (`laravel_product_db`) |
| ORM / schema | Eloquent / database migrations |
| Authentication | Laravel Sanctum, session cookies, CSRF |
| Testing | PHPUnit / SQLite in-memory / Postman |

The API needs no frontend build or Node.js installation. The default Laravel welcome page and asset scaffolding remain from the starter project.

## Run locally

Requirements: PHP 8.4 with the extensions required by Composer, PDO MySQL and PDO SQLite (for tests), Composer 2, and a running MySQL server.

After cloning the repository, open a terminal in the project folder. On a **new installation**:

```powershell
composer install
Copy-Item .env.example .env
```

On macOS/Linux, use `cp .env.example .env` instead of `Copy-Item`.

Create an empty MySQL database named `laravel_product_db`, then edit `.env` with your own `DB_USERNAME` and `DB_PASSWORD`. Finally run:

```powershell
php artisan key:generate
php artisan config:clear
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

The API is available at `http://127.0.0.1:8000`. MySQL must remain running. Stop the development server with Ctrl+C.

For an **existing installation**, retain `.env`, the application key, and database data. Run `composer install`, `php artisan config:clear`, and `php artisan migrate:status`. Apply only pending migrations with `php artisan migrate` if needed, then start the server. Do not overwrite `.env` or regenerate an existing key.

Relevant local configuration (these are examples, not extracted credentials):

```dotenv
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=laravel_product_db
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SANCTUM_STATEFUL_DOMAINS=127.0.0.1:8000,localhost:8000
CACHE_STORE=database
```

Keep DB_USERNAME and DB_PASSWORD private. Keep APP_KEY stable on an existing installation. Use HTTPS, SESSION_SECURE_COOKIE=true, and APP_DEBUG=false when deployed. Use a persistent shared cache for rate limits. Run `php artisan config:clear` after editing environment settings. Do not run `migrate:fresh`, `migrate:reset`, or reset your existing database.

`composer setup` installs dependencies, copies `.env.example` only if `.env` is missing, and clears configuration. It does not generate keys or migrate the database; follow the appropriate steps above. `composer dev` starts the local API server.

## Endpoints

All requests accept JSON. Send `Accept: application/json`. For bodies, send `Content-Type: application/json`. API authentication uses session cookies and CSRF protection.

| Method | URL | Result |
| --- | --- | --- |
| GET | /sanctum/csrf-cookie | 204; initializes session and XSRF cookies |
| POST | /api/register | 201; creates user and signs in |
| POST | /api/login | 200; signs in |
| POST | /api/logout | 200; signs out and invalidates session |
| GET | /api/profile | 200; current user's public profile |
| PATCH | /api/profile | 200; updates current user's profile |
| GET | /api/user | Existing alias for profile |
| GET | /api/products | 200; current user's products, 15 per page |
| POST | /api/products | 201; creates current user's product |
| GET | /api/products/{id} | 200; one owned product |
| PATCH or PUT | /api/products/{id} | 200; partial update of an owned product |
| DELETE | /api/products/{id} | 200; deletes an owned product |

Profile, logout, and every product endpoint require authentication. PUT and PATCH both accept partial product updates. Lists include `data`, `links`, and `meta`; use `?page=2` for another page. Individual user/product responses are wrapped in `data`. Delete and logout return a JSON `message`.

Product price is serialized as a two-decimal string to retain decimal formatting. User responses expose only id, name, and email.

## Run the included Postman collections

Import these files into your existing Postman workspace:

- [Complete API tests: 49 requests](Laravel-Product-API.postman_collection.json)
- [Rate-limit tests: 11 requests](Laravel-Product-API-Rate-Limits.postman_collection.json)

1. Start MySQL and the Laravel server.
2. Use the Postman desktop app, or select Desktop Agent in the web app to reach localhost.
3. Open a request's **Cookies > Domains Allowlist** and add `127.0.0.1` without a scheme or port.
4. Keep **No Environment** selected. The collections provide their own `base_url` and test variables. Keep the cookie jar enabled.
5. Wait at least 65 seconds after previous login/registration requests so rate-limit buckets can expire.
6. Run the complete collection with all 49 requests selected, in order, **1 iteration**, and **0 ms delay**.
7. Wait another 65 seconds, then run the separate rate-limit collection with all 11 requests, **1 iteration**, and **0 ms delay**. Avoid other login/registration requests during that run.

The complete run generates unique user emails, captures product IDs, and reads the latest CSRF token from the cookie jar. It creates two disposable users and removes its own products. The users remain in MySQL. Existing records are not selected for deletion. Re-run from request 01 if interrupted; the next run uses fresh users. The rate-limit run uses invalid inputs and creates no records.

Some requests intentionally expect 401, 404, 419, 422, or 429. Check the **test assertions**, not just whether HTTP statuses are 2xx. Unexpected statuses stop the run.

The complete collection was verified locally with **123 passing assertions** and no failures. The separate login/registration rate-limit collection also passed. These HTTP checks ran against the local MySQL application; they complement the SQLite feature tests below.

## Individual requests: cookies and CSRF

Use one hostname consistently; these instructions use `http://127.0.0.1:8000`. Do not switch between localhost and 127.0.0.1 during a session.

1. For your own manually created requests, create and select a Postman environment with `base_url` = `http://127.0.0.1:8000`.
2. Keep Postman's cookie jar enabled. Use **No Auth**; no Bearer token is needed.
3. Add `Accept: application/json` and `Origin: {{base_url}}` to every request. For JSON bodies, also add `Content-Type: application/json`. Origin must match a host AND port in SANCTUM_STATEFUL_DOMAINS; that setting contains no scheme.
4. Send `GET {{base_url}}/sanctum/csrf-cookie`. Expect 204 and cookies named XSRF-TOKEN and the application's session cookie (its name is derived from APP_NAME). The session cookie is HttpOnly. Postman must send both cookies on subsequent requests.
5. Add `127.0.0.1` to **Cookies > Domains Allowlist**.
6. On modifying requests, add this **pre-request script** to supply the latest `X-XSRF-TOKEN` header:

```javascript
pm.cookies.jar().get('http://127.0.0.1:8000', 'XSRF-TOKEN', (error, token) => {
    if (error || !token) {
        throw new Error('Get CSRF cookies first and allowlist 127.0.0.1.');
    }

    pm.request.headers.upsert({
        key: 'X-XSRF-TOKEN',
        value: decodeURIComponent(token)
    });
});
```

The header contains the decoded cookie value, not a raw session token. Login, registration, and logout regenerate the CSRF token. The first CSRF GET needs no X-XSRF-TOKEN header or script. Keep each manual header only once; duplicate Origin headers can stop Sanctum from recognizing a stateful request. Leave Authorization set to **No Auth**.

Register with a fresh email:

```json
{
  "name": "Interview User",
  "email": "interview@example.test",
  "password": "StrongPassword123!",
  "password_confirmation": "StrongPassword123!"
}
```

Registration automatically signs you in. To test login separately, log out, refresh the CSRF cookie, then POST /api/login:

```json
{
  "email": "interview@example.test",
  "password": "StrongPassword123!"
}
```

Create a product:

```json
{
  "name": "Keyboard",
  "description": "USB keyboard",
  "price": "125.50",
  "stock": 4
}
```

Use its returned data.id for show, update, and delete. Example PATCH body: `{"price":"99.99","stock":3}`.

Product name is required on creation and limited to 255 characters. Description is nullable and limited to 10,000 characters. Price is required on creation, nonnegative, at most 99999999.99, and accepts up to two decimal places. Stock defaults to 0 and must be an integer from 0 to 4294967295. Sending user_id is rejected; ownership always comes from the session.

Update profile with `{"name":"Updated Name","email":"updated@example.test"}`. Email must remain unique; keeping your own email is allowed. Changing email clears its verification timestamp.

Optional password update requires the existing password:

```json
{
  "current_password": "StrongPassword123!",
  "password": "AnotherStrongPassword123!",
  "password_confirmation": "AnotherStrongPassword123!"
}
```

Passwords require 12–72 characters and confirmation for registration or update. The User model hashes passwords; controllers never return hashes.

Common results:

- 401: no valid authenticated session on a protected endpoint.
- 403: register/login lacked a trusted Origin/Referer, so no stateful session was established. Protected guest endpoints may return 401 first.
- 404: nonexistent product or another user's product; ownership cannot be inferred from the response.
- 419: missing, invalid, or stale CSRF token. Refresh /sanctum/csrf-cookie, retain cookies, and update X-XSRF-TOKEN.
- 422: validation errors in `errors`, including incorrect login credentials.
- 429: rate limit exceeded; wait for the Retry-After header.

Login allows 5 requests/minute per normalized email + IP, and 20/minute per IP across emails. Registration allows 3/minute per IP. Successful and invalid attempts both count.

## Manually verify CSRF and ownership

Laravel normally bypasses CSRF verification during automated tests. Verify the real HTTP flow in Postman:

1. Clear cookies, GET /sanctum/csrf-cookie, and register or log in with the current X-XSRF-TOKEN.
2. After login, copy the latest XSRF cookie to the header. POST a product with both cookies and the valid header: expect 201.
3. Keep session cookies and Origin, but remove X-XSRF-TOKEN; POST a product: expect JSON 419 and no new product.
4. Send an incorrect token: expect 419. Repeat missing-token checks for profile PATCH, product PATCH/PUT/DELETE, and logout.
5. Restore the current decoded cookie token: modifying requests should succeed.
6. POST logout with a valid token, then GET profile with the old session cookie: expect 401.
7. Sign in as user A and create a product. Log out, sign in as user B, and try listing, reading, modifying, and deleting A's product: list excludes it, and direct access returns 404. Restore user A to verify its data was preserved.

For token-specific manual checks, omit Sec-Fetch-Site. Laravel 13 can also accept a browser's same-origin fetch metadata as a request-forgery defense, so a request carrying `Sec-Fetch-Site: same-origin` may succeed without the token. Postman normally omits that header. No CSRF exclusions were added. See [Laravel CSRF protection](https://laravel.com/docs/13.x/csrf).

Postman requests create/update real data in your configured database. Use disposable users and products; don't delete existing business records.

## Automated tests and database safety

```powershell
php artisan config:clear
php artisan test --compact
```

Or run the API feature tests only:

```powershell
php artisan test --compact tests/Feature/ApiTest.php
```

phpunit.xml forces APP_ENV=testing, DB_CONNECTION=sqlite, DB_DATABASE=:memory:, an empty DB_URL, and array-backed sessions/cache. This is a separate, disposable database living in the test process; it never uses laravel_product_db. Tests/TestCase.php checks those database settings before migration traits can run and refuses to proceed if they differ. LazilyRefreshDatabase migrates only that isolated database. Do not repoint this suite at your working database.

Tests cover authentication and validation, password hashing, cookie/session continuity, session rotation, logout and old-cookie rejection, profile updates, product CRUD and defaults, pagination, product ownership, mass-assignment protection, and both login limits plus registration throttling.

The CSRF test replaces only Laravel's unit-test bypass method in a test-only subclass. It exercises the real cookie, session, encryption, and token-validation middleware. It checks valid tokens and rejected modifying requests. This supplements the manual Postman procedure.

Local verification: **48 automated tests passed with 269 assertions**. The feature suite uses SQLite; the Postman collections were also run successfully against the local MySQL-backed API. No working database reset was performed.

## Continuous integration

[Backend tests](.github/workflows/tests.yml) runs on pushes and pull requests, and can be started manually from GitHub Actions. It installs the locked Composer dependencies on PHP 8.4, validates Composer configuration, checks PHP formatting, and runs the isolated test suite. CI creates its own temporary application key and SQLite database; no application secrets or MySQL service are required.

The hosted workflow can only be verified after the repository is pushed. No CI badge is shown until there is a real repository and workflow result.

## Architecture and request flow

A product request follows:

```text
JSON + cookies + Origin + X-XSRF-TOKEN
  -> route and middleware
  -> FormRequest validation
  -> controller
  -> service interface / implementation
  -> repository
  -> Eloquent model and MySQL
  -> JSON resource
  -> JSON response
```

- **routes/api.php** maps URLs to controller actions. Protected routes use auth:sanctum.
- **bootstrap/app.php** enables Sanctum's stateful middleware and JSON errors for API and Sanctum paths.
- **RequireStatefulRequest** ensures these APIs have the cookie/session context selected by Sanctum. It does not replace authentication.
- **AuthController** registers users, verifies login credentials, regenerates the session after login/registration, and invalidates it at logout.
- **ProfileController** reads and updates the signed-in user's profile.
- **ProductController** coordinates product requests and responses.
- **app/Http/Requests** contains validation. Controllers use validated fields instead of trusting the entire JSON body.
- **app/Services** defines contracts; **app/Services/Impl** implements them. ProductServiceImpl resolves owned products before updates or deletion.
- **app/Repositories** contains Eloquent database operations. ProductRepository always starts from `$user->products()`, preventing cross-user access.
- **app/Models/User.php** defines the products relationship and password hash cast. **Product.php** defines its owner and decimal/integer casts.
- **app/Http/Resources** chooses the public JSON fields and keeps password hashes private.
- **AppServiceProvider** binds service interfaces to concrete implementations and defines rate limits. Laravel injects these dependencies automatically.
- **database/migrations** describes table structure, including the product owner foreign key. Eloquent works with rows; migrations manage schema.
- **tests/Feature/ApiTest.php** proves the HTTP behavior against an isolated database.

A concise interview explanation:

> “I built a Laravel API with session authentication through Sanctum. The client first gets CSRF cookies, then signs in using JSON. Laravel verifies the password hash and rotates the session ID. Protected requests use the session cookie, and modifying requests also pass CSRF checks. Controllers accept validated input, services coordinate operations, repositories query Eloquent, and resources shape JSON responses. Every product query is scoped to the current user's relationship. I test the API using a separate database so development data is preserved.”

Sanctum's cookie flow is documented in the [official authentication guide](https://laravel.com/docs/13.x/sanctum). Laravel's [HTTP testing guide](https://laravel.com/docs/13.x/http-tests) explains the normal CSRF test bypass.

## Publish to your own GitHub repository

Create an empty GitHub repository without adding a README, license, or gitignore there; this project already contains its README and ignore rules. Then run these commands locally, replacing the remote URL with your repository URL:

```powershell
git init -b main
git add .
git status
git diff --cached --stat
git commit -m "Build Laravel product API with Sanctum authentication and tests"
git remote add origin https://github.com/YOUR_USERNAME/YOUR_REPOSITORY.git
git push -u origin main
```

Before committing, inspect the staged file list. `.env`, Composer dependencies, runtime caches, logs, database files/exports, private keys, and local Postman environment/result exports are excluded. `.env.example`, `composer.lock`, migrations, tests, and both reusable Postman collections belong in the repository. Do not use `git add -f` to override the ignore rules for private files.

Repository description suggestion: **Laravel 13 backend API with Sanctum cookie authentication, user profiles, product ownership, and automated tests.**
