# Laboratory Exercise 6

Product Management System built with a LavaLust JSON API, React/Vite frontend, and MySQL. The React app talks only to the API; database credentials remain on the backend.

## API routes

All success and error payloads are JSON. Except registration/login, send `Authorization: Bearer <access_token>`.

| Method | Path | Authentication | Purpose |
|---|---|---|---|
| POST | `/api/auth/register` | No | Create a user and return access/refresh tokens |
| POST | `/api/auth/login` | No | Sign in with email and password |
| POST | `/api/auth/refresh` | No | Rotate a refresh token and issue new tokens |
| GET | `/api/auth/me` | Bearer token | Return the current user |
| POST | `/api/auth/logout` | Bearer token | Revoke the supplied refresh token |
| GET | `/api/products` | Bearer token | List products |
| GET | `/api/products/{id}` | Bearer token | Read one product |
| POST | `/api/products` | Bearer token | Create a product |
| PUT/PATCH | `/api/products/{id}` | Bearer token | Update product fields |
| DELETE | `/api/products/{id}` | Bearer token | Delete a product |

Product JSON uses `product_name`, `description`, `price`, and `quantity`. Registration JSON uses `username`, `email`, and `password`; passwords must be at least 8 characters. The first account can be registered in the frontend. Every registered user can perform the lab CRUD operations.

## Local setup

Requirements: PHP 8+, `pdo_mysql`, Node.js/npm, and MySQL. Composer is not required by this LavaLust project; `composer install` fails because this repository has no `composer.json`.

1. Run `database/lab6.sql` against your Aiven MySQL service. It creates and selects a database named `lab6_db` before creating the tables. Set `DB_NAME=lab6_db`; if Aiven denies `CREATE DATABASE`, create `lab6_db` in the Aiven console first, then run the remaining SQL against it.
2. Copy `.env.example` to `.env` and set `DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, and `DB_NAME`. Local MySQL can leave `DB_SSL_CA` empty.
3. Copy `.env.example` to `.env`, then run `php lava jwt:generate` to write separate random `JWT_SECRET` and `REFRESH_TOKEN_KEY` values into `.env`. The command does not print the secrets. Copy each value into the Render Web Service environment settings manually; never commit `.env`.
4. Start the API from this directory: `php -S 127.0.0.1:8000 -t public`.
5. In `../frontend/` (sibling to the LavaLust backend), copy `.env.example` to `.env`, then run `npm install` and `npm run dev`.
6. Open the Vite URL (normally `http://localhost:5173`), create an account, then add products.

The PHP development server does not automatically load `.env`; export the values into the shell or set them in your local PHP/web-server environment. On PowerShell, set them for the current session with `$env:DB_HOST="..."` and equivalent assignments before starting PHP. The default CORS allowlist includes both `localhost` and `127.0.0.1` Vite origins.

## Aiven and Render deployment

1. Create an Aiven MySQL service and database. Download its CA certificate from the Aiven connection information.
2. Import `database/lab6.sql` into that database. It creates the `users`, `refresh_tokens`, and `products` tables required for signup, token revocation, and CRUD.
3. Create a Render **Web Service** from the LavaLust repository using the existing Dockerfile. Set `DB_DRIVER=mysql`, `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASSWORD`, `DB_NAME`, `DB_CHARSET=utf8mb4`, `DB_SSL_CA=/etc/secrets/ca.pem`, `JWT_SECRET`, `REFRESH_TOKEN_KEY`, and `API_ALLOWED_ORIGIN` in Render. Add the downloaded Aiven CA as a Render Secret File at `/etc/secrets/ca.pem`. The container entrypoint copies it to a PHP-readable runtime path before Apache starts. Keep secrets in Render and out of Git.
4. Create a Render **Static Site** from the separate frontend repository with the repository root as its root directory, build command `npm ci && npm run build`, and publish directory `dist`. Set `VITE_API_BASE_URL` to the Render API origin (no trailing slash). This Vite value is embedded at build time.
5. Set the backend `API_ALLOWED_ORIGIN` to the exact deployed frontend origin (for example `https://your-site.onrender.com`), then redeploy the API. For local development, the default is `http://localhost:5173`.
6. Register through the frontend. Use the deployed frontend to demonstrate login, list, add, edit, delete, and logout.

Render provides `PORT`; the Dockerfile's Apache service listens on port 80. Use Render's Dockerfile deployment defaults unless the service logs indicate a custom start command is needed.

## API smoke test

After setting `API` to your local or deployed API origin, register to receive a token, then use it on CRUD requests:

```sh
curl -X POST "$API/api/auth/register" -H "Content-Type: application/json" -d '{"username":"labuser","email":"labuser@example.com","password":"change-this-password"}'
curl "$API/api/products" -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
curl -X POST "$API/api/products" -H "Content-Type: application/json" -H "Authorization: Bearer YOUR_ACCESS_TOKEN" -d '{"product_name":"Notebook","description":"A5 dot grid","price":149.50,"quantity":12}'
```

The API tester can use the same routes and JSON bodies. Log in first and add the returned access token as a Bearer token for every `/api/products` request. Never paste database credentials into the frontend or API tester.