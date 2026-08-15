---
paths:
  - 'routes/web.php,app/Http/Middleware/EnsureUserHasRole.php,app/Models/*.php,app/Livewire/{Customer,Technician,SuperAdmin}/**'
---

# Customer Technician Super Admin

## Keep role flows on shared operations records
Use the custom role middleware for route boundaries: admin maps to superadmin/admin, technician and customer remain separate. Keep bookings, payments, reviews, and walk-in entries relational and derive dashboard/report values from those tables; completed bookings create one pending payment record.
