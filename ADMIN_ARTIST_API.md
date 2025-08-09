# Admin and Artist API Documentation

## Overview

The backend now supports comprehensive admin and artist functionality with role-based access control.

## Authentication

All protected routes require authentication using `Authorization: Bearer {token}` header.

## Role-Based Access

- **Admin**: Full access to user management and role approvals
- **Artist**: Access to own music management and statistics
- **User**: Can request role changes

---

## Admin API Endpoints

### Dashboard
```
GET /admin/dashboard
```
Returns admin dashboard statistics including:
- Total users, artists, admins
- Pending role requests
- Recent users and requests

### User Management
```
GET /admin/users?per_page=15&role=user&search=john
```
List all users with pagination and filtering

```
GET /admin/users/{id}
```
Get detailed user information including ratings and uploaded music

```
PATCH /admin/users/{id}
```
Update user details:
```json
{
  "name": "New Name",
  "email": "new@email.com", 
  "role": "artist",
  "status": "active"
}
```

```
DELETE /admin/users/{id}
```
Deactivate user (soft delete)

### Role Change Management
```
GET /admin/role-requests?status=pending&per_page=15
```
List role change requests with filtering

```
PATCH /admin/role-requests/{id}/approve
```
Approve role change request:
```json
{
  "admin_notes": "Approved based on portfolio review"
}
```

```
PATCH /admin/role-requests/{id}/reject
```
Reject role change request:
```json
{
  "admin_notes": "Insufficient music portfolio"
}
```

---

## Artist API Endpoints

### Dashboard
```
GET /artist/dashboard
```
Returns comprehensive statistics:
- Total tracks, views, ratings
- Monthly analytics (6 months)
- Recent activity
- Top rated and most viewed tracks

### Music Management
```
GET /artist/music?per_page=15&sort_by=views&sort_order=desc
```
List artist's uploaded music with stats

```
GET /artist/music/{id}/stats
```
Detailed song statistics:
- Ratings breakdown by score
- Daily play counts (30 days)
- Recent plays with user info
- All ratings with details

```
PATCH /artist/music/{id}
```
Update song details:
```json
{
  "title": "New Title",
  "genre": "Jazz",
  "description": "Updated description",
  "lyrics": "Song lyrics...",
  "release_date": "2025-01-01"
}
```

```
DELETE /artist/music/{id}
```
Delete song and associated files

---

## User Role Request API

### Submit Request
```
POST /role-requests
```
Submit role change request:
```json
{
  "requested_role": "artist",
  "reason": "I am a professional musician with 5 years experience..."
}
```

### View Requests
```
GET /role-requests
```
View all own role change requests

```
GET /role-requests/{id}
```
View specific request details

```
PATCH /role-requests/{id}/cancel
```
Cancel pending request

---

## Response Format

### Success Response
```json
{
  "success": true,
  "data": {...},
  "message": "Operation completed successfully"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {...}
}
```

---

## Role Change Workflow

1. **User** submits role change request with reason
2. **Admin** reviews request in admin panel
3. **Admin** approves or rejects with notes
4. On approval, user role is automatically updated
5. User receives response with admin decision

---

## Statistics Available

### Admin Dashboard
- User counts by role
- Pending requests count
- Recent activity overview

### Artist Dashboard
- Track performance metrics
- Monthly analytics
- Listener engagement data
- Rating distributions
- Play count trends

---

## Security Features

- Role-based middleware protection
- User ownership validation
- Admin-only user management
- Artist-only music management
- Request status tracking
- File cleanup on deletion

---

## Database Tables

### role_change_requests
- Tracks user role upgrade requests
- Includes admin review workflow
- Status: pending, approved, rejected, cancelled

### Enhanced user relationships
- Role change requests
- Uploaded music tracking
- Admin activity logging
