# Implemented Schema Notes

The included SVG ERDs are generated from database/schema.sql. The complete view lists all fields; module views show key and important operational fields. Each edge joins a referenced primary key to a foreign-key field.

Most relationships are one to many. Important exceptions: payments.sale_id is unique (one payment per sale); stock_movements.stock_receipt_item_id is nullable and unique (at most one movement per receipt line). Nullable foreign keys are shown explicitly in the dictionary. The diagram uses a crow-foot visual overview; these constraints and the SQL define exact optionality.

Family edition implementation details:

- users.owner_guard is a stored generated column: role_id 1 generates 1, all other roles generate NULL. Its unique index permits only one Owner account. The Owner also acts as Admin. Staff accounts are created directly by the Owner. There are no business registration, OTP or platform-admin tables.
- cash_sessions includes a generated open_guard column and a unique index. Open sessions generate 1; closed sessions generate NULL. This enforces one open cash drawer at the database level without a 21st table.
- All writes run under one short per-database advisory lock and an SQL transaction. This is a deliberate simplification for a single carinderia; the initial dictionary proposed more granular row locks.
- The database connection enforces strict SQL mode, including ONLY_FULL_GROUP_BY.
- Sales use the cashier identity from cash_sessions. There is no redundant cashier_id on sales.
- Starter data is optional. It includes catalog/menu/stock examples, not completed sales.
- Eight report types and 24 authenticated routes are implemented. Owner manages master catalogs; Staff edits preparation and receives stock; Cashier processes their own sales.
- Food images are bundled SVG interface illustrations, selected by product name. Product photo uploads are outside this release.

SQL is the authoritative implementation. The original planning dictionary remains included for the purpose and business meaning of each field.
