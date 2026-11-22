<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Official UI Theme

`public/courier-v1.0.0` is the **official UI theme** for Alabiq. All frontend work must use it as the source of truth.

- Stylesheet: `public/courier-v1.0.0/build/assets/css/theme.min.css`
- RTL stylesheet: `public/courier-v1.0.0/build/assets/css/theme-rtl.min.css`
- Images and illustrations: `public/courier-v1.0.0/build/assets/img/`
- Typography: Raleway, loaded in `resources/views/app.blade.php`
- App-specific overrides: `resources/css/app.css` only

Do not introduce Tailwind or a separate Bootstrap theme. Build UI with Courier/Bootstrap utility classes (`bg-100`, `bg-900`, `text-primary`, `btn-danger`, `card-span`, etc.) and keep Alabiq branding assets in `public/images/`.

## Product Model: One System, One Restaurant

Each Alabiq deployment serves exactly **one** restaurant — there is no restaurant directory, search, or post-login restaurant picker:

- Guests and customers land directly on the restaurant's storefront/menu (`/` redirects to `/restaurants/{slug}`).
- Restaurant owners log in straight into their dashboard. On first login they complete a one-time setup (`/restaurant/setup`) that creates the deployment's single restaurant; until then all restaurant dashboard pages redirect to setup.
- Public registration offers customer and rider accounts only. Restaurant owner accounts are provisioned by the deployer (super admin user management or seeders) — there is no self-serve restaurant signup, and no super-admin restaurant approval flow.
- If the database may ever hold more than one restaurant record, pin the deployment's restaurant with `ALABIQ_RESTAURANT_ID` in `.env` (see `Restaurant::single()`).

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
