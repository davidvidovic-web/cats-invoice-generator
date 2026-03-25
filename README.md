# Cats Invoice Generator

A WordPress plugin that automatically generates PDF invoices from WooCommerce orders and attaches them to order confirmation emails.

## Requirements

- WordPress 6.0+
- PHP 8.0+
- WooCommerce 7.0+
- Composer

## Installation

1. Clone or copy the `cats-invoice-generator` folder into `wp-content/plugins/`.
2. Install PHP dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```
3. Activate the plugin in **WordPress Admin > Plugins**.

## Development

Install all dependencies (including dev):

```bash
composer install
```

The `tmp/` directory is used at runtime to store generated PDF files before they are attached to emails. This folder is excluded from version control but must exist and be writable on the server.

## Structure

```
cats-invoice-generator/
├── assets/          # CSS and JS assets
├── includes/        # Core PHP classes
├── templates/       # Invoice HTML/PDF templates
├── tmp/             # Runtime-generated PDFs (git-ignored)
├── vendor/          # Composer dependencies (git-ignored)
├── composer.json    # Composer configuration
└── cats-invoice-generator.php  # Plugin entry point
```

## License

GPL-2.0-or-later
