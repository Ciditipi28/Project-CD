# CarinderEase — Family business edition

A complete local PHP and MySQL application for a single carinderia: changing daily menus, counter sales, fixed combos, dine-in rice refills, ingredient inventory, purchases, expenses, cash reconciliation, and reports.

One family business, one **Owner / Admin**, and optional Cashier or Kitchen Staff accounts. The Owner is the administrator: there is no separate System Admin, public registration, OTP, email verification, or business approval. The redesigned login stays. Accounts use a username and password, with a 30-minute inactivity timeout. Only the Owner can create staff accounts and reset their passwords.

## Already have records? Read this first

Do not overwrite your old installation or point this edition at the old database. Keep both until you have checked the imported records.

1. Export your old database using phpMyAdmin and keep a copy of the old application folder.
2. Close every open cash drawer in the old application and pause sales while switching.
3. Extract the new **CarinderEaseFamily** folder into `C:\xampp\htdocs\` alongside your old folder. Start Apache and MySQL.
4. Check `config/database.php`. The destination must be a NEW empty database, normally `carinderease_family_db`. Source and destination must be on the same server and accessible with the configured database credentials.
5. Open `http://localhost/CarinderEaseFamily/import-family.php` on the server computer **before running setup.php**. Enter the OLD database name (normally `carinderease_db`) and your existing business Owner username/password. A platform System Admin account cannot import a business.
6. After import, sign in with that Owner's existing credentials. Check sales totals, stock, menu and users before using the new counter.

The importer supports the supplied original and Admin/OTP editions. It reads the source without changing it. From the multi-business edition, it copies only the selected Owner's business; it does not copy other businesses, platform admins or OTP tokens. The selected Owner becomes the sole Owner/Admin. Other former Owners in that business become inactive Cashiers so history retains its user links; review their access before activation. Existing Cashier/Staff permissions remain. Imported records include sales, payments, stock and audit history.

Import refuses a nonempty destination, open source drawers, and inconsistent payment records. A failed record-copy transaction rolls back. Do not use this importer for an unrelated or manually redesigned schema. The old application remains available as a fallback until you begin recording new activity in the new one; after that, the two databases diverge.

## Fresh installation on Windows with XAMPP

1. Extract this ZIP. Place the **CarinderEaseFamily** folder in `C:\xampp\htdocs\`. Your main file should be `C:\xampp\htdocs\CarinderEaseFamily\index.php`.
2. Open XAMPP Control Panel. Start **Apache** and **MySQL**.
3. Open `http://localhost/CarinderEaseFamily/setup.php` in Chrome, Edge, or Firefox on the same computer.
4. Enter your name, owner username, and a password of 10–72 characters. No default account or password is installed.
5. Leave **sample dishes, today’s menu, and ingredient stock** checked for your first classroom demonstration. These are clearly demonstration records; no fake sales are inserted. Uncheck it for an empty operational installation.
6. Click **Create my workspace**. Setup creates `carinderease_family_db`, imports the 20-table schema, creates your owner account, and locks itself once an account exists.
7. Sign in. Open **Cash drawer**, enter an opening float, and click **Open drawer**. Then open **Counter POS** and make your first sale.

**You do not need to import SQL manually when using setup.php.** For manual phpMyAdmin installation into the new database, use `database/carinderease_family.sql`, then open setup.php to create the Owner. Never import this schema over existing business records. If your MySQL account has a password or uses a different port, edit `config/database.php` before setup. The supplied values match a standard local XAMPP installation: host `127.0.0.1`, port `3306`, username `root`, empty password. The application password you create is separate from the database password.

Use the application through `http://localhost/...`, not by opening PHP files directly. All scripts, styles, icons, and illustrations are included locally. No CDN, npm build, Composer installation, or internet connection is required to run the app.

## Runtime requirements

- PHP 8.1 or newer, with PDO, pdo_mysql, mbstring, and session support.
- MySQL 8.0+ or MariaDB 10.4+ with InnoDB. XAMPP commonly uses MariaDB behind its MySQL control-panel button. The application was runtime-tested with PHP 8.3.6 and MariaDB 10.11.14; a Windows XAMPP launch still needs your local installation.
- Apache with `.htaccess` support, as supplied by XAMPP.
- Modern Chrome, Edge, or Firefox.

If your instructor requires Oracle MySQL specifically, configure a MySQL server in `config/database.php`; do not claim that MariaDB and Oracle MySQL are the same product.

## First demonstration

The starter catalog contains 12 products, including **Afritada Unli Meal at ₱105**. Open a drawer with ₱500. Sell two dine-in meals, receive ₱500, and verify a total of ₱210 and change of ₱290. Rice refills will show two separate meal cards. Record a refill, then close one visit. Food portion counts decrease, but the original sale remains ₱210.

Next, receive 2.5 kg of raw rice at ₱50 per kg, paid from the drawer. Post the ₱125 receipt. Record a ₱20 transport expense paid from the drawer. Expected drawer cash is **₱565 = ₱500 + ₱210 − ₱125 − ₱20**. Count the actual money and close the session. The system records any variance instead of forcing a match.

For a second business day, add fresh entries to **Today’s menu**. Yesterday’s quantities and prices remain historical. On the same day, enter **cumulative prepared and wasted quantities**, not just a new batch amount.

## What is included

- One Owner / Admin, plus optional Cashier and Staff roles; hashed passwords; CSRF protection; login throttling; protected pages; account deactivation and owner-controlled password reset.
- Responsive overview with real database statistics, seven-day sales chart, low-stock alerts, and current menu.
- Product and category CRUD, daily menu prices and quantities, fixed combo builder, and automatic availability checks.
- Interactive POS with search, category tabs, quantity controls, exact-cash shortcuts, change calculation, duplicate protection, and printable transaction slips.
- One refill entitlement per eligible meal, one-serving refill action, and visit closing.
- Ingredient units and catalogs, supplier management, raw stock ledger, kitchen issues, waste, and owner corrections.
- Draft stock receipts, editable lines, atomic posting, and clear drawer/owner payment source.
- Operating expenses and one shared cash drawer with individual cashier accountability.
- Eight reports with date filters, printing, and CSV exports: daily sales, product sales, prepared food/waste, current stock, supplier purchases, expenses, cash reconciliation, and rice refills.
- Search and paginated lists, profile updates, activity log, and a built-in operating guide.
- `database/schema.sql`, field dictionary, ERD sources and diagrams, project proposal, flowchart, use case documentation, and automated tests.

There are 24 authenticated page routes, plus login and setup. Detail pages are real functional views, not empty placeholders.

## Permissions

| Activity | Owner | Cashier | Staff |
|---|---|---|---|
| Manage users, catalog, combo definitions, suppliers and categories | Yes | No | No |
| Edit daily menu portions and availability | Yes | No | Yes |
| Set selling prices | Yes | No | No |
| Process sales and inspect receipts | All sales | Own sales | No |
| Open and close a cash session | Own session | Own session | No |
| Record eligible rice refills and close visits | Yes | Yes | Yes |
| View raw stock, receive supplies, issue ingredients and record waste | Yes | No | Yes |
| Record opening stock or inventory corrections | Yes | No | No |
| Record expenses and view business reports | Yes | No | No |
| View activity history | Yes | No | No |
| Update own profile and password | Yes | Yes | Yes |

Only one cash drawer can be open. If the cashier opens it, the owner cannot enter sales on that cashier’s behalf. The owner and authorized staff may record drawer-paid purchases against the active shared drawer. Only its assigned user closes it.

## Important operating rules

**Two stock layers:** raw ingredient stock changes when supplies arrive or staff issue ingredients to the kitchen. Prepared-food portions change when staff record batches, sales allocate food, free rice is served, or prepared food is wasted. Sales do not deduct raw ingredients a second time. There is no recipe costing engine in this version.

**Combos:** create a product of type Combo, define its components, then offer it on today’s menu. Each component must also be listed and available that day. Combos have no separate prepared quantity. For unli-rice combos, include the configured rice product as a component.

**Unli-rice:** a standalone unli dish includes one initial rice portion. A combo gets its initial rice through its components. Only dine-in purchases receive refill eligibility; eligibility ends when the visit closes or the business date changes. One refill action adds one serving. Refill revenue is zero.

**Money:** the server uses database prices and integer centavos for sale arithmetic. The browser total is only a preview. Purchase line totals are rounded to two decimals before summing. Net cash retained, not cash tendered, is used for sales and drawer reports.

**History:** completed sales, payments, serving events, posted receipts, posted expenses, and closed cash sessions are not editable or deletable through the app. Master records can be deactivated. Unused records and draft receipt lines can be deleted. Inventory corrections create new ledger entries and audit records.

**Single-branch writes:** short write transactions use one database advisory lock plus transactions. This intentionally serializes counter, kitchen, and cash writes for a small carinderia, preventing races between checkout, refills, waste, and drawer closing. Duplicate checkout, receipt, stock, and expense tokens prevent repeated submissions from repeating financial/stock effects.

## Scope and limitations

This release completes the agreed cash-only, single-branch project scope. It does not include post-payment refunds/reversals, utang, online ordering, delivery, electronic payments, supplier credit, payroll, mix-and-match combos, tax invoicing, or accounting profit. Check a financial record carefully before posting. A printed slip is a classroom transaction record, not a claim of official invoicing compliance.

A master catalog item’s standard price does not overwrite a daily listing automatically. Change today’s price on Today’s menu. A historical sale retains the price originally charged. Corrections to raw stock do not rewrite the purchase value or reconcile a cash mistake.

The system is intended for local XAMPP use. Public hosting requires deployment-specific HTTPS, database credentials, access controls, backups, and server configuration. Do not expose XAMPP’s default root database account to the internet.

## Project structure

| Location | Purpose |
|---|---|
| `index.php` | Front controller for authenticated pages and actions |
| `login.php`, `setup.php` | Sign-in and one-time installation |
| `config/database.php` | Database connection settings |
| `app/core.php` | PDO, sessions, permissions, CSRF, validation, transaction wrapper |
| `app/services.php` | Checkout, serving, stock, menu and cash business rules |
| `app/actions.php` | Authorized POST actions |
| `app/masters.php` | Validated catalog/account CRUD definitions |
| `app/ui.php`, `app/views_*.php` | Shared layout and functional screens |
| `app/reports.php` | Database report queries and profile/guide views |
| `assets/` | Offline CSS, JavaScript and vector food illustrations |
| `database/schema.sql` | 20-table database definition and role seeds |
| `docs/` | Proposal, diagrams, database dictionary and test evidence |
| `tests/` | Optional developer regression tests |
| `storage/` | Local login rate-limit files; not publicly served |

Reuse is intentional: 24 routes share layout, field, table, and permission helpers. A functional page does not require its own duplicate PHP template.

## Back up and restore

1. In phpMyAdmin, select `carinderease_family_db`, choose **Export**, use SQL format, and download the export to a safe folder outside the public web directory.
2. Also keep a copy of the application folder and your database configuration. Protect exports because they contain business records and account password hashes.
3. To restore, create a new empty database in phpMyAdmin and import your SQL backup. Change the database name in `config/database.php` to the restored database.
4. Sign in with the restored accounts. Do not rerun the installer over a populated database.

## Troubleshooting

| Problem | Check |
|---|---|
| PHP code displays or a file downloads | Use the localhost URL through Apache, not a file path |
| Database connection fails | Start MySQL, check credentials and port in config/database.php |
| Setup says database is incomplete | Use a fresh empty database name; setup does not erase existing data |
| Login says storage is not writable | Allow your local web-server user to write to the storage folder |
| Cashier cannot sell | Open that account’s own cash session; another user may already have the drawer open |
| Combo unavailable | Verify all components, their daily listings, and enough portions for the entire cart |
| Menu is empty tomorrow | Add the new day’s menu; daily records are deliberately separate |
| Delete fails | The record is already referenced; deactivate it instead |
| Owner forgets the password | Back up the DB and use an authorized local database recovery procedure; there is no public password reset or second owner |
| Apache uses another port | Include it in the URL, for example http://localhost:8080/CarinderEaseFamily/ |

## Optional developer checks

Normal app use does not require Node or any testing tools.

For integration tests, create a NEW disposable database name beginning with `ce_test_`, set `CE_DB_NAME` and connection environment variables, and run `php tests/integration.php`. It refuses ordinary database names and refuses an existing user dataset. It installs the schema and test-only accounts. Never run it against your real records.

For browser checks, install Playwright in a separate developer environment, serve the app against a fresh disposable database, and run `node tests/browser.cjs`. Set `CE_BASE_URL` as needed. Test account passwords exist only inside the disposable test setup, never in a normal installation.

A development-server alternative is `php -S 127.0.0.1:8080 -t . router.php` from this folder. Use the supplied router; the built-in server otherwise ignores Apache `.htaccess` protections. XAMPP remains the intended final-project environment.
