# CarinderEase Workflows and Use Cases

Family edition update: one Owner account also serves as Admin. The Owner creates optional Cashier and Staff accounts directly. Public registration, OTP and business approval are not part of this edition.

## System flowchart

```mermaid
flowchart TD
 A[Log in] --> B{Valid active account?}
 B -- No --> A
 B -- Yes --> C[Role dashboard]
 C --> D[Publish daily menu and prepared portions]
 D --> E[Open cashier session]
 E --> F[Select customer order]
 F --> G{All portions available?}
 G -- No --> F
 G -- Yes --> H[Enter cash received]
 H --> I{Enough cash?}
 I -- No --> H
 I -- Yes --> J[Save sale payment and serving records atomically]
 J --> K[Print slip and serve meal]
 K --> L{Eligible rice refill?}
 L -- Yes --> M[Check entitlement and rice stock]
 M --> N[Record one free rice serving]
 N --> L
 L -- No --> O[Close customer visit]
 O --> P[Record waste and expenses]
 P --> Q[Count cash and close drawer]
```

## Use case diagram source

The following is UML use-case notation in PlantUML. It shows direct software actors. Customers and suppliers do not log in and therefore are not direct actors in this version.

```plantuml
@startuml
left to right direction
actor Owner
actor Cashier
actor Staff
rectangle CarinderEase {
  usecase "Log in and manage own profile" as Login
  usecase "Manage accounts and catalogs" as Catalog
  usecase "Configure combos and prices" as Combo
  usecase "Publish daily menu and portions" as Menu
  usecase "Process counter sale" as Sale
  usecase "Check food availability" as Availability
  usecase "Record payment and servings" as Payment
  usecase "Record eligible rice refill" as Refill
  usecase "Close customer visit" as Visit
  usecase "Receive ingredient supplies" as Receive
  usecase "Record kitchen use and waste" as Stock
  usecase "Approve inventory adjustment" as Adjust
  usecase "Record operating expense" as Expense
  usecase "Open and close own cash session" as Cash
  usecase "Review reports and audit log" as Reports
}
Owner -- Login
Cashier -- Login
Staff -- Login
Owner -- Catalog
Owner -- Combo
Owner -- Menu
Staff -- Menu
Owner -- Sale
Cashier -- Sale
Sale ..> Availability : <<include>>
Sale ..> Payment : <<include>>
Owner -- Refill
Cashier -- Refill
Staff -- Refill
Refill ..> Availability : <<include>>
Owner -- Visit
Cashier -- Visit
Staff -- Visit
Owner -- Receive
Staff -- Receive
Owner -- Stock
Staff -- Stock
Owner -- Adjust
Owner -- Expense
Owner -- Cash
Cashier -- Cash
Owner -- Reports
@enduml
```

## Key scenarios

| Use case | Preconditions | Normal flow | Rejection or recovery |
|---|---|---|---|
| Process sale | Owner or Cashier; own open drawer | Read DB prices; aggregate components; check portions; save sale/items/servings/payment together | Reject insufficient stock or cash; rollback every partial change; retry token prevents duplicates |
| Record refill | Eligible same-day dine-in meal; visit open | Check configured rice; record one free serving | Reject closed visit or unavailable rice; no extra payment |
| Receive stock | Owner or Staff; active supplier and ingredients | Create draft; edit lines; select payment source; post once | Empty receipt rejected; posted receipt immutable; failed transaction rolls back |
| Issue ingredients | Owner or Staff; sufficient stock | Append negative kitchen issue with reason | Reject insufficient stock; no balance overwrite |
| Adjust stock | Owner only | Append signed correction with reason and audit entry | Reject correction resulting in negative balance |
| Close drawer | Own open cash session | Sum cash effects; count cash; close and record variance | No additional sales/expenses/purchases can use the closed session |

## Architecture

```mermaid
flowchart TD
 Browser[Browser pages and POS JavaScript] --> Controller[index.php action routing]
 Controller --> Access[Authentication CSRF and role checks]
 Access --> Services[Transaction and business services]
 Services --> PDO[Parameterized PDO queries]
 PDO --> DB[(20 table InnoDB database)]
 Controller --> Views[Shared PHP views]
 Views --> Browser
```

## Functional page list

| Route | Purpose |
|---|---|
| login.php | Authenticate active users |
| setup.php | One-time owner account and database creation |
| dashboard | Role-specific live overview |
| pos | Interactive cash counter sales |
| daily-menu | Today's offerings, prices, preparation and waste |
| refills | Eligible meals, free servings and visit closing |
| sales | Searchable completed transactions |
| sale | Itemized transaction and print slip |
| cash-session | Open, reconcile, close, and review sessions |
| catalog | Dish/product CRUD |
| categories | Menu category CRUD |
| combos | Combo component creation, replacement and removal |
| inventory | Current stock and low-stock status |
| ingredients | Ingredient catalog CRUD |
| movements | Stock movement ledger and entry forms |
| receiving | Draft receipts and purchase history |
| receipt | Receipt lines and final posting |
| suppliers | Supplier CRUD |
| units | Measurement unit CRUD |
| expenses | Paid operating expenses |
| expense-categories | Expense category CRUD |
| reports | Eight database reports, printing and CSV |
| users | Team accounts, roles and password reset |
| audit | Sensitive activity history |
| profile | Own name and password changes |
| settings | Operating guide and scope |
