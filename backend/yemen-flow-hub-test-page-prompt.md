# Yemen Flow Hub — API Testing Page Prompt (for Codex)

## How to use

Paste this entire prompt into Codex **after** Modules 1–9 and the seeder are complete. Codex will generate a single self-contained Blade page that lets you exercise every API endpoint visually, in Arabic, with quick-login shortcuts from the seeded users.

---

# 🎯 Goal

Build **one Blade page** at route `/test-api` that acts as a complete interactive testing console for the Yemen Flow Hub API. The page must be **fully in Arabic**, RTL, visually clean, and require zero extra setup beyond the seeded database.

The page is for **internal development only** — it bypasses normal frontend flows so you can quickly test every endpoint, every role, every workflow transition.

---

# 📁 Files to create

1. `app/Http/Controllers/TestApiController.php` — single controller with one method.
2. `resources/views/test_api.blade.php` — the full testing page.
3. Route in `routes/web.php`:
   ```php
   Route::get('/test-api', [TestApiController::class, 'index'])->name('test_api');
   ```

**Important:** the route is on `web.php`, NOT `api.php`. The page is a regular Blade view; it calls the `/api/*` endpoints via JavaScript `fetch()`.

---

# 🧩 TestApiController

```php
namespace App\Http\Controllers;

use App\Models\User;

class TestApiController extends Controller
{
    public function index()
    {
        $users = User::with('bank')
            ->where('is_active', true)
            ->orderBy('role')
            ->orderBy('bank_id')
            ->get()
            ->map(fn($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'email'     => $u->email,
                'role'      => $u->role->value,
                'role_label'=> $u->role->label(),
                'bank_name' => $u->bank?->name,
                'bank_code' => $u->bank?->code,
            ]);

        return view('test_api', compact('users'));
    }
}
```

The view receives `$users` so the login section can render them as a clickable list.

---

# 🎨 View design — `resources/views/test_api.blade.php`

## Page-level rules

- **Standalone HTML** — do NOT extend a layout. Include `<!DOCTYPE html>`, `<html dir="rtl" lang="ar">`, the full `<head>`.
- **Tailwind via CDN**: `<script src="https://cdn.tailwindcss.com"></script>` for fast setup. (No build step.)
- **Font**: Use Cairo or Tajawal from Google Fonts for Arabic readability.
  ```html
  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
  ```
- **All visible text in Arabic.** Section headers, button labels, table headers, status messages — everything.
- **RTL layout** throughout. Tailwind's RTL works fine when `dir="rtl"` is set on `<html>`.
- Use a soft, professional color palette: slate/blue/emerald/rose. No flashy colors.
- The page must be usable without any console — every response renders on the page itself.

## Layout structure

The page is divided into 4 main sections, stacked vertically (or 2-column on wide screens):

```
┌─────────────────────────────────────────────────┐
│ Header: لوحة اختبار واجهات يمن فلو هَب         │
│ Status bar: المستخدم الحالي + الدور + زر خروج │
└─────────────────────────────────────────────────┘

┌─────────────────┬───────────────────────────────┐
│ القسم 1:        │ القسم 3:                      │
│ تسجيل دخول سريع│ نتيجة آخر طلب                 │
│ (User list)     │ (Response panel)              │
├─────────────────┤                               │
│ القسم 2:        │                               │
│ مجموعات الـ API │                               │
│ (Endpoint tabs) │                               │
└─────────────────┴───────────────────────────────┘
```

---

# 🔐 Section 1 — تسجيل دخول سريع (Quick Login)

Render a list/table of all seeded users grouped by role. Each row shows:
- اسم المستخدم (name)
- البريد الإلكتروني (email)
- الدور (role label in Arabic+English, e.g. `"موظف إدخال البيانات / Data Entry"`)
- البنك (bank name, or `"البنك المركزي"` if CBY user)
- زر **"دخول"** (login button)

Clicking "دخول" sends:
```js
POST /api/auth/login
{ "email": "<that email>", "password": "password" }
```

Authentication uses **Bearer token** mode (since this page is on `/web` but calling `/api`). On success:
1. Store the token in `localStorage` under key `yfh_api_token`.
2. Store user info in `localStorage` under key `yfh_current_user`.
3. Update the top status bar to show: `"المستخدم الحالي: {name} ({role_label}) — {bank_name}"`.
4. Highlight the active user row.
5. Show success toast: `"تم تسجيل الدخول بنجاح"`.

Logout button calls `POST /api/auth/logout`, clears localStorage, refreshes the status bar.

All subsequent API calls in section 2 automatically include `Authorization: Bearer <token>`.

**Filter input** above the user list: instant client-side filter by name/email/role.

---

# 🧪 Section 2 — مجموعات الـ API (Endpoint Groups)

Render endpoints organized into **collapsible tabs/accordions** matching the Swagger tags from Module 9:

| Tab key | Arabic label |
|---|---|
| `auth` | المصادقة |
| `banks` | البنوك |
| `users` | المستخدمون |
| `requests` | طلبات التمويل |
| `workflow` | سير العمل |
| `voting` | التصويت |
| `documents` | المستندات |
| `customs` | البيان الجمركي |
| `audit` | سجلات التدقيق |
| `notifications` | الإشعارات |
| `dashboard` | لوحة المعلومات |
| `reports` | التقارير |

For each endpoint inside a tab, render a card with:
- **Method badge** (color-coded: GET=blue, POST=emerald, PUT=amber, DELETE=rose).
- **Endpoint URL** in monospace.
- **Short Arabic description** (e.g. `"إنشاء طلب تمويل جديد"`).
- **Input fields** for path params and body params (auto-generated from the endpoint's signature — see the list below).
- **زر "تنفيذ"** (Execute) button.

When user clicks "تنفيذ":
- Build the fetch call with the entered values.
- Send to the `/api/*` endpoint.
- Render the response in Section 3 (the response panel).
- Also show a small inline result indicator on the card (✓ or ✗ with status code).

---

## Endpoint list to render (Arabic descriptions)

### Tab: المصادقة (auth)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| POST | /api/auth/login | تسجيل الدخول | email, password |
| POST | /api/auth/logout | تسجيل الخروج | — |
| GET | /api/auth/me | المستخدم الحالي | — |

### Tab: البنوك (banks)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/banks | قائمة البنوك | — |
| POST | /api/banks | إضافة بنك جديد | name, code |
| GET | /api/banks/{id} | تفاصيل البنك | id |
| PUT | /api/banks/{id} | تعديل بنك | id, name, code, is_active |
| DELETE | /api/banks/{id} | حذف بنك | id |

### Tab: المستخدمون (users)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/users | قائمة المستخدمين | role (filter), bank_id (filter), is_active |
| POST | /api/users | إضافة مستخدم | name, email, password, role, bank_id |
| GET | /api/users/{id} | تفاصيل مستخدم | id |
| PUT | /api/users/{id} | تعديل مستخدم | id, name, email, role, bank_id, is_active |
| DELETE | /api/users/{id} | إلغاء تفعيل مستخدم | id |

### Tab: طلبات التمويل (requests)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/requests | قائمة الطلبات | status, bank_id, search, from_date, to_date |
| POST | /api/requests | إنشاء طلب جديد | currency, amount, supplier_name, goods_description, port_of_entry, notes |
| GET | /api/requests/{id} | تفاصيل الطلب | id |
| PUT | /api/requests/{id} | تعديل الطلب | id + كل الحقول |
| DELETE | /api/requests/{id} | حذف الطلب | id |
| GET | /api/requests/{id}/history | سجل مراحل الطلب | id |

### Tab: سير العمل (workflow)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| POST | /api/workflow/{id}/submit | إرسال الطلب للمراجعة | id, reason (optional) |
| POST | /api/workflow/{id}/bank-approve | موافقة البنك | id |
| POST | /api/workflow/{id}/bank-reject | رفض البنك | id, reason |
| POST | /api/workflow/{id}/return-to-entry | إعادة لموظف الإدخال | id, reason |
| POST | /api/workflow/{id}/support-approve | موافقة لجنة الدعم | id |
| POST | /api/workflow/{id}/support-reject | رفض لجنة الدعم | id, reason |
| POST | /api/workflow/{id}/swift-upload | رفع مستند SWIFT | id, file (multipart) |
| POST | /api/workflow/{id}/finalize-decision | إنهاء قرار اللجنة التنفيذية | id |

### Tab: التصويت (voting)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/voting | قائمة الطلبات للتصويت | — |
| GET | /api/voting/{id} | تفاصيل تصويت | id |
| POST | /api/voting/{id}/vote | إرسال تصويت | id, vote (APPROVE/REJECT/ABSTAIN), justification |
| POST | /api/voting/{id}/director-decide | قرار رئيس اللجنة (كسر التعادل) | id, vote, justification |

### Tab: المستندات (documents)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| POST | /api/requests/{id}/documents | رفع مستند طلب | id, file (multipart) |
| DELETE | /api/documents/{id} | حذف مستند | id |
| GET | /api/documents/{id}/download | تحميل مستند | id |

### Tab: البيان الجمركي (customs)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| POST | /api/customs/{request_id}/generate | إصدار البيان الجمركي | request_id |
| GET | /api/customs/{id} | تفاصيل البيان | id |
| GET | /api/customs/{id}/download | تحميل البيان PDF | id |

### Tab: سجلات التدقيق (audit)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/audit | سجل العمليات | user_id, action, from_date, to_date |

### Tab: الإشعارات (notifications)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/notifications | إشعاراتي | — |
| POST | /api/notifications/{id}/read | تعليم كمقروء | id |
| POST | /api/notifications/read-all | تعليم الكل كمقروء | — |

### Tab: لوحة المعلومات (dashboard)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/dashboard/stats | إحصائيات اللوحة | — |

### Tab: التقارير (reports)

| Method | Path | الوصف | الحقول |
|---|---|---|---|
| GET | /api/reports/workflow | تقرير سير العمل | — |
| GET | /api/reports/voting | تقرير التصويت | — |

---

# 📤 Section 3 — نتيجة آخر طلب (Response Panel)

A sticky/fixed panel showing:
- **شريط الحالة**: HTTP status code (color-coded green/red).
- **الطريقة + الرابط**: e.g. `POST /api/requests`.
- **الوقت المستغرق**: ms.
- **محتوى الطلب المُرسَل** (collapsible): pretty-printed JSON of what was sent.
- **محتوى الاستجابة**: pretty-printed JSON with syntax highlighting (use a simple highlighter — color keys/strings/numbers via plain CSS classes; no external library needed).
- **زر "نسخ الاستجابة"** (Copy response).
- **زر "مسح"** (Clear).

If the response contains an `id` field (e.g. after creating a request), show a `"استخدم هذا المعرّف"` (Use this ID) button that auto-fills the `id` field in workflow/document/voting endpoint cards.

---

# 🛠️ JavaScript behavior

All in one `<script>` block at the bottom of the page. No external JS frameworks.

## Core helpers

```js
const API_BASE = '/api';
const TOKEN_KEY = 'yfh_api_token';
const USER_KEY  = 'yfh_current_user';

function getToken() { return localStorage.getItem(TOKEN_KEY); }
function setToken(t) { localStorage.setItem(TOKEN_KEY, t); }
function clearToken() { localStorage.removeItem(TOKEN_KEY); localStorage.removeItem(USER_KEY); }

async function callApi(method, path, body = null, isMultipart = false) {
  const start = performance.now();
  const headers = { 'Accept': 'application/json' };
  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (!isMultipart && body) headers['Content-Type'] = 'application/json';

  const opts = { method, headers };
  if (body && method !== 'GET') {
    opts.body = isMultipart ? body : JSON.stringify(body);
  }

  let res, data, error = null;
  try {
    res = await fetch(API_BASE + path, opts);
    data = await res.json().catch(() => null);
  } catch (e) {
    error = e.message;
  }
  const elapsed = Math.round(performance.now() - start);

  renderResponse({ method, path, body, status: res?.status, data, error, elapsed });
  return { status: res?.status, data };
}
```

## Login handler

```js
async function login(email) {
  const { status, data } = await callApi('POST', '/auth/login', { email, password: 'password' });
  if (status === 200 && data?.data?.token) {
    setToken(data.data.token);
    localStorage.setItem(USER_KEY, JSON.stringify(data.data.user));
    updateStatusBar();
    showToast('تم تسجيل الدخول بنجاح', 'success');
  } else {
    showToast('فشل تسجيل الدخول', 'error');
  }
}
```

(Adjust property paths based on the actual response shape — `data.data.token` or `data.token` depending on what Module 1's `AuthController` returns. **Codex: inspect the AuthController response shape and align this code accordingly.**)

## Endpoint card form handling

For each endpoint card:
- Path params (`{id}`, `{request_id}`) become input fields above the body fields.
- File inputs are detected by field name (`file`) and trigger multipart mode.
- The "تنفيذ" button reads all field values, builds the URL by replacing path params, and calls `callApi(...)`.

## Render response

`renderResponse({...})` updates Section 3:
- Status badge with color (2xx=green, 4xx=amber, 5xx=red).
- Pretty-print JSON with simple inline syntax coloring.
- Show elapsed time and full path.

## Toast

Simple toast helper showing top-left messages in Arabic, auto-dismissing after 3s.

---

# 🎯 UX details

- **Status bar at top**: always visible. Shows current user + role + bank, or `"لم يتم تسجيل الدخول"` if no token.
- **Active endpoint tab persists** across reloads via `localStorage`.
- **Last entered values** for each endpoint persist via `localStorage` keyed by endpoint path (so you don't lose your test data on reload).
- **Filter input** for endpoints (top of section 2): instant search across all endpoint paths and Arabic descriptions.
- **Keyboard shortcut**: pressing `Enter` inside any endpoint card's last input triggers the "تنفيذ" button.
- **Visual lock**: if no user is logged in, dim section 2 and show a tooltip `"يجب تسجيل الدخول أولاً"` on the execute buttons (still allow `/api/auth/login` to be called).

---

# ⚠️ Implementation notes for Codex

1. **Read `AuthController` from Module 1 first** to confirm whether the login response returns `{ data: { token, user } }` or some other shape. Align the JS accordingly.
2. **Read the seeded users** — the controller already passes `$users`. The view just renders them.
3. **No CSRF concerns** since we're using Bearer tokens, not cookies, for this page. Make sure `auth:sanctum` middleware accepts Bearer tokens (it does by default).
4. **One file only** — everything (HTML, CSS via Tailwind CDN, JS) lives inside `test_api.blade.php`. No partials, no asset compilation, no Vite.
5. **Do not protect the route** with auth middleware — anyone hitting `/test-api` should see the page (the API calls themselves require auth).
6. **Use `@json($users)`** to inject the user list into JS:
   ```html
   <script>
     window.SEEDED_USERS = @json($users);
   </script>
   ```
7. Keep the page under ~800 lines. Inline, readable, no over-engineering.

---

# ✅ Acceptance criteria

After running the page at `http://localhost:8000/test-api`:

- [ ] Page loads in RTL Arabic with Cairo/Tajawal font.
- [ ] All 22 seeded users appear in the quick-login list, filterable, grouped by role.
- [ ] Clicking "دخول" on any user logs them in and shows their role + bank in the status bar.
- [ ] All 12 endpoint tabs render with their endpoints.
- [ ] Every endpoint can be executed and shows a response in section 3.
- [ ] Response panel shows status, timing, request body, and response body with syntax coloring.
- [ ] The "استخدم هذا المعرّف" button works after creating a request.
- [ ] Logout clears the token and resets the UI.
- [ ] No console errors. No external JS dependencies beyond Tailwind CDN.

---

# 📤 Output

Print:
- File tree of the 3 created/modified files.
- The exact URL to visit: `http://localhost:8000/test-api`.
- Reminder: seeded password is `password` for all users.
