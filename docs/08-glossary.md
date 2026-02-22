# Glossary

Common terms used throughout the Farm Management System.

---

## Business Terms

| Term | Definition |
|------|------------|
| **Farmer** | A business entity (individual, group, or organization) that manages farms |
| **Farm** | A physical land unit with defined boundaries, owned/leased by a farmer |
| **Field** | A subdivision of a farm used for specific crop cultivation |
| **Planting** | A crop cycle from seed to harvest on a specific field/farm |
| **Harvest** | The collection of mature crops from a planting |
| **Livestock** | Animals raised on a farm (cattle, goats, poultry, etc.) |
| **Input** | Materials used in farming (seeds, fertilizers, pesticides, feed) |

---

## Crop Types

| Term | Definition |
|------|------------|
| **Annual** | Crops that complete lifecycle in one growing season (e.g., maize, beans) |
| **Perennial** | Crops that persist for multiple years (e.g., coffee, tea, fruit trees) |
| **Biennial** | Crops that complete lifecycle over two years |
| **Variety** | A specific cultivar of a crop type (e.g., H614D maize) |

---

## Farm Types

| Term | Definition |
|------|------------|
| **Crop Farm** | Farm primarily for crop cultivation |
| **Animal Farm** | Farm primarily for livestock/poultry |
| **Mixed Farm** | Farm with both crops and animals |

---

## Ownership Types

| Term | Definition |
|------|------------|
| **Owned** | Farmer owns the land outright |
| **Leased** | Land is rented from another party |
| **Shared** | Joint ownership or sharecropping arrangement |

---

## User Roles

| Role | Description |
|------|-------------|
| **Owner** | Full access to all farm data and user management |
| **Manager** | Can manage operations, limited user management |
| **Staff** | Limited access to assigned tasks and data entry |

---

## Technical Terms

| Term | Definition |
|------|------------|
| **UUID** | Universally Unique Identifier - used for public API references |
| **Sanctum** | Laravel package for SPA authentication |
| **CSRF** | Cross-Site Request Forgery - security protection |
| **Scope** | Eloquent query filter for multi-tenancy |
| **Pivot Table** | Database table linking two entities (e.g., farmer_users) |

---

## Status Values

| Value | Meaning |
|-------|---------|
| `1` / `active` | Entity is active and usable |
| `0` / `inactive` | Entity is disabled but retained |
| `deleted` | Soft-deleted, can be restored |

---

## Units

### Size Units
- `acres` - Imperial unit (1 acre = 0.405 hectares)
- `hectares` - Metric unit (1 hectare = 2.47 acres)
- `sqm` - Square meters

### Quantity Units
- `kg` - Kilograms
- `bags` - Standard bags (usually 50kg or 90kg)
- `seedlings` - Individual plants
- `heads` - Individual animals

