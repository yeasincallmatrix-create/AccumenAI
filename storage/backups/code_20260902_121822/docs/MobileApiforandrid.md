# AccumenAI - Mobile API Documentation for Android

## Base URL

```
http://your-domain.com/api
```

All endpoints are prefixed with `/api`. Responses are always JSON.

---

## Authentication

### Token-Based Auth (Laravel Sanctum)

Every protected endpoint requires a **Bearer Token** in the `Authorization` header.

```
Authorization: Bearer {your-token-here}
```

**How to get a token:** Call the Login endpoint with email, password, and institute_id. The response includes a `token` field. Use this token in all subsequent requests.

**Why:** Token-based auth is required for mobile apps because mobile apps do not use browser cookies. Each token is scoped to a specific institute and branch, ensuring data isolation between tenants.

---

## Response Format

### Success Response (Single Resource)

```json
{
  "success": true,
  "message": "Ok",
  "data": { ... }
}
```

### Success Response (Paginated List)

```json
{
  "success": true,
  "message": "Ok",
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Invalid credentials."
}
```

### Validation Error Response

```json
{
  "success": false,
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

---

## HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200  | Success |
| 201  | Created |
| 401  | Unauthenticated - token missing or invalid |
| 403  | Forbidden - no access to this resource |
| 404  | Resource not found |
| 422  | Validation failed |
| 429  | Too many requests - rate limited |
| 500  | Server error |

---

## Endpoints Overview

| # | Method | Endpoint | Auth | Purpose |
|---|--------|----------|------|---------|
| 1 | POST | `/api/login` | No | Login and get token |
| 2 | POST | `/api/logout` | Yes | Revoke token |
| 3 | GET | `/api/profile` | Yes | Get user profile |
| 4 | GET | `/api/institute` | Yes | Get institute details |
| 5 | GET | `/api/branches` | Yes | List branches |
| 6 | GET | `/api/students` | Yes | List students (paginated) |
| 7 | GET | `/api/students/{id}` | Yes | Get student details |
| 8 | GET | `/api/courses` | Yes | List courses (paginated) |
| 9 | GET | `/api/courses/{id}` | Yes | Get course details |
| 10 | GET | `/api/batches` | Yes | List batches (paginated) |
| 11 | GET | `/api/batches/{id}` | Yes | Get batch details |
| 12 | GET | `/api/enrollments` | Yes | List enrollments (paginated) |
| 13 | GET | `/api/attendance` | Yes | List attendance records |
| 14 | POST | `/api/attendance` | Yes | Mark / update attendance |
| 15 | GET | `/api/assessments` | Yes | List exams (paginated) |
| 16 | GET | `/api/assessments/{id}/results` | Yes | Get exam results |
| 17 | GET | `/api/invoices` | Yes | List invoices (paginated) |
| 18 | GET | `/api/invoices/{id}` | Yes | Get invoice details |
| 19 | GET | `/api/payments` | Yes | List payments (paginated) |
| 20 | GET | `/api/crm/contacts` | Yes | List CRM contacts |
| 21 | GET | `/api/crm/contacts/{id}` | Yes | Get CRM contact details |
| 22 | GET | `/api/crm/leads` | Yes | List CRM leads |
| 23 | GET | `/api/crm/leads/{id}` | Yes | Get CRM lead details |
| 24 | GET | `/api/certificates` | Yes | List certificates |
| 25 | GET | `/api/verify/certificate/{number}` | No | Verify certificate (public) |
| 26 | GET | `/api/notifications` | Yes | List notifications |
| 27 | POST | `/api/notifications/{id}/read` | Yes | Mark notification read |

---

## Detailed Endpoint Documentation

---

### 1. Login

**POST** `/api/login` | **Auth Required:** No

**Purpose:** Authenticate a user and receive a Bearer token. This is the entry point for all mobile app sessions. The token is tied to the user's institute and branch.

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | User email address |
| password | string | Yes | User password |
| institute_id | integer | Yes | Institute ID the user belongs to |

**Request Example:**

```json
POST /api/login
Content-Type: application/json

{
  "email": "admin@example.com",
  "password": "secret12345",
  "institute_id": 1
}
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": {
      "id": 1,
      "name": "Admin User",
      "email": "admin@example.com",
      "account_type": "owner",
      "institute_id": 1,
      "branch_id": null,
      "email_verified_at": "2026-01-15T00:00:00.000000Z",
      "last_login_at": "2026-08-21T10:00:00.000000Z"
    },
    "token": "1|abc123def456...",
    "institute": {
      "id": 1,
      "name": "My Academy",
      "slug": "my-academy",
      "email": "info@myacademy.com",
      "phone": "+8801700000000",
      "website": "https://myacademy.com",
      "logo": "https://myacademy.com/logo.png",
      "industry": "education"
    }
  }
}
```

**Error Response (401):**

```json
{ "success": false, "message": "Invalid credentials." }
```

**Error Response (423):**

```json
{ "success": false, "message": "Account is locked. Try again later." }
```

**Why:** Mobile apps need a way to authenticate users and obtain a persistent token. Unlike web apps that use session cookies, mobile apps store the token locally and send it with every request. The token also carries institute_id and branch_id permissions.

---

### 2. Logout

**POST** `/api/logout` | **Auth Required:** Yes

**Purpose:** Revoke the current access token. Always call this when the user taps "Logout" in the app to ensure the token is destroyed server-side.

**Request Headers:** `Authorization: Bearer {token}`

**Request Body:** None

**Success Response (200):**

```json
{ "success": true, "message": "Logged out successfully.", "data": null }
```

**Why:** Simply deleting the token from the app's local storage is not enough. The server must also invalidate the token so it cannot be used if intercepted.

---

### 3. Get Profile

**GET** `/api/profile` | **Auth Required:** Yes

**Purpose:** Fetch the currently authenticated user's profile. Use this to display the user's name, email, and account details on the profile screen or settings page.

**Request Headers:** `Authorization: Bearer {token}`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "account_type": "owner",
    "institute_id": 1,
    "branch_id": null,
    "email_verified_at": "2026-01-15T00:00:00.000000Z",
    "last_login_at": "2026-08-21T10:00:00.000000Z"
  }
}
```

**Why:** The app needs to display user information on profile screens, settings pages, and navigation headers. This endpoint provides a clean data structure without exposing sensitive fields like password_hash.

---

### 4. Get Institute Details

**GET** `/api/institute` | **Auth Required:** Yes

**Purpose:** Fetch the details of the institute the authenticated user belongs to. Use this to display the institute name, logo, and contact info on the home screen or about page.

**Request Headers:** `Authorization: Bearer {token}`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "name": "My Academy",
    "slug": "my-academy",
    "email": "info@myacademy.com",
    "phone": "+8801700000000",
    "website": "https://myacademy.com",
    "logo": "https://myacademy.com/logo.png",
    "industry": "education"
  }
}
```

**Why:** The mobile app needs to show institute branding (name, logo) across screens. This avoids hardcoding institute data and always returns fresh information.

---

### 5. Get Branches

**GET** `/api/branches` | **Auth Required:** Yes

**Purpose:** List all branches belonging to the user's institute. Use this to populate a branch selector/filter dropdown or to show a list of campus locations.

**Request Headers:** `Authorization: Bearer {token}`

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "name": "Main Campus",
      "code": "MC01",
      "address": "123 Main St, Dhaka",
      "phone": "+8801700000001",
      "email": "main@myacademy.com",
      "status": "active"
    }
  ]
}
```

**Why:** Institutes with multiple branches need to let users filter data by branch. This endpoint provides the list of branches for UI dropdowns and filtering logic.

---

### 6. List Students

**GET** `/api/students` | **Auth Required:** Yes

**Purpose:** Get a paginated list of all students. Use this for the student directory screen, search functionality, and student management views.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| search | string | No | - | Search by name, email, phone, or student ID |
| status | string | No | - | Filter: `active`, `inactive`, `graduated` |
| branch_id | integer | No | - | Filter by branch ID |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/students
GET /api/students?search=ahmed
GET /api/students?status=active&branch_id=1&per_page=50
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "student_id_number": "STU001",
      "registration_number": "REG001",
      "roll_number": "R001",
      "full_name": "Ahmed Hassan",
      "first_name": "Ahmed",
      "last_name": "Hassan",
      "email": "ahmed@student.com",
      "phone": "01711234567",
      "gender": "male",
      "dob": "2000-05-15",
      "status": "active",
      "admission_status": "approved",
      "admission_date": "2026-01-10",
      "photo": "https://...",
      "father_name": "Hassan Ali",
      "mother_name": "Fatima Ali",
      "blood_group": "O+",
      "religion": "Islam",
      "nationality": "Bangladeshi",
      "present_address": "Dhaka, Bangladesh",
      "permanent_address": "Dhaka, Bangladesh",
      "guardian_phone": "01711234568",
      "emergency_contact_name": "Hassan Ali",
      "emergency_contact_phone": "01711234568",
      "branch_id": 1,
      "course_id": 1,
      "academic_year_id": 1
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

**Why:** The student list is the most used screen in any education management app. Search, status filtering, and branch filtering allow users to quickly find specific students. Pagination prevents loading all records at once.

---

### 7. Get Student Details

**GET** `/api/students/{id}` | **Auth Required:** Yes

**Purpose:** Fetch complete details of a single student including their enrollments. Use this for the student profile/detail screen.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Student ID

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "student_id_number": "STU001",
    "full_name": "Ahmed Hassan",
    "email": "ahmed@student.com",
    "phone": "01711234567",
    "gender": "male",
    "dob": "2000-05-15",
    "status": "active",
    "enrollments": [
      {
        "id": 1,
        "student_id": 1,
        "course_id": 1,
        "batch_id": 1,
        "enrollment_date": "2026-01-10",
        "course": { "id": 1, "name": "SSC Preparation" },
        "batch": { "id": 1, "name": "Batch 2026-A", "status": "running" }
      }
    ],
    "branch": { "id": 1, "name": "Main Campus" }
  }
}
```

**Error Response (404):** `{ "success": false, "message": "Student not found." }`

**Why:** Tapping on a student in the list opens a detailed profile showing personal info, enrolled courses, batches, and contact details. This endpoint loads all related data in one request.

---

### 8. List Courses

**GET** `/api/courses` | **Auth Required:** Yes

**Purpose:** Get a paginated list of courses offered by the institute. Use this for course selection screens, catalogs, and filter dropdowns.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| search | string | No | - | Search by course name or code |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/courses
GET /api/courses?search=SSC
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "name": "SSC Preparation",
      "code": "SSC-01",
      "description": "Full SSC preparation course",
      "status": "active",
      "category_id": 1,
      "duration": "2 years"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 5 }
}
```

**Why:** The app needs to display available courses for browsing, enrollment, and filtering students by course. Search helps users find courses quickly.

---

### 9. Get Course Details

**GET** `/api/courses/{id}` | **Auth Required:** Yes

**Purpose:** Fetch complete details of a single course including its batches and subjects. Use this for the course detail screen.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Course ID

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "name": "SSC Preparation",
    "code": "SSC-01",
    "description": "Full SSC preparation course",
    "status": "active",
    "category_id": 1,
    "duration": "2 years"
  }
}
```

**Why:** When a user taps on a course, the app shows full details including description, duration, and related batches/subjects.

---

### 10. List Batches

**GET** `/api/batches` | **Auth Required:** Yes

**Purpose:** Get a paginated list of batches. Use this for batch selection, attendance screens, and exam scheduling views.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| course_id | integer | No | - | Filter by course ID |
| status | string | No | - | Filter: `upcoming`, `running`, `completed`, `cancelled`, `archived` |
| branch_id | integer | No | - | Filter by branch ID |
| search | string | No | - | Search by batch name or code |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/batches
GET /api/batches?course_id=1&status=running
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "name": "Batch 2026-A",
      "code": "B26A",
      "status": "running",
      "course_id": 1,
      "academic_year_id": 1,
      "branch_id": 1,
      "start_date": "2026-01-15",
      "end_date": null,
      "capacity": 40
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 3 }
}
```

**Why:** Batches group students by class schedule. The app needs batch lists for attendance marking, exam management, and enrollment.

---

### 11. Get Batch Details

**GET** `/api/batches/{id}` | **Auth Required:** Yes

**Purpose:** Fetch complete details of a single batch including its course, academic year, and branch.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Batch ID

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "name": "Batch 2026-A",
    "code": "B26A",
    "status": "running",
    "course_id": 1,
    "academic_year_id": 1,
    "branch_id": 1,
    "start_date": "2026-01-15",
    "end_date": null,
    "capacity": 40
  }
}
```

**Why:** The batch detail screen shows schedule, capacity, and status information in a single call.

---

### 12. List Enrollments

**GET** `/api/enrollments` | **Auth Required:** Yes

**Purpose:** Get a paginated list of student enrollments linking students to courses and batches. Needed for attendance tracking, fee generation, and academic reporting.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| student_id | integer | No | - | Filter by student ID |
| batch_id | integer | No | - | Filter by batch ID |
| course_id | integer | No | - | Filter by course ID |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/enrollments
GET /api/enrollments?student_id=1
GET /api/enrollments?batch_id=1&course_id=1
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "student_id": 1,
      "course_id": 1,
      "batch_id": 1,
      "enrollment_date": "2026-01-10",
      "status": null,
      "student": { "id": 1, "full_name": "Ahmed Hassan", "student_id_number": "STU001" },
      "course": { "id": 1, "name": "SSC Preparation" },
      "batch": { "id": 1, "name": "Batch 2026-A" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 20, "total": 35 }
}
```

**Why:** Enrollments are the core link between students and academic programs. This data drives attendance, fees, and reports. Filtering by student, batch, or course narrows results.

---

### 13. List Attendance Records

**GET** `/api/attendance` | **Auth Required:** Yes

**Purpose:** Get paginated attendance records. Use for attendance history, reports, and tracking views.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| student_id | integer | No | - | Filter by student ID |
| batch_id | integer | No | - | Filter by batch ID |
| date | string | No | - | Filter by date (YYYY-MM-DD) |
| status | string | No | - | Filter: `present`, `absent`, `late`, `leave` |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/attendance?batch_id=1&date=2026-08-21
GET /api/attendance?student_id=1&status=absent
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "student_id": 1,
      "batch_id": 1,
      "date": "2026-08-21",
      "status": "present",
      "marked_by": 1,
      "student": { "id": 1, "full_name": "Ahmed Hassan" },
      "batch": { "id": 1, "name": "Batch 2026-A" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 1 }
}
```

**Why:** Teachers and admins need to view attendance history. Filtering by date, batch, and status makes it easy to find specific records.

---

### 14. Mark / Update Attendance

**POST** `/api/attendance` | **Auth Required:** Yes

**Purpose:** Create a new attendance record or update an existing one for the same student/batch/date. If a record already exists, it updates the status (upsert behavior prevents duplicates).

**Request Headers:** `Authorization: Bearer {token}`, `Content-Type: application/json`

**Request Body:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| student_id | integer | Yes | Student ID |
| batch_id | integer | Yes | Batch ID |
| date | string | Yes | Date in YYYY-MM-DD format |
| status | string | Yes | One of: `present`, `absent`, `late`, `leave` |

**Request Example:**

```json
POST /api/attendance
{
  "student_id": 1,
  "batch_id": 1,
  "date": "2026-08-21",
  "status": "present"
}
```

**Success Response - New Record (201):**

```json
{
  "success": true,
  "message": "Attendance recorded.",
  "data": {
    "id": 1,
    "student_id": 1,
    "batch_id": 1,
    "date": "2026-08-21",
    "status": "present",
    "marked_by": 1,
    "student": { "id": 1, "full_name": "Ahmed Hassan" },
    "batch": { "id": 1, "name": "Batch 2026-A" }
  }
}
```

**Success Response - Updated Record (200):**

```json
{
  "success": true,
  "message": "Attendance updated.",
  "data": { ... }
}
```

**Validation Error (422):**

```json
{
  "success": false,
  "message": "The student id field is required.",
  "errors": {
    "student_id": ["The student id field is required."],
    "batch_id": ["The batch id field is required."],
    "date": ["The date field is required."],
    "status": ["The status field is required."]
  }
}
```

**Why:** Attendance marking is a daily task for teachers. The upsert behavior allows correcting mistakes without creating duplicate records.

---

### 15. List Assessments (Exams)

**GET** `/api/assessments` | **Auth Required:** Yes

**Purpose:** Get a paginated list of exams/assessments. Use for the exam list screen and scheduling views.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| batch_id | integer | No | - | Filter by batch ID |
| course_id | integer | No | - | Filter by course ID |
| search | string | No | - | Search by exam title |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/assessments
GET /api/assessments?batch_id=1
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "title": "Midterm Exam",
      "batch_id": 1,
      "course_id": 1,
      "full_marks": 100,
      "pass_marks": 40
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 5 }
}
```

**Why:** Students and teachers need to see upcoming and past exams. Filtering by batch and course narrows the list.

---

### 16. Get Exam Results

**GET** `/api/assessments/{id}/results` | **Auth Required:** Yes

**Purpose:** Get paginated results for a specific exam showing each student's marks.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Exam ID

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| student_id | integer | No | - | Filter by specific student |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/assessments/1/results
GET /api/assessments/1/results?student_id=1
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "exam_id": 1,
      "student_id": 1,
      "marks_obtained": 85,
      "student": { "id": 1, "full_name": "Ahmed Hassan" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 30 }
}
```

**Why:** After an exam, teachers need to see student performance. Students need their own results. The student_id filter allows fetching results for a single student.

---

### 17. List Invoices

**GET** `/api/invoices` | **Auth Required:** Yes

**Purpose:** Get a paginated list of invoices. Use for the billing screen, fee tracking, and payment history views.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| student_id | integer | No | - | Filter by student ID |
| status | string | No | - | Filter: `pending`, `paid`, `partial`, `overdue`, `cancelled` |
| type | string | No | - | Filter by invoice type |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/invoices
GET /api/invoices?student_id=1&status=pending
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "invoice_number": "INV-2026-001",
      "student_id": 1,
      "type": "tuition",
      "status": "pending",
      "total_amount": 15000.00,
      "paid_amount": 0,
      "due_amount": 15000.00,
      "currency_id": 1,
      "due_date": "2026-02-28",
      "student": { "id": 1, "full_name": "Ahmed Hassan" },
      "items": [
        { "id": 1, "description": "Tuition Fee", "amount": 15000.00 }
      ]
    }
  ],
  "meta": { "current_page": 1, "last_page": 2, "per_page": 20, "total": 30 }
}
```

**Why:** The finance screen needs to show invoices with amounts and payment status. Filtering by student and status helps users find specific invoices.

---

### 18. Get Invoice Details

**GET** `/api/invoices/{id}` | **Auth Required:** Yes

**Purpose:** Fetch complete details of a single invoice including items and installment payments.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Invoice ID

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "invoice_number": "INV-2026-001",
    "student_id": 1,
    "type": "tuition",
    "status": "partial",
    "total_amount": 15000.00,
    "paid_amount": 5000.00,
    "due_amount": 10000.00,
    "currency_id": 1,
    "due_date": "2026-02-28",
    "student": { "id": 1, "full_name": "Ahmed Hassan" },
    "items": [
      { "id": 1, "description": "Tuition Fee", "amount": 15000.00 }
    ]
  }
}
```

**Error Response (404):** `{ "success": false, "message": "Invoice not found." }`

**Why:** The invoice detail screen shows full breakdown of charges, payments made, and remaining balance.

---

### 19. List Payments

**GET** `/api/payments` | **Auth Required:** Yes

**Purpose:** Get a paginated list of all payments. Use for payment history and financial reporting.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| invoice_id | integer | No | - | Filter by invoice ID |
| student_id | integer | No | - | Filter by student ID |
| payment_method | string | No | - | Filter by method: `cash`, `bank`, `online`, `mobile` |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/payments
GET /api/payments?student_id=1&payment_method=online
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "invoice_id": 1,
      "student_id": 1,
      "amount": 5000.00,
      "payment_method": "cash",
      "transaction_id": null,
      "paid_at": "2026-02-15T10:30:00.000000Z",
      "received_by": 1
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 10 }
}
```

**Why:** The payment history screen needs to show all payments with method, amount, and date. Filtering by student, invoice, or method helps find specific transactions.

---

### 20. List CRM Contacts

**GET** `/api/crm/contacts` | **Auth Required:** Yes

**Purpose:** Get a paginated list of CRM contacts (prospective students, parents, partners). Use for the CRM contact directory.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| search | string | No | - | Search by first name, last name, email, or phone |
| branch_id | integer | No | - | Filter by branch ID |
| contact_type_id | integer | No | - | Filter by contact type ID |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/crm/contacts
GET /api/crm/contacts?search=ahmed&contact_type_id=1
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "first_name": "Ahmed",
      "last_name": "Khan",
      "email": "ahmed@example.com",
      "phone": "01711234567",
      "contact_type": { "id": 1, "name": "Prospective Student" },
      "organization": { "id": 1, "name": "ABC Corp" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 25 }
}
```

**Why:** The CRM module manages prospective students and contacts. Search and type filtering help sales teams find and manage leads.

---

### 21. Get CRM Contact Details

**GET** `/api/crm/contacts/{id}` | **Auth Required:** Yes

**Purpose:** Fetch complete details of a single CRM contact including their leads, activities, and tasks.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Contact ID

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Khan",
    "email": "ahmed@example.com",
    "phone": "01711234567",
    "contact_type": { "id": 1, "name": "Prospective Student" },
    "organization": { "id": 1, "name": "ABC Corp" },
    "leads": [],
    "activities": [],
    "tasks": []
  }
}
```

**Error Response (404):** `{ "success": false, "message": "Contact not found." }`

**Why:** The contact detail screen shows all interactions, leads, and tasks associated with a contact in one view.

---

### 22. List CRM Leads

**GET** `/api/crm/leads` | **Auth Required:** Yes

**Purpose:** Get a paginated list of CRM leads (sales pipeline). Use for the lead management screen.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| search | string | No | - | Search by first name, last name, email, or phone |
| status_id | integer | No | - | Filter by lead status ID |
| branch_id | integer | No | - | Filter by branch ID |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/crm/leads
GET /api/crm/leads?status_id=1&branch_id=1
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "first_name": "Ahmed",
      "last_name": "Khan",
      "email": "ahmed@example.com",
      "phone": "01711234567",
      "status": { "id": 1, "name": "New" },
      "source": { "id": 1, "name": "Website" },
      "organization": { "id": 1, "name": "ABC Corp" },
      "contact": { "id": 1, "first_name": "Ahmed", "last_name": "Khan" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 15 }
}
```

**Why:** The sales team needs to track leads through the pipeline. Filtering by status and branch helps manage the sales process.

---

### 23. Get CRM Lead Details

**GET** `/api/crm/leads/{id}` | **Auth Required:** Yes

**Purpose:** Fetch complete details of a single CRM lead including status, source, activities, and tasks.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Lead ID

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "id": 1,
    "first_name": "Ahmed",
    "last_name": "Khan",
    "email": "ahmed@example.com",
    "phone": "01711234567",
    "status": { "id": 1, "name": "New" },
    "source": { "id": 1, "name": "Website" },
    "organization": { "id": 1, "name": "ABC Corp" },
    "contact": { "id": 1, "first_name": "Ahmed" },
    "activities": [],
    "tasks": []
  }
}
```

**Error Response (404):** `{ "success": false, "message": "Lead not found." }`

**Why:** The lead detail screen shows the full history of interactions, notes, and tasks for a specific lead.

---

### 24. List Certificates

**GET** `/api/certificates` | **Auth Required:** Yes

**Purpose:** Get a paginated list of issued certificates. Use for the certificate management screen.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| student_id | integer | No | - | Filter by student ID |
| status | string | No | - | Filter by certificate status |
| search | string | No | - | Search by certificate number |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/certificates
GET /api/certificates?student_id=1
GET /api/certificates?search=CERT-2026
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "certificate_number": "CERT-2026-001",
      "student_id": 1,
      "course_id": 1,
      "batch_id": 1,
      "certificate_type_id": 1,
      "status": "issued",
      "issued_date": "2026-06-30",
      "student": { "id": 1, "full_name": "Ahmed Hassan" },
      "course": { "id": 1, "name": "SSC Preparation" }
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 20 }
}
```

**Why:** Schools need to track which certificates have been issued to which students. Search by certificate number enables quick verification.

---

### 25. Verify Certificate (Public)

**GET** `/api/verify/certificate/{number}` | **Auth Required:** No

**Purpose:** Publicly verify a certificate by its number. Anyone can use this endpoint to check if a certificate is authentic. Use this for the certificate verification screen or public verification page.

**Request Headers:** None required

**URL Parameters:** `number` (string) - Certificate number

**Request Example:**

```
GET /api/verify/certificate/CERT-2026-001
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": {
    "certificate_number": "CERT-2026-001",
    "student_name": "Ahmed Hassan",
    "course_name": "SSC Preparation",
    "batch_name": "Batch 2026-A",
    "type": "Completion Certificate",
    "status": "issued",
    "issue_date": "2026-06-30"
  }
}
```

**Error Response (404):**

```json
{ "success": false, "message": "Certificate not found." }
```

**Why:** This is the only public endpoint (no auth required). It allows employers, universities, or anyone to verify a certificate's authenticity by entering the certificate number. This prevents certificate fraud.

---

### 26. List Notifications

**GET** `/api/notifications` | **Auth Required:** Yes

**Purpose:** Get a paginated list of notifications for the authenticated user. Use for the notifications bell/screen in the app.

**Request Headers:** `Authorization: Bearer {token}`

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| unread_only | boolean | No | false | Set to `true` to show only unread notifications |
| per_page | integer | No | 20 | Results per page (max 100) |

**Request Examples:**

```
GET /api/notifications
GET /api/notifications?unread_only=true
```

**Success Response (200):**

```json
{
  "success": true,
  "message": "Ok",
  "data": [
    {
      "id": 1,
      "title": "New Admission",
      "body": "A new student has been admitted to SSC Batch.",
      "type": "admission",
      "data": { "student_id": 15 },
      "read_at": null,
      "created_at": "2026-08-21T10:00:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 1, "per_page": 20, "total": 5 }
}
```

**Why:** Users need to see real-time notifications about new admissions, payments, attendance alerts, and system updates. The unread_only filter helps show a badge count on the notification icon.

---

### 27. Mark Notification Read

**POST** `/api/notifications/{id}/read` | **Auth Required:** Yes

**Purpose:** Mark a specific notification as read. Use this when the user taps on a notification to view it.

**Request Headers:** `Authorization: Bearer {token}`

**URL Parameters:** `id` (integer) - Notification ID

**Request Body:** None

**Success Response (200):**

```json
{ "success": true, "message": "Notification marked as read.", "data": null }
```

**Error Response (404):** `{ "success": false, "message": "Notification not found." }`

**Why:** The app needs to track which notifications the user has seen so the unread badge count is accurate. This endpoint marks a notification as read for the current user.

---

## Pagination

All list endpoints support pagination via the `per_page` query parameter (default: 20, max: 100).

**Pagination metadata:**

| Field | Description |
|-------|-------------|
| current_page | Current page number |
| last_page | Total number of pages |
| per_page | Number of items per page |
| total | Total number of items |

**Navigation:** To get page 2 with 50 items per page:

```
GET /api/students?per_page=50&page=2
```

---

## Security

### Tenant Isolation

Every authenticated request is scoped to the user's institute and branch. The `ensure.institute.context` middleware reads the institute_id and branch_id from the authenticated token (NOT from client input). This means:

- A user from Institute A can NEVER see data from Institute B
- A branch-scoped user can only see their own branch's data
- Institute owners/admins see all branches within their institute

### Token Scoping

Each token carries two permission abilities:

- `institute_id:{id}` - The institute this token belongs to
- `branch_id:{id}` - The branch this token is scoped to (null for owners)

### Rate Limiting

All API endpoints are rate-limited to prevent abuse. If you receive a `429` response, wait before retrying.

### Sensitive Data

The following fields are hidden from all API responses:

- `password_hash` - Never exposed
- `nid_number`, `birth_cert_number`, `passport_number` - National ID data hidden from list endpoints
- `crm_contact_id`, `crm_lead_id` - Internal CRM links hidden

---

## Android Implementation Checklist

1. **Login Flow:** POST to `/api/login`, store token securely in Android Keystore
2. **Add Token to Requests:** Set `Authorization: Bearer {token}` header on all API calls
3. **Handle 401:** If any request returns 401, redirect to login screen
4. **Handle Pagination:** Use `meta.last_page` to implement infinite scroll or pagination
5. **Handle Errors:** Check `success` field in every response. If `false`, show `message` to user
6. **Certificate Verification:** Use the public `/api/verify/certificate/{number}` endpoint (no auth needed)
7. **Logout:** Always call `/api/logout` before clearing local token
8. **Offline Support:** Cache last-seen data in Room database for offline viewing
