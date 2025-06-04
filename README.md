# Invoicer

Lightweight invoice generator using PHP backend and Vue 3 frontend.

## Features

- Manage customers (add/edit/delete; now include agreement, ID number, VAT number, website, currency, and optional logo (PNG/SVG/GIF up to 10KB)).
- Manage issuing companies (add/edit/delete company details for invoice dropdown; now include company property, supports currency (read-only once an invoice exists) and optional logo (PNG/SVG/GIF up to 10KB) shown on invoices).
- Manage service items (add/edit/delete descriptions, unit prices, and currency).
- Create, edit, and view invoices with live HTML preview and customizable templates.
- Auto-generate invoice numbers (first three uppercase letters of client name + invoice date, e.g. IMO-20250228).
- Invoice item can be "time-based", calculated from an hourly rate.
- Duplicate invoices and maintain historical HTML snapshots for each invoice.
- View and assign payments to customers, with sortable columns and dynamic filtering.
- Filter payments by text or date range and display totals by currency.
- Generate reconciliation statements (invoices & payments with summary balance) for any period in multiple languages via custom templates.
- Separate client (non-admin) interface to have access to payments and invoice information, assigned to the client user companies.
- Auto-importing payments from PKO email notifications and XML-based export of payments from iPKO web interface
- Responsive UI built with Bootstrap 5 and front-end powered by Vue 3.
- Simple data storage with SQLite.
- Upload a signed PDF per invoice (stored in private folder with date, company name, timestamp, and invoice ID in filename).
- Consult the [User Guide](USER_GUIDE.md) for detailed instructions on using Invoicer.

## Requirements

- PHP 8+ with PDO SQLite extension.
- (Optional) Composer (for email fetching dependencies).
- Web server (Apache, Nginx, etc.).

## Installation

1. Clone the repository into the web server root directory.
2. (Optional) Install PHP dependencies via Composer (if using automated email fetching):
   ```bash
   composer install
   ```
3. Ensure `../private` is writable by the web server - additional folders are auto-created there.
4. Configure application settings in `config.local.php` (see **Configuration**) or via environmental variables.
5. Access `login.php` in your browser and log in with default credentials (configurable):

   ```
   Username: admin
   Password: admin
   ```
6. After logging in, go to **Users** to create or manage additional accounts.

## Configuration

Instead of editing `config.php` in the web root, edit a private config file at `../private/config.local.php` with your credentials and settings This file is loaded before defaults in `config.php` and is auto-create at first run:

- `DEFAULT_ADMIN_USERNAME`, `DEFAULT_ADMIN_PASSWORD`: initial admin credentials.
- Azure/Graph OAuth2 settings: 

  ```php
  <?php
  define('AZURE_TENANT_ID', 'your-tenant-id');
  define('AZURE_CLIENT_ID', 'your-client-id');
  define('AZURE_CLIENT_SECRET', 'your-client-secret');
  define('AZURE_SHARED_MAILBOX_EMAIL', 'shared-mailbox@example.com');
  define('AZURE_REDIRECT_URI', 'https://your-domain/fetch_pko_messages.php');
  define('AZURE_TOKEN_FILE', __DIR__ . '/microsoft_graph_token.json');
  define('AZURE_MAIL_FOLDER', 'pko');
  ```

### Environment Variables

The application supports configuration via environment variables (which override defaults or `config.local.php`).

```bash
# Directory paths
ROOT_DIR_PATH        # Root application path
PRIVATE_DIR_PATH     # Private directory path for config, data, and templates (default: ../private)

# Admin credentials (initial seed)
DEFAULT_ADMIN_USERNAME   # Default admin username (default: admin)
DEFAULT_ADMIN_PASSWORD   # Default admin password (default: admin)

# Azure / Microsoft Graph OAuth2 settings for PKO email fetching
AZURE_TENANT_ID          # Azure Directory (tenant) ID (default: your-tenant-id)
AZURE_CLIENT_ID          # Azure Application (client) ID (default: your-client-id)
AZURE_CLIENT_SECRET      # Azure Client Secret (default: your-client-secret)
AZURE_SHARED_MAILBOX_EMAIL  # Shared mailbox email address (default: shared-mailbox@example.com)
AZURE_REDIRECT_URI       # OAuth Redirect URI for email scripts (default: https://your-domain/fetch_pko_messages.php)
AZURE_TOKEN_FILE         # Path to token JSON file (default: private/microsoft_graph_token.json)
AZURE_MAIL_FOLDER        # Mail folder to fetch messages from (default: folder-name)
```

## Usage

Use the navigation menu to access:

- **Customers**: manage customer profiles.
- **Invoices**: create, edit, preview, save invoices, and upload signed PDF versions.
- **Payments**: view and assign payment records.
- **Reconciliation**: generate reconciliation statements for any period.
- **Companies**: manage issuing company profiles.
- **Services**: define handy service items for fast invoice editing.
- **Users**: manage application users, view and revoke active authentication sessions

## PDF Generation

- Use the **Print / Save as PDF** button in the invoice form to invoke the browser’s Print dialog on the template-isolated iframe 

## Invoice Templates

Place invoice templates in the private part into `templates/` directory:

- Files starting with `invoice` (`invoice*.html`) are listed in the invoice form dropdown.
- Drop reconciliation templates named `reconciliation_*.html` to enable them on the Reconciliation page.
- Add your own templates to the `temaplates` folder in the `private` folder.

## Importing Historical PKO Statements

To import existing PKO XML statements (one-time):

1. Place your XML files in the `../private/import/` directory (relative to the project root).
2. Run the import script:
   ```bash
   php admin/import_payments.php
   ```
3. Alternatively, use the **Import Payments** page in the web UI to upload and import XML files (link at the Payments page).

## Automated Email Fetching (PKO)

To fetch and process payment notifications from a shared PKO mailbox:

1. Install Composer dependencies and configure Azure settings in `config.php`.
2. Ensure the shared mailbox has the following folders:
   - `<AZURE_MAIL_FOLDER>` (e.g. `pko`)
   - `<AZURE_MAIL_FOLDER>-processed`
   - `<AZURE_MAIL_FOLDER>-skipped`
   - `non-bank`
3. Open via web or run the fetch script:
   ```bash
   php fetch_pko_messages.php
   ```
4. (Optional) Set up a cron job to run this script periodically.

## HTTP API Endpoints

The JSON-based API endpoints used by the Vue frontend are available under the `api/` directory:

```
api/customers.php
api/companies.php
api/services.php
api/invoices.php
api/invoice_history.php
api/payments.php
api/reconciliation.php
api/users.php
api/sessions.php
```

## User Guide

Detailed end-user documentation is available in [USER_GUIDE.md](USER_GUIDE.md).

## Developer Utilities

- `db_browser.py`: curses-based SQLite viewer and editor

## Docker

A Dockerfile is provided to build and run the application in a Docker container.

### Build the image

```bash
docker build -t invoicer .
```

### Run the container

It is strongly recommended that you mount your host directory for `/var/www/private` in the container, as all sensitive data (configuration, database, OAuth tokens, uploaded PDFs) is stored there. **You will loose all the data on container removal otherwise**:

```bash
mkdir -p ~/invoicer_private
docker run -d \
  --name invoicer \
  -p 9000:9000 \
  -v ~/invoicer_private:/var/www/private \
  invoicer
```

The application will be available at http://localhost:9000.

### Running fetch_pko_messages job

To execute the `fetch_pko_messages` script on a running container from your host without opening a shell inside the container:

```bash
docker exec invoicer php /var/www/html/fetch_pko_messages.php
```

