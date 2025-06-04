 # User Guide

 This guide provides step-by-step instructions for both administrators and client users of the Invoicer application.
 For installation, configuration, and developer documentation, please refer to [README.md](README.md).

 ---

 ## Authentication

 ### Default Credentials

 - **Username:** `admin`
 - **Password:** `admin`

 > **Note:** For security reasons, change the default admin password immediately after your first login. You can also set up another set of default credentials via config.local.php or envirnmental variables. These settings are effective at the db creation stage - i.e. first system run.

 ---

 ## Administrator Interface

 After logging in as an administrator, use the top navigation menu to manage users, customers, services, invoices, payments, reconciliation statements, and application settings.

 ### Users

 - Navigate to **Users** to view, add, edit, or disable user accounts.
 - To create a new user, click **Add User**, enter a unique username, set a password, and assign the **Admin** role if needed.
 - Assign customers to non-admin users so they can only access their own records. You need to assign a company to the user in this case.
 - In **Sessions** page, you can review auth sessions, ip addresses and User-agents of those users and revoke them.

 ### Customers

 - Go to **Customers** to add, edit, or delete customer profiles.
- Provide contact details, company information, tax identifiers (ID/VAT numbers), website, agreement, currency, and an optional logo (PNG/SVG/GIF up to 10 KB).

 ### Companies

 - Under **Companies**, configure your issuing companies (for the “From” section on invoices).
- Include company property, business details, bank information, currency, and an optional logo.

 ### Services

 - Use **Services** to define service items or products with descriptions, unit prices, and currencies. They will help you fill in recurring items to the invoice.

 ### Currencies

 - Manage the list of available currency codes under **Currencies**.

 ### Invoices

 - Click **Customers**, choose a customer, click **invoices**. In the opened page click:
   - **New invoice** to create a new invoice.
   - **Copy** to create a new invoice out of the existing one.
   - **View/Edit** to edit an existing one.
 - In the invoice form:
   1. Select an issuing company that matches the customer currency.
   2. Specify the invoice date and service period (if applicable).
   3. Choose service items and quantities; the totals are calculated automatically.
   4. Select a template from the dropdown to preview the invoice in HTML.
   5. Click **Save** to save the HTML version of the invoice.

 #### Invoice History & Signed PDFs

 - After saving an invoice, Invoicer automatically archives an HTML snapshot (history).
 - To upload a signed PDF version of the invoice:
   1. In the invoice form, use the **Upload Signed PDF** button.
   2. Choose a PDF file. The system validates the MIME type and renames the file with date, company slug, and invoice ID.
   3. Once uploaded, clients and admins can download the signed PDF.

 ---

 ## Payments

 - Go to **Payments** to view and assign payment records to customers.
 - Use the filters to search by text or date range and see totals per currency.

 ## Reconciliation

 - Under **Reconciliation**, generate statements summarizing invoices and payments for any date range.
 - Choose from available reconciliation templates (e.g., `reconciliation_en.html`, `reconciliation_pl.html`).

 ---

 ## Importing Historical PKO Statements (One‑Time)

 To import past PKO XML files into Invoicer:

1. Go to iPKO web page (Polish UI localization):
  - open history of the required account
  - select only incoming payments
  - make sure you see on your screen all the information for the full period selected
  - export as XML 
2. Use the **Payments** -> **Import Payments** page in the web UI to upload and process XML files.
3. Alternatively: 
  - place your XML files in the `../private/import/` directory (relative to your web root).
  - Run the import script from the command line:
    ```bash
    php admin/import_payments.php
    ```

 ---

 ## Automated Email Fetching (PKO)

 If configured, PKO can send notifications of payments via emails. Invoicer can fetch those payment notifications from a shared mailbox via Microsoft Graph and push the data to the db:

 1. Install Composer dependencies (`composer install`). A `private/config.local.php` file will be auto-created if missing. Edit it to configure your Azure settings.
 2. Ensure the shared mailbox has folders: `<AZURE_MAIL_FOLDER>`, `<AZURE_MAIL_FOLDER>-processed`, `<AZURE_MAIL_FOLDER>-skipped`, and `non-bank`.
 3. Run:
    ```bash
    php fetch_pko_messages.php
    ```
 4. Optionally, set up a cron job for periodic execution.

 ---

 ## Client Interface

 Client (non-admin) users see only their assigned invoices and payments:

 - After logging in, they are directed to **Invoices**, showing only their own records.
 - Clients can download signed PDF invoices if available.

 ---

 ## Logging Out

 - Click **Logout** in the navigation menu to end your session securely.

 ---

 For more technical details and developer utilities, please refer to [README.md](README.md).