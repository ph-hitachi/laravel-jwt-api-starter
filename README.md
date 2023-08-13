<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Laravel 10 REST API Authentication with JWT

This project is a starter template for building a Laravel 10 REST API with JWT (JSON Web Token) authentication. It provides endpoints for user login, retrieving user information, refreshing tokens, and logging out. The project is intended to serve as a boilerplate and has been uploaded to GitHub as a public repository.

## Table of Contents

- [Installation](#installation)
- [Endpoints](#endpoints)
- [Error Handling](#error-handling)
- [Security](#security)
- [License](#license)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/ph-hitachi/laravel-api-jwt-starter.git
    ```

2. Navigate to the project directory:
    ```bash
    cd laravel-api-jwt-starter
    ```
3. Install the required dependencies using Composer::

   ```bash
   composer install
    ```
4. Set up your environment variables by copying the `.env.example` file:
   ```bash
   cp .env.example .env
    ```

5. Generate a new application key:
    ```bash
    php artisan key:generate
    ```
6. Configure your database connection in the `.env` file.
7. Run the migrations:
    ```bash
    php artisan migrate
    ```
8. Generate a JWT secret key:

   ```bash
   php artisan jwt:secret
    ```
## Endpoints

| Method   |      Endpoint      |  Description                             |
|----------|--------------------|------------------------------------------|
| POST     | /api/login         | User login and token generation          |
| GET      | /api/me            | Get current user information             |
| POST     | /api/refresh       | Refresh access token                     |
| POST     | /api/logout	    | Invalidate the current token and log out |

#### User Login
Use this endpoint to authenticate a user and generate an access token. Provide the user's email and password in the request body. If the credentials are valid, the API will respond with the user's information along with an access token that can be used for subsequent requests.

##### Request Body:
```http
POST /api/login HTTP/1.1
Host: localhost:8000
Content-Type: application/json

{
    "email": "johndoe@example.com",
    "password": "password@123"
}
```

##### Response:
```json
{
    "user": {
        "id": 1,
        "name": "john doe",
        "email": "johndoe@example.com",
        "email_verified_at": null,
        "created_at": "2023-07-24T05:10:20.000000Z",
        "updated_at": "2023-07-24T05:10:20.000000Z"
    },
    "authorization": {
        "access_token": "{{ token }}",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

#### Get Current Authenticated User Information
Use this endpoint to fetch information about the currently authenticated user. Include the access token in the request headers. The API will respond with the user's details.

##### Request Body:
```http
GET /api/me HTTP/1.1
Host: localhost:8000
Content-Type: application/json
Authorization: Bearer <token>
```

##### Response:
```json
{
    "id": 1,
    "name": "john doe",
    "email": "johndoe@example.com",
    "email_verified_at": null,
    "created_at": "2023-07-24T05:10:20.000000Z",
    "updated_at": "2023-07-24T05:10:20.000000Z"
}
```

#### Generate Refresh token
Use this endpoint to refresh an expired access token. Include the current access token in the request headers. The API will respond with a new access token that can be used for authentication. Token refresh is useful to maintain a user's session without requiring frequent logins.

##### Request Body:
```http
POST /api/refresh HTTP/1.1
Host: localhost:8000
Content-Type: application/json
Authorization: Bearer <token>
```

##### Response:
```json
{
    "user": {
        "id": 1,
        "name": "john doe",
        "email": "johndoe@example.com",
        "email_verified_at": null,
        "created_at": "2023-07-24T05:10:20.000000Z",
        "updated_at": "2023-07-24T05:10:20.000000Z"
    },
    "authorization": {
        "access_token": "{{ token }}",
        "token_type": "bearer",
        "expires_in": 3600
    }
}
```

#### Logout
Use this endpoint to invalidate the current access token and log out the user. Include the access token in the request headers. After logging out, the token will no longer be valid for making authenticated requests.

##### Request Body:
```http
POST /api/logout HTTP/1.1
Host: localhost:8000
Content-Type: application/json
Authorization: Bearer <token>
```

##### Response:
```json
{
    "success": true
}
```

## Error Handling

In case of errors, the API will return responses in JSON format. The following table lists the fields you might encounter in error responses along with their descriptions:

| Field            | Description                                      |
| ---------------- | ------------------------------------------------ |
| error            | The top-level container for error information    |
| error.message    | A human-readable description of the error        |
| error.type       | A categorization of the error type              |
| error.code       | A specific code or identifier for the error     |
| error.trace_id   | A unique identifier for tracing the error        |
| error.validation | An object containing field-specific errors      |

### Field Descriptions

- **error.message**: This field provides a human-readable description of the error that occurred. It can help you identify the nature of the problem quickly.

- **error.type**: This field categorizes the type of error that occurred. It can be helpful for programmatically handling different types of errors, such as authentication, validation, or server errors.

- **error.code**: An error-specific code or identifier that can be used for easier identification and handling of errors in your codebase.

- **error.trace_id**: A unique identifier associated with the error. This trace ID can be helpful for diagnosing issues and troubleshooting, as it can be used to locate relevant logs and traces.

- **error.validation**: If the error is related to input validation, this object may contain detailed information about which fields failed validation and why. It helps pinpoint the exact issues with the submitted data.

It's important to parse and handle these error responses appropriately in your application to provide meaningful feedback to users and to aid in debugging and improving the system.

Here's an example of an error response:

```json
{
    "error": {
        "message": "The provided token is invalid, has expired, or has been blacklisted.",
        "type": "OAuthException",
        "code": "token_could_not_verified",
        "trace_id": "qOoyG0cl3R8B4x9j"
    }
}
```

**Remember** that the specific field names and structures of error responses might vary based on the API implementation.

```
Feel free to adjust the descriptions and add any other relevant information to suit your project's requirements.
```

## Security

### JWT Security Mechanisms

JSON Web Tokens (JWTs) are a secure way to authenticate users in stateless environments. Here's how the security is maintained:

- **Token Expiration**: JWT tokens have an expiration time (expiry). After a token expires, it's no longer valid for authentication. This ensures that if a token is intercepted, it can only be used for a limited time.

- **Token Refresh**: When an access token expires, the user can use the refresh token to obtain a new access token without having to re-enter their credentials. The refresh token is typically long-lived and is used to generate new access tokens.

- **Token Blacklisting**: While expired tokens can't be used for authentication, they can be blacklisted to ensure that even if an attacker gets hold of a valid token, it won't work after being blacklisted. Laravel has a built-in blacklist mechanism for this purpose.

- **Statelessness**: JWTs are self-contained and do not require server-side storage. This makes JWT-based authentication suitable for scalable and distributed systems.

### Handling Expired Tokens and Refreshing

When an access token expires, the user can use the refresh token to request a new access token. This is done by making a request to the `/api/refresh` endpoint, providing the expired token in the authorization header. The API then responds with a new access token, extending the user's session.

### Blacklisting Tokens

If a token is compromised or a user logs out, their tokens can be blacklisted. Blacklisting means that even if an expired token is used for refresh, the new access token won't be generated. Laravel's built-in mechanism takes care of blacklisting tokens to enhance security.

It's important to implement proper token handling and security practices to protect user data and maintain system integrity.


## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
