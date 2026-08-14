# Order Modules — Functional Summary

## Recent Orders

**What it does:** Shows orders you booked in the portal (`plexecom_customer_units`), grouped by Ref No. It is the main place to track status after Order Booking and to open full order details.

### Flow

- Order Booking (submit) → Recent Orders (track by Ref No) → AO / Despatch (via status)
- After a successful booking, the app redirects here with `?order_no={refno}` so the new order is easy to find.

### What the list shows

- Ref No, AO Number, Category, Delivery Term, PO, Payment Term, Transporter, Order Status, Order Date, Added By (only for Admin / Management / CCS Admin), Actions: View details, Re-Push (when allowed)
- Server-side DataTables with search, sort, and pagination.

### Order status meanings

- **Pending:** Booked; no AO yet (or no AO number)
- **AO:** Acknowledged in ERP (`order_number` populated)
- **Despatched:** Shipment exists in `despatch` for that AO

### Access (RBAC)

- `recent-orders` / **list:** Open the list
- `recent-orders` / **view:** See detail link
- `recent-orders` / **export-excel:** Export (UI disabled)

Assigned via Assign Permissions. Non-admin users see only their customer's orders; Admin/Management/CCS Admin can see broader scope (including Added By).

### Tables used

| Table | Database | Purpose |
|-------|----------|---------|
| `plexecom_customer_units` | OB | Primary list, details, and Re-Push source |
| `despatch` | DP | Despatched status check |
| `tbl_vayu_delivery_term` | OB | Delivery term label |
| `tbl_vayu_order_category` | OB | Category label |
| `spp_payterm_master` | OB | Payment term description |
| `transporter_master` | OB | Transporter name |
| `user_master` | OB | Added By column (admin roles) |
| `area` | OB | Details page enrichment |
| `customer_address` | DP | Re-Push / address helpers |

### Data sources

- `plexecom_customer_units` — booked orders (Ref No, lines, header fields)
- `despatch` — despatched check
- `customer_master`, delivery/payment/transporter masters — labels on list/detail
- LN API — Re-Push resubmits order XML
- Dashboard — shows a 10-row preview of Recent Orders + pipeline counts

---

## Order Booking

**What it does:** Lets authorized users create and submit dealer orders in the portal. Users fill an order header, add products to a cart, then submit. Draft lines stay in `tbl_vayu_cartitems`; on submit, lines are saved to `plexecom_customer_units` under a shared Ref No and sent to ERP (ION/LN).

### Flow

- Order Booking (build cart + submit) → `plexecom_customer_units` (Ref No created) → ION ERP (`ProcessSalesOrder`)
- On success → redirect to Recent Orders with `?order_no={refno}` so the new order is easy to find

### What the page shows / user actions

- **Header:** Category, Area, PO Number, Delivery Date, Delivery Term, Payment Term, Transporter, Delivery Address (Dealer / End Customer), Order Type (Units / Spares)
- **Product cart:** Search/add products, update qty, remove lines, view cart totals and price breakup (GST/freight display)
- **Submit:** `submitCartApi` (live path) — persists order and posts to ION
- **Rules:** Cannot mix Units and Spares in one cart; duplicate item merges qty; empty cart cannot be submitted
- **Role UI:** Dealer Engineer / ELGi Engineer get an extra Dealer Select2 for address lookup; other roles use session customer address

### Order state at create

- Ref No generated (e.g. `E/UNITS/{date}{sequence}`)
- `order_number` (AO) empty until ERP acknowledges
- Cart draft cleared after successful submit

### Access (RBAC)

- `order-booking` / **create-order:** Open page and book orders
- Assigned via Assign Permissions
- Unauthenticated → login; no permission → access denied
- Order is scoped to session customer (`customer_number_vayu`, fallback `10001`)

### Tables used

| Table | Database | Purpose |
|-------|----------|---------|
| `tbl_vayu_cartitems` | OB | Draft cart (`status = 0`, per user) |
| `plexecom_customer_units` | OB | Booked order lines (shared Ref No on submit) |
| `product_master_vayu` | OB | Product search, price, order type |
| `tbl_vayu_order_category` | OB | Order category dropdown |
| `area` | OB | Area dropdown |
| `transporter_master` | OB | Transporter dropdown |
| `dealercode_and_transportercode` | OB | Transporter mapping |
| `customer_master` | OB/DP | Customer / dealer lookup |
| `customer_address` | OB/DP | Delivery address Select2 |
| `dpst_master` | OB | Product group (DPST) |
| `gst_hsn` | OB | HSN / tax on submit |
| `user_master` | OB | EDI email lookup on submit |

**OB** = order booking DB (`$obconn`) · **DP** = dealer portal DB (`$dpconn`)

### Data sources

- `tbl_vayu_cartitems` — draft cart (`status = 0`, per user)
- `plexecom_customer_units` — booked order lines (shared Ref No)
- `product_master_vayu` — product search, price, order type
- `customer_master`, `customer_address`, `area`, `transporter_master`, `dpst_master`, `gst_hsn` — header lookups
- ION API — `ProcessSalesOrder` XML on submit (`submitCartApi`)
- Recent Orders — success redirect and status tracking after booking
- Dashboard — pipeline KPIs (Created / Acknowledged / Pending / Dispatched) from booked orders

---

## Order Acknowledgement

**What it does:** Read-only list of orders **acknowledged by ERP** (AO = Acknowledgement Order). Shows ERP-confirmed orders from `maintdealer` and links booking Ref No from `plexecom_customer_units`.

### Flow

- Order Booking (submit) → ERP acknowledges → **Order Acknowledgement** (AO list) → Despatch (when shipped)
- Ref No is resolved by matching portal booking `order_number` to ERP AO `ordno`

### What the list shows

- Ref No, PO Number, AO Number, AO Date, Action (View details)
- Server-side DataTables with search, sort, and pagination
- Global search by AO number

### Detail view

- Eye icon opens `order_data.php?order={AO}&cuno={customer}&reference=order_acknowledgement` (read-only header + line items)
- Shared detail page also used by Pending Orders

### Access (RBAC)

- `order-acknowledgement` / **list:** Open the list
- `order-acknowledgement` / **view:** See detail link
- `order-acknowledgement` / **export-excel:** Export (UI disabled)

Assigned via Assign Permissions. List scoped to logged-in user (`usr_name` as customer). Read-only — no create/edit.

### Tables used

| Table | Database | Purpose |
|-------|----------|---------|
| `maintdealer` | DP | Primary AO list and detail header/lines |
| `plexecom_customer_units` | OB | Ref No bridge via `order_number` |
| `dpst_master` | DP | Product group description |
| `customer_master` | DP | Detail page customer name |
| `cust_delivery_address` | DP | Detail page delivery address |

### Data sources

- `maintdealer` — ERP-acknowledged order header/lines (dealer portal DB)
- `plexecom_customer_units` — Ref No mapping from portal booking
- `customer_master`, `cust_delivery_address` — detail page labels
- Dashboard — Acknowledged KPI uses related booking/ERP data

---

## Pending Orders

**What it does:** Read-only list of **open / not fully fulfilled** order lines from ERP (`pendingordersnew`). Shows delivery dates and computed status for lines still pending fulfilment.

### Flow

- Order Booking → ERP acknowledgement → **Pending Orders** (open lines) → Despatch (when complete)
- Status derived from `despatch` and `plexecom_customer_units.order_number`

### What the list shows

- Ref No, PO Number, AO Number, Delivery Date, Status, Action (View details)
- Server-side DataTables with search, sort, and pagination
- Global search by AO / PO / indent

### Status meanings

- **Pending:** Open line, not yet fully processed
- **AO:** Acknowledged (linked to booking)
- **Despatched:** Shipment exists in `despatch` for that AO

### Detail view

- Eye icon opens `order_data.php?...&reference=pending_order` (same read-only AO detail as Order Acknowledgement)

### Access (RBAC)

- `pending-order` / **list:** Open the list
- `pending-order` / **view:** See detail link
- `pending-order` / **export-excel:** Export (UI disabled)

Assigned via Assign Permissions. List scoped to logged-in user (`usr_name`). Read-only — no create/edit.

### Tables used

| Table | Database | Purpose |
|-------|----------|---------|
| `pendingordersnew` | DP | Primary open pending order lines |
| `plexecom_customer_units` | OB | Ref No and status enrichment |
| `despatch` | DP | Despatched status badge |
| `dpst_master` | DP | Product group description |
| `tbl_commitment` | DP | Commitment / delivery date lookup |
| `maintdealer` | DP | Detail page header/lines (shared with AO) |

### Data sources

- `pendingordersnew` — open ERP pending order lines (dealer portal DB)
- `plexecom_customer_units` — Ref No and status enrichment
- `despatch` — despatched status check
- `dpst_master`, `tbl_commitment` — labels and delivery info
- Dashboard — Pending KPI uses a different definition (empty `order_number` on booked orders)

---

## Despatch Details

**What it does:** Read-only list of **shipped/despatched** orders — invoice and shipment stage. Shows despatched rows from ERP with LR packing, transporter, and weight data.

### Flow

- Order Booking → Acknowledgement → Pending → **Despatch Details** (shipped) → LR Details (transport docs)
- Recent Orders / Pending Orders show **Despatched** status when a matching row exists in `despatch`

### What the list shows

- AO Number, Order Ref, Invoice Date, Transporter, LR Number, Packaging, Weight, Action
- Server-side DataTables with search, sort, and pagination
- Global search by invoice / AO / customer / product group

### Access (RBAC)

- `despatch-details` / **list:** Open the list
- `despatch-details` / **view:** See detail link (client-side)
- `despatch-details` / **export-excel:** Export button visible (legacy export target)

Assigned via Assign Permissions. Scoped to session customer (`customer_number_vayu`, fallback `10001`). Read-only — no create/edit.

### Tables used

| Table | Database | Purpose |
|-------|----------|---------|
| `despatch` | DP | Primary despatched invoice/shipment list |
| `lr_details` | DP | LR number, packing, transporter, weight (join) |
| `dpst_master` | DP | Product group description |

### Data sources

- `despatch` — despatched invoice/shipment records (dealer portal DB)
- `lr_details` — LR packing, transporter, weight (joined on list)
- `dpst_master` — product group description
- Recent Orders / Pending Orders / Dashboard — Despatched status and KPIs

---

## LR Details

**What it does:** Read-only list of **Lorry Receipt (LR)** records — transport/shipment documents after despatch. Dedicated LR-centric view of the shipment stage.

### Flow

- Despatch Details (shipment) → **LR Details** (LR number, date, transporter)
- Sibling module to Despatch Details; both cover post-acknowledgement shipment stage

### What the list shows

- Product Group, AO Number, Invoice, Despatch Date, Transporter, LR Number, LR Date
- Server-side DataTables with search, sort, and pagination
- Global search by product group / AO / invoice / LR / transporter

### Access (RBAC)

- `lr-details` / **list:** Open the list

Assigned via Assign Permissions. Scoped to session customer (`customer_number_vayu`, fallback `10001`). Read-only — no create/edit.

### Tables used

| Table | Database | Purpose |
|-------|----------|---------|
| `lrdetails` | DP | Primary LR list source |
| `dpst_master` | DP | Product group description |

**Note:** Despatch Details uses a separate table `lr_details` (packing join); LR Details uses `lrdetails`.

### Data sources

- `lrdetails` — LR records (dealer portal DB; separate from `lr_details` used in Despatch join)
- `dpst_master` — product group description
- Despatch Details — related shipment data via shared AO/invoice keys
