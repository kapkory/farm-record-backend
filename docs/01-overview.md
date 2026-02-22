# System Overview

## Introduction

The Farm Management System is a comprehensive agricultural management platform designed to empower farmers—from small-scale subsistence farmers to large commercial operations—to manage their farms as profitable businesses.

## Vision

To provide farmers with digital tools that enable:
- **Data-driven decision making** through accurate record-keeping
- **Financial visibility** by tracking expenses, revenues, and profitability
- **Operational efficiency** through task management and planning
- **Scalability** from a single plot to multiple farms with various enterprises

## Core Concepts

### Multi-Tenancy Model

The system uses a **Farmer-centric multi-tenancy** model:

```
User (Login Account)
  └── Farmer (Business Entity) [via farmer_users pivot]
        ├── Farm 1
        │     ├── Field A
        │     ├── Field B
        │     └── Livestock Pen 1
        └── Farm 2
              └── Field C
```

- **User**: Authentication identity (email, password)
- **Farmer**: Business entity (individual, group, or organization)
- **Farm**: Physical land unit with location, size, and type
- **Field**: Subdivision of a farm for crop management
- **Livestock**: Animals managed within a farm

### User Roles

Users are associated with Farmers through the `farmer_users` pivot table with roles:

| Role | Description |
|------|-------------|
| `owner` | Full access, can manage other users |
| `manager` | Can manage farm operations, limited user management |
| `staff` | Limited access to assigned tasks and data entry |

### Farm Types

Farms can be categorized by their primary use:

| Type | Description |
|------|-------------|
| `crop` | Primarily for crop cultivation |
| `animal` | Primarily for livestock/poultry |
| `mixed` | Both crops and animals |

### Ownership Types

| Type | Description |
|------|-------------|
| `owned` | Farmer owns the land |
| `leased` | Land is rented/leased |
| `shared` | Shared ownership or sharecropping arrangement |

## Key Features

### 1. Farm & Field Management
- Register multiple farms with location, size, and characteristics
- Subdivide farms into fields for granular management
- Track soil types, irrigation systems, and field history

### 2. Crop Management
- Catalog of crop types (e.g., Maize, Beans, Coffee)
- Crop varieties with maturity days, expected yields
- Support for **annual** and **perennial** crops
- Planting cycles with expected and actual harvest tracking

### 3. Livestock Management (Planned)
- Animal inventory with identification tags
- Breed and health records
- Reproduction and mortality tracking
- Feed and medication logs

### 4. Financial Tracking (Planned)
- Input costs (seeds, fertilizer, labor, equipment)
- Revenue from sales
- Profit/loss analysis per crop/livestock/field
- Budget planning and forecasting

### 5. Task & Activity Management (Planned)
- Scheduled tasks (planting, spraying, harvesting)
- Worker assignment and tracking
- Calendar integration
- Notifications and reminders

### 6. Reports & Analytics (Planned)
- Yield reports per crop/field/season
- Financial summaries
- Comparative analysis across seasons
- Export to PDF/Excel

## Security Principles

1. **Authentication**: Laravel Sanctum with SPA cookie-based sessions
2. **Authorization**: Scoped queries ensure users only access their farmers' data
3. **Data Isolation**: All queries filtered by `farmer_id` through authenticated user
4. **Input Validation**: Request validation on all endpoints
5. **UUID Exposure**: Internal IDs hidden; UUIDs used in API responses
6. **HTTPS**: Production deployment requires SSL/TLS
7. **Rate Limiting**: API throttling to prevent abuse

## Technology Decisions

| Decision | Rationale |
|----------|-----------|
| Laravel 12 | Latest LTS, modern PHP 8.2+ features, excellent ecosystem |
| Sanctum SPA Auth | Seamless integration with Nuxt frontend, CSRF protection |
| UUID + Auto-increment ID | Performance of integer PKs, security of UUIDs in API |
| Soft Deletes | Data recovery, audit trails, referential integrity |
| Repository Pattern | Separation of concerns, testability |
| API Versioning (`/v1/`) | Future-proof, backward compatibility |

