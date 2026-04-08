# CRM Module Implementation

## Overview
This document describes the CRM (Customer Relationship Management) module implementation that adds client and supplier management functionality to the e-commerce application.

## What Was Added

### 1. Database Changes
- **Migration**: `2026_04_08_153955_add_additional_fields_to_clients_and_suppliers_tables.php`
- **New Fields Added** (to both `clients` and `suppliers` tables):
  - `code` - Client/Supplier code
  - `postal_code` - Postal/ZIP code
  - `region` - Region
  - `ice` - ICE (Identifiant Commun de l'Entreprise)
  - `fiscal_identifier` - Fiscal identifier (IF)
  - `latitude` - GPS latitude coordinate
  - `longitude` - GPS longitude coordinate
  - `ville` - City (Ville)

### 2. Models Updated
- **Client Model** (`app/Models/Client.php`)
  - Added new fillable fields
  
- **Supplier Model** (`app/Models/Supplier.php`)
  - Added new fillable fields

### 3. Controllers Created/Updated
- **ClientController** (`app/Http/Controllers/ClientController.php`)
  - Full CRUD operations (index, create, store, show, edit, update, destroy)
  - Form validation for all fields
  
- **SupplierController** (`app/Http/Controllers/SupplierController.php`)
  - Full CRUD operations (index, create, store, show, edit, update, destroy)
  - Form validation for all fields

### 4. Routes Added
Routes are prefixed with `/crm` and protected by authentication middleware:
- `GET /crm/clients` - List all clients
- `GET /crm/clients/create` - Show create client form
- `POST /crm/clients` - Store new client
- `GET /crm/clients/{client}` - Show client details
- `GET /crm/clients/{client}/edit` - Show edit client form
- `PUT /crm/clients/{client}` - Update client
- `DELETE /crm/clients/{client}` - Delete client

- `GET /crm/suppliers` - List all suppliers
- `GET /crm/suppliers/create` - Show create supplier form
- `POST /crm/suppliers` - Store new supplier
- `GET /crm/suppliers/{supplier}` - Show supplier details
- `GET /crm/suppliers/{supplier}/edit` - Show edit supplier form
- `PUT /crm/suppliers/{supplier}` - Update supplier
- `DELETE /crm/suppliers/{supplier}` - Delete supplier

### 5. Views Created

#### Client Views
- `resources/views/clients/index.blade.php` - List all clients with search/filter
- `resources/views/clients/create.blade.php` - Create new client form
- `resources/views/clients/edit.blade.php` - Edit existing client form
- `resources/views/clients/show.blade.php` - View client details

#### Supplier Views (Fournisseurs)
- `resources/views/suppliers/index.blade.php` - List all suppliers
- `resources/views/suppliers/create.blade.php` - Create new supplier form
- `resources/views/suppliers/edit.blade.php` - Edit existing supplier form
- `resources/views/suppliers/show.blade.php` - View supplier details

### 6. Sidebar Navigation Updated
Added a new "CRM" section to the sidebar (`resources/views/layouts/sidebar.blade.php`) with:
- **Clients** submenu item (links to clients index)
- **Fournisseurs** submenu item (links to suppliers index)

## Form Fields

### Client Form Fields
**Required Fields:**
- Entreprise (Company Name) *
- Email *

**Optional Fields:**
- Téléphone (Phone)
- Adresse (Address)
- Code client (Client Code)
- Code postal (Postal Code)
- ICE
- Région (Region)
- Identifiant fiscal (IF) (Fiscal Identifier)
- Ville (City)
- Latitude (Google Maps)
- Longitude (Google Maps)
- Pays (Country) - Defaults to "Maroc"

### Supplier Form Fields
**Required Fields:**
- Fournisseur (Supplier Name) *
- Email *

**Optional Fields:**
- Adresse (Address)
- Code
- Ville (City)
- Identifiant fiscal (IF) (Fiscal Identifier)
- Région (Region)
- ICE
- Code postal (Postal Code)
- Latitude
- Numéro de téléphone (Phone Number)
- Longitude
- Pays (Country) - Defaults to "Maroc"

## Next Steps

### To Complete Setup:
1. **Run the migration** (when database is available):
   ```bash
   php artisan migrate
   ```

2. **Test the functionality**:
   - Navigate to the CRM section in the sidebar
   - Try creating, editing, viewing, and deleting clients
   - Try creating, editing, viewing, and deleting suppliers

## Features
- ✅ Full CRUD operations for Clients
- ✅ Full CRUD operations for Suppliers (Fournisseurs)
- ✅ Form validation
- ✅ Responsive design matching existing application style
- ✅ Success/error messages
- ✅ Pagination support
- ✅ GPS coordinates support (Latitude/Longitude)
- ✅ Moroccan business identifiers (ICE, IF)

## Technical Details
- Framework: Laravel
- Frontend: Blade templates with Tailwind CSS
- JavaScript: Alpine.js for interactivity
- Database: MySQL
