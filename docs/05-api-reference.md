# API Reference

**Base URL:** `http://localhost:8000/api/v1`

All endpoints require authentication. Include session cookies.

## Response Format

### Success
```json
{"success": true, "message": "...", "data": {...}}
```

### Error
```json
{"success": false, "message": "...", "errors": {...}}
```

---

## Farms

### List Farms
```http
GET /api/v1/farms?per_page=15&page=1&all=false&search=
```

### Get Farm
```http
GET /api/v1/farms/{uuid}
```

### Create Farm
```http
POST /api/v1/farms
{
    "name": "Green Valley Farm",
    "location": "Nairobi, Kenya",
    "size": 50.00,
    "size_unit": "acres",
    "established_date": "2020-01-15",
    "description": "Mixed farming",
    "type": "mixed",           // mixed, crop, animal
    "ownership_type": "owned"  // owned, leased, shared
}
```

### Update Farm
```http
PUT /api/v1/farms/{uuid}
{"name": "Updated Farm Name", "size": 75.00}
```

### Delete Farm
```http
DELETE /api/v1/farms/{uuid}
```

---

## Crops (Settings)

### List Crops
```http
GET /api/v1/settings/crops/list
```

### Create/Update Crop
```http
POST /api/v1/settings/crops/{uuid?}
{"name": "Maize", "description": "Staple cereal"}
```

### Delete Crop
```http
DELETE /api/v1/settings/crops/{uuid}
```

---

## Crop Varieties

### List Varieties
```http
GET /api/v1/settings/crops/varieties/list?crop_id=1
```

### Create/Update Variety
```http
POST /api/v1/settings/crops/varieties/{uuid?}
{
    "crop_id": 1,
    "name": "H614D",
    "maturity_days": 120,
    "expected_yield": 40,
    "harvest_type": "single",
    "description": "Drought-resistant"
}
```

### Delete Variety
```http
DELETE /api/v1/settings/crops/varieties/{uuid}
```

---

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content |
| 401 | Unauthenticated |
| 404 | Not Found |
| 422 | Validation Failed |
| 429 | Rate Limited |
| 500 | Server Error |

