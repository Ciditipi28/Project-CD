# Family edition validation — 25 October 2026

Tested with PHP 8.3.6, MariaDB 10.11.14 and Chromium headless 131 on Linux. Windows XAMPP and Oracle MySQL were not executed in this environment.

| Suite | Passed checks | Coverage |
|---|---:|---|
| integration.php | 53 | Counter sales, menu/portions, combos, refills, raw stock, purchases, expenses, drawer reconciliation and access rules |
| family_rules.php | 4 | Application and database reject a second Owner; Owner cannot demote itself; direct Staff creation |
| import_v2.php | 14 | Selected-business isolation, Owner credentials, inactive former Owners, reconstructed payments, stock, source unchanged and repeat-import refusal |
| import_v1.php | 6 | Open-drawer refusal, invalid payment rejection, full copy rollback, original Owner/payment preservation and source unchanged |
| browser.cjs | 137 | Setup, login, role screens, counter workflow, management forms, reports, profile/password change, disabled public registration and no JavaScript errors |

Total: 214 automated assertions passed. Login and responsive screens were captured for internal visual review. No test credentials or test datasets are installed by normal setup. These results are regression evidence, not a guarantee that every possible deployment or workflow is error-free.

Tests require disposable databases beginning with ce_test_. See README for basic execution. Import tests additionally create a source fixture database with a _source suffix. Never run tests against business data.
