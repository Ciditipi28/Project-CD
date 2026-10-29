# CarinderEase Project Proposal

Family edition update: one Owner account also serves as Admin. The Owner creates optional Cashier and Staff accounts directly. Public registration, OTP and business approval are not part of this edition.

## System name

CarinderEase: A Sales and Daily Operations Management System for Carinderias.

## Background

CarinderEase supports a small eatery serving prepared Filipino meals at a counter. Dishes and prices may change each day, and customers may buy individual portions or fixed combos. Some dine-in meals include rice refills. The application brings these activities together with ingredient stock, cash payments, purchases, expenses, and reports.

The design uses the operating assumptions agreed during project planning. They remain assumptions until verified with the selected carinderia owner or staff; no field interview or actual business study is claimed.

## Problem statement

Manual or scattered records can make it difficult to track today’s menu, remaining portions, individual sales, free rice servings, ingredient usage, and end-of-day cash. The owner needs a reliable way to review these activities without repeatedly combining handwritten records.

## Objectives

Develop and test a PHP/MySQL system that provides secure accounts, a changing daily menu, fast cash checkout, fixed combos, recorded rice refills, ingredient movements, purchasing, expenses, cash reconciliation, and database-driven reports. Preserve transaction history and restrict actions by role.

## Target users

Owner: manages accounts, catalogs, prices, expenses, corrections, and reports. Cashier: handles counter sales and their own cash session. Staff: updates prepared quantities, records ingredient usage and supplies, and serves eligible refills. Customers order at the counter without using an account.

## Scope

One branch, one shared cash drawer, full cash payments, same-day dine-in refill eligibility, fixed combos, separate raw ingredient and prepared-food stock, 20 related database tables, and 24 authenticated functional routes. The system uses HTML, CSS, JavaScript, PHP and MySQL-compatible SQL, runs locally through XAMPP, and keeps its assets offline.

## Limitations

No refunds, customer credit, supplier credit, online ordering, delivery, electronic payment integration, payroll, tax invoicing, mix-and-match offers, or recipe-based costing. Stock accuracy depends on correctly recorded preparation and kitchen activity. Financial reports summarize operations rather than calculate accounting profit.

## Research still needed

Validate portion definitions, rice policies, daily menu preparation, cashier handover, supply payment sources, and required reports with the actual business. Record observations and update the design if the real process differs.
