# Enterprise REST API v1 — Pabbly Connect Integration Guide

Base URL: `https://yourdomain.com/cms/api_v1/endpoints.php`

---

## Authentication

All requests must include an API Key as a Bearer token in the `Authorization` header.

```
Authorization: Bearer YOUR_API_KEY_HERE
```

> Get your API key from: **Admin → Users → Edit User → Generate API Key**
>
> Alternatively, pass as a query param for testing: `?api_key=YOUR_KEY`

---

## Testing the Connection

**Request:**
```
GET ?resource=ping
```
**Response:**
```json
{
  "status": "success",
  "data": {
    "user": "Admin User",
    "role": "Admin",
    "timestamp": "2026-04-24T10:00:00+05:30",
    "available_resources": ["projects", "leads", "employees", "attendance", "expenses", "leaves", "kpi", "payroll"]
  }
}
```

---

## GET Endpoints (Pabbly Reads Data)

### 1. CRM Leads — `?resource=leads`
Fetch all CRM leads. Optional filter: `&stage=Prospect`

**Stages:** `New` | `Prospect` | `Qualified` | `Proposal` | `Won` | `Lost`

```
GET ?resource=leads
GET ?resource=leads&stage=New
```

---

### 2. Projects — `?resource=projects`
Returns all projects (Admin sees all; others see only assigned projects).

```
GET ?resource=projects
```

---

### 3. Employees — `?resource=employees`
Returns all active employees. **Admin/Manager only.**

```
GET ?resource=employees
```

---

### 4. Attendance — `?resource=attendance`
Returns attendance records. Admins see all; users see their own. Optional: `&limit=50`

```
GET ?resource=attendance
GET ?resource=attendance&limit=50
```

---

### 5. Expenses — `?resource=expenses`
Returns all expenses. Optional: `&status=Pending`

**Statuses:** `Pending` | `Approved` | `Rejected`

```
GET ?resource=expenses
GET ?resource=expenses&status=Pending
```

---

### 6. Leaves / PTO — `?resource=leaves`
Returns leave requests. Optional: `&status=Pending`

```
GET ?resource=leaves
GET ?resource=leaves&status=Pending
```

---

### 7. KPI Targets — `?resource=kpi`
Returns KPI targets. Admins see all; employees see their own.

```
GET ?resource=kpi
```

---

### 8. Payroll — `?resource=payroll`
Returns payroll run data. **Admin/Manager only.** Optional: `&period=2026-04`

```
GET ?resource=payroll
GET ?resource=payroll&period=2026-04
```

---

## POST Endpoints (Pabbly Sends Data)

All POST bodies should be sent as **JSON** (`Content-Type: application/json`).

---

### 1. Create CRM Lead — `?resource=leads`
Use this in Pabbly to push a new lead from a website form, Facebook Lead Ad, etc.

**Request Body:**
```json
{
  "lead_name": "John Doe",
  "email": "john@company.com",
  "phone": "+1234567890",
  "company": "Acme Corp",
  "value": 5000,
  "source": "Facebook Ads",
  "assigned_to": "sales_rep_login_id"
}
```
**Response:**
```json
{
  "status": "success",
  "data": {
    "id": 42,
    "lead_name": "John Doe",
    "message": "Lead created successfully."
  }
}
```

---

### 2. Log Attendance — `?resource=attendance_log`

**Request Body:**
```json
{
  "date": "2026-04-24"
}
```

---

### 3. Submit Leave Request — `?resource=leaves`

**Request Body:**
```json
{
  "start_date": "2026-05-01",
  "end_date": "2026-05-03",
  "leave_type": "Annual Leave",
  "reason": "Family event"
}
```

---

## Pabbly Connect Setup Guide

### Step 1: Choose a Trigger App
Examples: Google Forms, Facebook Lead Ads, Typeform, JotForm

### Step 2: Add Action App = **Webhooks by Pabbly**
- **Method:** `POST`
- **URL:** `https://yourdomain.com/cms/api_v1/endpoints.php?resource=leads`
- **Headers:**
  - `Authorization`: `Bearer YOUR_API_KEY`
  - `Content-Type`: `application/json`
- **Body:** Map fields from your trigger to the JSON body format above

### Step 3: Test & Verify
After running the Pabbly test, check the **CRM** module in your system to confirm the lead was created.

---

## Error Codes

| Code | Meaning |
|---|---|
| `401` | Missing API Key |
| `403` | Invalid Key or insufficient permissions |
| `404` | Unknown resource name |
| `405` | HTTP method not supported |
| `409` | Conflict (e.g. duplicate attendance record) |
