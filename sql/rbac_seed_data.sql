-- RBAC seed data for complaint_management database
-- ---------------------------------------------------------------------------
-- 1. Run sql/rbac_schema.sql first (creates tables)
-- 2. Run this file to insert roles, modules, and permissions
--
-- Role IDs (1-6) match user_master.role values in includes/user_helpers.php
-- Module slugs match includes/rbac_access_helpers.php
--
-- RE-SEED (dev only): uncomment the TRUNCATE block below before running again
-- ---------------------------------------------------------------------------

/*
TRUNCATE TABLE role_permissions RESTART IDENTITY CASCADE;
TRUNCATE TABLE permissions RESTART IDENTITY CASCADE;
TRUNCATE TABLE modules RESTART IDENTITY CASCADE;
TRUNCATE TABLE roles RESTART IDENTITY CASCADE;
*/

-- ---------------------------------------------------------------------------
-- ROLES
-- ---------------------------------------------------------------------------
INSERT INTO roles (id, role_name, description, status, created_by, created_at) VALUES
(1, 'Dealer User',       'Standard dealer portal user', 'active', 'system', CURRENT_TIMESTAMP),
(2, 'Dealer Engineer',   'Dealer service engineer',     'active', 'system', CURRENT_TIMESTAMP),
(3, 'ELGi Engineer',     'ELGi service engineer',       'active', 'system', CURRENT_TIMESTAMP),
(4, 'Sales Coordinator', 'Sales coordination user',     'active', 'system', CURRENT_TIMESTAMP),
(5, 'Management',        'Management dashboard user',   'active', 'system', CURRENT_TIMESTAMP),
(6, 'System Admin',      'Full system administrator',   'active', 'system', CURRENT_TIMESTAMP);

SELECT setval(pg_get_serial_sequence('roles', 'id'), (SELECT MAX(id) FROM roles));

-- ---------------------------------------------------------------------------
-- MODULES
-- ---------------------------------------------------------------------------
INSERT INTO modules (id, module_name, module_slug, description, status, created_by, created_at) VALUES
(1,  'Dashboard',             'dashboard',               'Dashboard overview',                 'active', 'system', CURRENT_TIMESTAMP),
(2,  'Installed Base Capture', 'installed-base-capture',  'Installed base capture management',  'active', 'system', CURRENT_TIMESTAMP),
(3,  'Service Log Capture',    'service-log-capture',     'Service log capture management',     'active', 'system', CURRENT_TIMESTAMP),
(4,  'Spare Parts Consumption','spare-parts-consumption', 'Spare parts consumption management', 'active', 'system', CURRENT_TIMESTAMP),
(5,  'Complaint Entry',        'complaint-entry',         'Complaint entry management',         'active', 'system', CURRENT_TIMESTAMP),
(6,  'Assigned Complaint List','assigned-complaint-list', 'Assigned complaint list management', 'active', 'system', CURRENT_TIMESTAMP),
(7,  'Order Booking',          'order-booking',           'Create and manage orders',           'active', 'system', CURRENT_TIMESTAMP),
(8,  'Order Acknowledgement',  'order-acknowledgement',   'Order acknowledgement list',         'active', 'system', CURRENT_TIMESTAMP),
(9,  'Pending Orders',         'pending-orders',          'Pending order list',                 'active', 'system', CURRENT_TIMESTAMP),
(10, 'Recent Orders',          'recent-orders',           'Recent order list',                  'active', 'system', CURRENT_TIMESTAMP),
(11, 'Despatch Details',       'despatch-details',        'Despatch details',                   'active', 'system', CURRENT_TIMESTAMP),
(12, 'LR Details',             'lr-details',              'LR details',                         'active', 'system', CURRENT_TIMESTAMP);

SELECT setval(pg_get_serial_sequence('modules', 'id'), (SELECT MAX(id) FROM modules));

-- ---------------------------------------------------------------------------
-- PERMISSIONS
-- ---------------------------------------------------------------------------

-- Dashboard (module_id = 1)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(1, 'View', 'view', 'View dashboard', 'active', 'system', CURRENT_TIMESTAMP);

-- Installed Base Capture (module_id = 2)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(2, 'View',   'view',   'View installed base records',   'active', 'system', CURRENT_TIMESTAMP),
(2, 'Add',    'add',    'Add installed base records',    'active', 'system', CURRENT_TIMESTAMP),
(2, 'Edit',   'edit',   'Edit installed base records',   'active', 'system', CURRENT_TIMESTAMP),
(2, 'Delete', 'delete', 'Delete installed base records', 'active', 'system', CURRENT_TIMESTAMP);

-- Service Log Capture (module_id = 3)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(3, 'View',   'view',   'View service log records',   'active', 'system', CURRENT_TIMESTAMP),
(3, 'Add',    'add',    'Add service log records',    'active', 'system', CURRENT_TIMESTAMP),
(3, 'Edit',   'edit',   'Edit service log records',   'active', 'system', CURRENT_TIMESTAMP),
(3, 'Delete', 'delete', 'Delete service log records', 'active', 'system', CURRENT_TIMESTAMP);

-- Spare Parts Consumption (module_id = 4)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(4, 'View',   'view',   'View spare parts consumption records',   'active', 'system', CURRENT_TIMESTAMP),
(4, 'Add',    'add',    'Add spare parts consumption records',    'active', 'system', CURRENT_TIMESTAMP),
(4, 'Edit',   'edit',   'Edit spare parts consumption records',   'active', 'system', CURRENT_TIMESTAMP),
(4, 'Delete', 'delete', 'Delete spare parts consumption records', 'active', 'system', CURRENT_TIMESTAMP);

-- Complaint Entry (module_id = 5)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(5, 'View',               'view',               'View complaints',        'active', 'system', CURRENT_TIMESTAMP),
(5, 'Add',                'add',                'Add complaints',         'active', 'system', CURRENT_TIMESTAMP),
(5, 'Delete',             'delete',             'Delete complaints',      'active', 'system', CURRENT_TIMESTAMP),
(5, 'Assign Complaint',   'assign-complaint',   'Assign complaint',       'active', 'system', CURRENT_TIMESTAMP),
(5, 'Complaint Closure',  'complaint-closure',  'Close complaint',        'active', 'system', CURRENT_TIMESTAMP),
(5, 'Reassign Complaint', 'reassign-complaint', 'Reassign complaint',     'active', 'system', CURRENT_TIMESTAMP);

-- Assigned Complaint List (module_id = 6)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(6, 'View',           'view',           'View assigned complaints',                 'active', 'system', CURRENT_TIMESTAMP),
(6, 'Service Update', 'service-update', 'Update service for assigned complaint',    'active', 'system', CURRENT_TIMESTAMP);

-- Order Booking (module_id = 7)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(7, 'View',   'view',   'View order booking',   'active', 'system', CURRENT_TIMESTAMP),
(7, 'Add',    'add',    'Add order booking',    'active', 'system', CURRENT_TIMESTAMP),
(7, 'Edit',   'edit',   'Edit order booking',   'active', 'system', CURRENT_TIMESTAMP),
(7, 'Delete', 'delete', 'Delete order booking', 'active', 'system', CURRENT_TIMESTAMP);

-- Order Acknowledgement (module_id = 8)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(8, 'View',   'view',   'View order acknowledgement',   'active', 'system', CURRENT_TIMESTAMP),
(8, 'Add',    'add',    'Add order acknowledgement',    'active', 'system', CURRENT_TIMESTAMP),
(8, 'Edit',   'edit',   'Edit order acknowledgement',   'active', 'system', CURRENT_TIMESTAMP),
(8, 'Delete', 'delete', 'Delete order acknowledgement', 'active', 'system', CURRENT_TIMESTAMP);

-- Pending Orders (module_id = 9)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(9, 'View',   'view',   'View pending orders',   'active', 'system', CURRENT_TIMESTAMP),
(9, 'Add',    'add',    'Add pending orders',    'active', 'system', CURRENT_TIMESTAMP),
(9, 'Edit',   'edit',   'Edit pending orders',   'active', 'system', CURRENT_TIMESTAMP),
(9, 'Delete', 'delete', 'Delete pending orders', 'active', 'system', CURRENT_TIMESTAMP);

-- Recent Orders (module_id = 10)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(10, 'View',   'view',   'View recent orders',   'active', 'system', CURRENT_TIMESTAMP),
(10, 'Add',    'add',    'Add recent orders',    'active', 'system', CURRENT_TIMESTAMP),
(10, 'Edit',   'edit',   'Edit recent orders',   'active', 'system', CURRENT_TIMESTAMP),
(10, 'Delete', 'delete', 'Delete recent orders', 'active', 'system', CURRENT_TIMESTAMP);

-- Despatch Details (module_id = 11)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(11, 'View',   'view',   'View despatch details',   'active', 'system', CURRENT_TIMESTAMP),
(11, 'Add',    'add',    'Add despatch details',    'active', 'system', CURRENT_TIMESTAMP),
(11, 'Edit',   'edit',   'Edit despatch details',   'active', 'system', CURRENT_TIMESTAMP),
(11, 'Delete', 'delete', 'Delete despatch details', 'active', 'system', CURRENT_TIMESTAMP);

-- LR Details (module_id = 12)
INSERT INTO permissions (module_id, permission_name, permission_slug, description, status, created_by, created_at) VALUES
(12, 'View',   'view',   'View LR details',   'active', 'system', CURRENT_TIMESTAMP),
(12, 'Add',    'add',    'Add LR details',    'active', 'system', CURRENT_TIMESTAMP),
(12, 'Edit',   'edit',   'Edit LR details',   'active', 'system', CURRENT_TIMESTAMP),
(12, 'Delete', 'delete', 'Delete LR details', 'active', 'system', CURRENT_TIMESTAMP);
