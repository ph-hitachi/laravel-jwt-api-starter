# 🚀 Laravel JWT API Starter

Welcome to the **Laravel JWT API Starter**, a robust, scalable, and secure API boilerplate built with **Laravel 11** and secured via **JWT Authentication**.

This boilerplate is designed with performance, security, and developer experience in mind, providing a solid foundation for any modern web or mobile application backend.

---

## 📌 Table of Contents
- [Endpoints Summary](#-endpoints-summary)
- [Rate Limiting & Security Policies](#-rate-limiting--security-policies)
- [ETag Caching](#-etag-caching)
- [JWT Security Mechanisms](#jwt-security-mechanisms)
- [Standard Error Responses](#-standard-error-responses)
- [Installation & Quick Start](#-installation--quick-start)

---

## 🛣️ Endpoints Summary

### Authentication

| Method | Endpoint | Description |
|:---|:---|:---|
| `POST` | `/api/auth/authenticate` | Authenticate a user using social OAuth provider and token. |
| `POST` | `/api/auth/logout` | Revoke the user's current JWT access token and log them out. |
| `POST` | `/api/auth/refresh` | Refresh the current authentication token. |
| `POST` | `/api/auth/register` | Complete onboarding for the currently authenticated user. |

### User/Profile

| Method | Endpoint | Description |
|:---|:---|:---|
| `GET` | `/api/user/me` | Retrieve the authenticated user's profile details. |
| `PUT` | `/api/user/profile` | Update the authenticated user's profile information. |
| `PATCH` | `/api/user/avatar` | Upload an avatar file to update the profile. |
| `PATCH` | `/api/user/settings` | Update user-specific settings. |
| `GET` | `/api/user/username` | Check if a username is valid and available. |
| `PUT` | `/api/user/password` | Update the authenticated user's password. |

### Admin/Users

| Method | Endpoint | Description |
|:---|:---|:---|
| `GET` | `/api/admin/users` | List all registered users with pagination. |
| `GET` | `/api/admin/users/{user}` | Retrieve detailed profile information of a specific user. |
| `DELETE` | `/api/admin/users/{user}` | Permanently delete a user account. |
| `PATCH` | `/api/admin/users/{user}/activate` | Reactivate a deactivated user account. |
| `PATCH` | `/api/admin/users/{user}/deactivate` | Deactivate a user account. |
| `PATCH` | `/api/admin/users/{user}/role` | Assign a new system role to a user account. |

---

## 🛡️ Rate Limiting & Security Policies

The API applies a strict rate limit of **60 requests per minute** per IP Address or Authenticated User ID.
When the limit is reached, the API returns a `429 Too Many Requests` status code.

### HTTP Security Headers
Every response includes strict security headers:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: no-referrer`
- `Content-Security-Policy: default-src 'none'; script-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'none';`

### ⚡ ETag Caching
The API implements ETag-based caching (using HTTP conditional requests) for optimized client performance and reduced server bandwidth:
- **ETag Generation**: Every response includes an `ETag` header representing the hash of the payload.
- **Conditional Requests**: Clients send the ETag value in the `If-None-Match` header on subsequent requests.
- **304 Not Modified**: If the resource hasn't changed, the server responds with a `304 Not Modified` status code and an empty body, indicating the client should use its cached copy.

### JWT Security Mechanisms
- **Token Expiration**: JWT tokens have an expiration time.
- **Token Refresh**: Uses a refresh token mechanism to obtain new access tokens.
- **Token Blacklisting**: Expired or logged-out tokens are blacklisted to prevent reuse.
- **Statelessness**: JWTs do not require server-side storage.

---

## 🚫 Standard Error Responses

The API uses a standardized error format for all exceptions:

```json
{
    "error_code": "UNAUTHENTICATED",
    "exception_type": "AuthenticationException",
    "message": "Unauthenticated."
}
```

### Global Error Codes
| Error Code | HTTP Status | Description |
|---|---|---|
| `TOKEN_COULD_NOT_PARSE` | `400` | The token could not be parsed. |
| `BAD_REQUEST` | `400` | The request could not be understood or was malformed. |
| `TOKEN_BLACKLISTED` | `401` | The token has been blacklisted. |
| `TOKEN_EXPIRED` | `401` | The token has expired. |
| `UNAUTHENTICATED` | `401` | Not authenticated. Please provide a valid token. |
| `FORBIDDEN` | `403` | No permission to perform this action. |
| `NOT_FOUND` | `404` | Requesting an endpoint or record that does not exist. |
| `METHOD_NOT_ALLOWED` | `405` | Invalid HTTP method. |
| `VALIDATION_ERROR` | `422` | The given data was invalid. |
| `TOO_MANY_REQUESTS` | `429` | Too many requests. |
| `INTERNAL_ERROR` | `500` | Something went wrong on the server. |

---

## 🛠️ Installation & Quick Start

Follow these steps to set up and run the application locally on your machine.

### Step 1: Clone and Install Dependencies
Navigate into the `src` directory and install the required packages:
```bash
# Install PHP dependencies
composer install

# Install and build frontend assets (required for Scramble API Docs rendering)
npm install
npm run build
```

### Step 2: Configure Environment Variables
Copy the template environment file to create your local configuration:
```bash
cp .env.example .env
```

### Step 3: Generate Application Keys
Generate the standard Laravel application encryption key and the JWT authentication secret:
```bash
# Generate the application key
php artisan key:generate

# Generate the JWT secret key
php artisan jwt:secret
```

### Step 4: Run Database Migrations
Set up your database (default local setup uses SQLite, but you can configure MySQL/PostgreSQL in `.env` if desired):
```bash
# For default SQLite usage, ensure the database file exists first
touch database/database.sqlite

# Run all migrations and seed the database with initial dummy data
php artisan migrate:fresh --seed
```

### Step 5: Serve the Application
Start the local Laravel development server:
```bash
php artisan serve
```
Once started, the API will be accessible locally at `http://127.0.0.1:8000`. You can test the endpoints or access the built-in API developer tools from there.