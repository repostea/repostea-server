# Repostea

Repostea is a content aggregation platform built with Laravel for the backend API and Nuxt for the frontend.

## Description

Repostea allows users to share, vote, and comment on various types of content. The platform supports:

- Publishing different content types (text, links, media)
- Voting system for posts and comments
- Comments and nested replies
- Karma system and user levels
- Tags for content categorization

## Project Structure

The project is divided into two main parts:

- **Backend API (this repository)**: Developed with Laravel 12
- **Frontend**: Developed with Nuxt, located in the `../client` directory

## Requirements

- PHP 8.2 or higher
- Composer
- Node.js and npm/pnpm
- Database compatible with Laravel (MySQL, PostgreSQL, SQLite)

### Supported Platforms

✅ **Linux** | ✅ **macOS** | ✅ **Windows** | ✅ **WSL2**

*Git Hooks work seamlessly across all platforms*

## Installation

### Backend API

1. Clone this repository:
```bash
git clone https://github.com/repostea/repostea.git
cd repostea
```

2. Install dependencies (Git Hooks will be configured automatically):
```bash
composer install
```
> ✅ **Git Hooks Setup**: Quality checks (Laravel Pint, PHPStan, Tests) are now automatically configured!

3. Set up the environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure the database in the `.env` file

5. Run migrations:
```bash
php artisan migrate
```

6. Optionally, you can load sample data:
```bash
php artisan db:seed
```

### Frontend (Nuxt)

1. Navigate to the client directory:
```bash
cd ../client
```

2. Install dependencies:
```bash
pnpm install
```

3. Set up the environment:
```bash
cp .env.example .env
```

## Development Execution

To facilitate development, you can use the `composer dev` command which will simultaneously start:
- The Laravel API server
- The Laravel queue worker
- Vite for asset compilation
- The Nuxt development server

```bash
cd server  # Make sure you're in the server directory
composer dev
```

> **Important**: For `composer dev` to work correctly, the client must be located at `../client` relative to the server directory.

## API

The API is structured following RESTful best practices and uses Laravel Sanctum for authentication.

### Main endpoints

- `/api/v1/posts`: Post management
- `/api/v1/comments`: Comment management
- `/api/v1/tags`: Tag management
- `/api/v1/users`: User management

See the complete API documentation for more details.

## Key Features

### Voting System

The platform features an advanced voting system:

- For posts: Only positive votes
- For comments: Positive and negative votes with specific types (didactic, interesting, elaborate, funny, incomplete, irrelevant, false, out of place)

### Karma System

Users earn karma points based on their activity:
- Receiving positive votes on their posts and comments
- Publishing content regularly
- Maintaining a daily activity streak

### Social Features

- Saved lists (favorites, read later, custom)
- Tag following
- Badges based on karma level

## Code Quality & Tests

### Automatic Quality Checks ✨

This project has **pre-commit hooks** that automatically run:
- **Laravel Pint** (code formatting)
- **PHPStan** (static analysis)
- **Pest Tests** (core functionality)

**Commits are blocked if quality checks fail!**

### Manual Commands

```bash
# Run all quality checks
composer quality

# Fix code formatting
composer quality-fix

# Run specific tools
./vendor/bin/pint              # Format code
./vendor/bin/phpstan analyse   # Static analysis
./vendor/bin/pest              # Run all tests
```

### For New Team Members

Quality checks are **automatically configured** when you run `composer install`. No additional setup needed! 🎉

> See `DEVELOPMENT.md` for detailed development guidelines.

## 💬 Using Repostea?

We'd love to hear from you! If you're using Repostea for your project, please [open an issue](https://github.com/repostea/repostea/issues/new?labels=showcase&title=Showcase:%20[Your%20Project%20Name]) to let us know. It helps us understand how the project is being used and motivates continued development.

## License

[GPL-3.0](LICENSE)
