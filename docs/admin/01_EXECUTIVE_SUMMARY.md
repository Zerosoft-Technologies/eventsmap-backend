# Super-Admin Panel Blueprint
## Part 1: Executive Summary & Module Breakdown

**Version:** 1.0  
**Date:** January 2026  
**Tech Stack:** Laravel REST API | React (CoreUI) | PostgreSQL + PostGIS

---

# 1. EXECUTIVE SUMMARY

## 1.1 System Overview

This blueprint defines the architecture for a **Super-Admin Panel** that manages an Events platform with map-based discovery. The system introduces:

- **Admin-only APIs** under `/api/admin/*` namespace
- **Role-based access control** with audit logging
- **Complete CRUD operations** for all event-related entities
- **Clean separation** between public read APIs and admin write APIs

## 1.2 Design Principles

| Principle | Implementation |
|-----------|----------------|
| **Separation of Concerns** | Public APIs remain read-only; Admin APIs handle all mutations |
| **Security First** | All admin endpoints require authentication + role verification |
| **Audit Trail** | Every admin action is logged with user, timestamp, and changes |
| **Scalability** | Module-based architecture allows independent feature scaling |
| **Future-Ready** | Booking, multi-tenancy, and API versioning considered |

## 1.3 Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        SUPER-ADMIN PANEL                        │
│                     (CoreUI React Frontend)                     │
└─────────────────────────────────────────────────────────────────┘
                                │
                                │ HTTPS + JWT/Sanctum
                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                      LARAVEL REST API                           │
├─────────────────────────────────────────────────────────────────┤
│  /api/v1/*          │  /api/admin/*                             │
│  (Public, Read-Only)│  (Protected, Full CRUD)                   │
├─────────────────────┴───────────────────────────────────────────┤
│                    MIDDLEWARE LAYER                             │
│  • auth:sanctum  • role:super-admin  • audit.log               │
├─────────────────────────────────────────────────────────────────┤
│                    SERVICE LAYER                                │
│  EventService | TalentService | MediaService | CategoryService  │
├─────────────────────────────────────────────────────────────────┤
│                    REPOSITORY LAYER                             │
├─────────────────────────────────────────────────────────────────┤
│              PostgreSQL + PostGIS Database                      │
└─────────────────────────────────────────────────────────────────┘
```

---

# 2. SUPER-ADMIN MODULE BREAKDOWN

## 2.1 Module Overview Matrix

| Module | Purpose | Access Level | Priority |
|--------|---------|--------------|----------|
| Dashboard | System overview & KPIs | Super-Admin | P0 |
| Event Management | Full event lifecycle control | Super-Admin | P0 |
| Talent Management | Manage performers/speakers | Super-Admin | P0 |
| Category Management | Organize event taxonomy | Super-Admin | P0 |
| Media Management | Central media library | Super-Admin | P1 |
| Booking & Capacity | Ticket/capacity management | Super-Admin | P2 (Future) |
| Analytics & Reporting | Data insights & exports | Super-Admin | P1 |
| Admin Users & Roles | User management | Super-Admin | P1 |
| System Settings | Global configuration | Super-Admin | P1 |

---

## 2.2 Module Details

### 2.2.1 Dashboard Module

**Purpose:** Provide at-a-glance system health and key metrics.

**Key Responsibilities:**
- Display total events by status (Draft, Published, Featured, Cancelled, Archived)
- Show upcoming events timeline (next 7/30 days)
- Display recent admin activity log
- Show media storage usage
- Display category distribution chart
- Quick actions panel (Create Event, View Recent, etc.)

**Widgets:**

| Widget | Data Source | Refresh |
|--------|-------------|---------|
| Event Status Cards | events table, grouped by status | Real-time |
| Upcoming Events List | events table, ordered by start_date | 5 min |
| Recent Activity | audit_logs table | Real-time |
| Storage Usage | media table, sum of file_size | 15 min |
| Category Distribution | Pie chart from event_category pivot | 15 min |
| Map Preview | PostGIS aggregate of event locations | 15 min |

---

### 2.2.2 Event Management Module

**Purpose:** Complete control over event lifecycle and content.

**Key Responsibilities:**
- Create, edit, duplicate, delete events
- Manage event status transitions
- Attach talents to events
- Manage event media gallery
- Configure SEO metadata
- Control visibility and featuring

*(Detailed in Part 2)*

---

### 2.2.3 Talent Management Module

**Purpose:** Manage performers, speakers, artists, and other talents.

**Key Responsibilities:**
- Create and maintain talent profiles
- Activate/deactivate talents
- Attach talents to multiple events
- Define talent roles per event
- Control display ordering

*(Detailed in Part 3)*

---

### 2.2.4 Category & Subcategory Management Module

**Purpose:** Define and organize event taxonomy.

**Key Responsibilities:**
- Create/edit categories and subcategories
- Enable/disable categories
- Control display ordering
- Manage category icons/images
- Validate category dependencies before deletion

*(Detailed in Part 3)*

---

### 2.2.5 Media Management Module

**Purpose:** Central media library for all platform assets.

**Key Responsibilities:**
- Upload, organize, and delete media files
- Manage image metadata (alt text, captions)
- Support multiple media types (images, videos)
- Track media usage across events
- Prepare for CDN integration

*(Detailed in Part 3)*

---

### 2.2.6 Booking & Capacity Module (Future-Ready)

**Purpose:** Manage event ticketing and capacity.

**Key Responsibilities:**
- Define ticket types and pricing
- Set event capacity limits
- Track bookings and attendance
- Manage waitlists
- Generate booking reports

**Status:** Future Phase - Schema placeholders only

**Placeholder Schema:**
```
event_tickets: id, event_id, name, price, quantity, sold_count, status
bookings: id, event_id, ticket_id, user_id, quantity, status, booked_at
```

---

### 2.2.7 Analytics & Reporting Module

**Purpose:** Data insights and exportable reports.

**Key Responsibilities:**
- Event performance metrics
- Category popularity analysis
- Geographic distribution (map heatmap)
- Talent engagement stats
- Export to CSV/PDF

**Reports Available:**

| Report | Description | Export Formats |
|--------|-------------|----------------|
| Events Summary | All events with status, dates, category | CSV, PDF |
| Events by Category | Grouped event counts | CSV |
| Events by Location | Geographic distribution | CSV, Map |
| Talent Performance | Events per talent, engagement | CSV |
| Audit Trail | Admin activity log | CSV |

---

### 2.2.8 Admin Users & Roles Module

**Purpose:** Manage admin accounts and permissions.

**Key Responsibilities:**
- Create/edit admin users
- Assign roles (Super-Admin, Admin)
- Activate/deactivate accounts
- Reset passwords
- View login history

**User Fields:**

| Field | Type | Required |
|-------|------|----------|
| name | string | Yes |
| email | string (unique) | Yes |
| password | string (hashed) | Yes |
| role | enum (super_admin, admin) | Yes |
| is_active | boolean | Yes |
| last_login_at | timestamp | System |
| created_at | timestamp | System |

---

### 2.2.9 System Settings Module

**Purpose:** Global platform configuration.

**Key Responsibilities:**
- API configuration (rate limits, cache TTL)
- Default SEO settings
- Media upload limits
- Feature flags
- Maintenance mode toggle

**Settings Categories:**

| Category | Settings |
|----------|----------|
| General | Site name, description, timezone |
| Media | Max upload size, allowed types, storage path |
| SEO | Default meta title, description, OG image |
| API | Rate limit, cache duration |
| Features | Enable booking, enable map, enable analytics |
