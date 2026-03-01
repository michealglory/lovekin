# LoveKin Plugin Testing Guide

## 1. Purpose
This document guides QA testers through a full manual test of the LoveKin plugin before release.

Goals:
1. Verify all major features work end-to-end.
2. Verify role/capability boundaries (admin vs primary vs secondary).
3. Verify recent fixes (auth redirects/notices, documents/archive reliability/privacy, demo generate/erase tools).
4. Catch regressions in security, UX, and data integrity.

## 2. Scope
In scope:
1. Plugin activation and setup.
2. Roles and permissions.
3. Admin menus and settings.
4. Login/registration/logout/reset-password integration.
5. Dashboard tabs and protected-page behavior.
6. Courses and assessments.
7. Reports and remarks.
8. Funding requests.
9. Documents library (admin upload/manage, member download).
10. Archive (member-owned private uploads only).
11. Tools: Generate Demo Data + Erase Demo Content.

Out of scope:
1. Performance/load testing.
2. Third-party plugin/theme conflicts beyond smoke checks.
3. Automated browser testing scripts.

## 3. Environment and Prerequisites
Use this baseline unless project lead specifies otherwise:
1. WordPress site: `http://localhost/lovekin`
2. Plugin installed and activated from ZIP.
3. Permalinks enabled (save once in WP settings).
4. `WP_DEBUG` enabled in local test environment.
5. Access to:
   - WordPress admin
   - Browser dev tools
   - Database viewer (phpMyAdmin or equivalent)
   - PHP log: `/Applications/XAMPP/xamppfiles/logs/php_error_log`

## 4. Required Test Pages and Shortcodes
Create these pages (or verify they already exist):
1. Login page: `[lovekin_login]`
2. Register page: `[lovekin_register]`
3. Dashboard page: `[lovekin_dashboard]`
4. Optional standalone pages:
   - Documents page: `[lovekin_documents]`
   - Archive page: `[lovekin_archive]`
   - Profile page: `[lovekin_profile]`
   - Funding page: `[lovekin_funding_request]`
   - Reports page: `[lovekin_report]`

In `LoveKin > Settings > Authentication Pages`:
1. Set Login Page.
2. Set Register Page.
3. Set Dashboard Page.
4. Save settings.

## 5. Test Accounts
Prepare:
1. Admin user (WordPress administrator).
2. One `lk_primary` user.
3. Two `lk_secondary` users.
4. Optional non-member subscriber for negative checks.

If role users do not exist, create in WP admin and assign roles manually.

## 6. Quick Smoke Test (10-15 mins)
Run this first:
1. Activate plugin.
2. Verify LoveKin menu appears in admin.
3. Open `LoveKin > Tools`, generate demo data.
4. Visit login page, sign in as one demo member.
5. Confirm dashboard loads and tabs render.
6. Open Documents tab and confirm shared library section loads.
7. Open Archive tab and upload a supported file.
8. Download uploaded archive file.
9. Logout and verify redirect/notice behavior.

Pass criteria:
1. No blank pages.
2. No PHP fatals/notices blocking flow.
3. Core navigation and forms usable.

## 7. Detailed Test Cases

## A. Installation and Data Model
### A1. Plugin activation
Steps:
1. Deactivate plugin.
2. Reactivate plugin.
3. Visit any LoveKin admin page.

Expected:
1. No activation errors.
2. Required DB tables exist:
   - `<prefix>lk_relationships`
   - `<prefix>lk_attempts`
   - `<prefix>lk_funding_requests`
   - `<prefix>lk_documents`
   - `<prefix>lk_archive_files`
3. Roles `lk_primary` and `lk_secondary` exist.

### A2. Admin menus
Steps:
1. Login as admin.
2. Open LoveKin sidebar.

Expected:
1. Menus visible: Dashboard, Courses, Assessments, Relationships, Reports, Funding Requests, Documents, Settings, Tools.

## B. Roles and Permissions
### B1. Member permissions
Steps:
1. Login as secondary.
2. Attempt access to admin-only pages (LoveKin Settings/Tools/Documents admin).

Expected:
1. Member can use frontend dashboard features.
2. Member cannot access admin-only pages/actions.

### B2. Primary vs secondary report access
Steps:
1. Login as primary.
2. Open reports in dashboard.
3. Login as secondary.
4. Open reports in dashboard.

Expected:
1. Primary can view assigned members and remark functionality where applicable.
2. Secondary can view own report data only.

## C. Settings
### C1. Authentication page selectors
Steps:
1. Set login/register/dashboard page IDs in settings.
2. Save.
3. Clear selectors to 0.
4. Save again.

Expected:
1. Save succeeds each time.
2. With IDs set, those pages are used for redirects.
3. With IDs cleared, shortcode auto-discovery fallback works.

### C2. Upload settings
Steps:
1. Set allowed types and quotas.
2. Save.
3. Try upload with disallowed type and oversized files in Documents and Archive.

Expected:
1. Settings persist.
2. Type/size/quota validations trigger correct user-facing errors.

## D. Authentication and Access Control
### D1. Registration success flow
Steps:
1. Visit register page logged out.
2. Submit valid values for:
   - first name, last name
   - username, email
   - phone number, occupation
   - city, state, country
   - password + confirm

Expected:
1. Account created.
2. New user gets default secondary role.
3. Redirect to login page with success notice.
4. User is not auto-logged-in.

### D2. Registration validation and anti-enumeration
Steps:
1. Submit missing required fields.
2. Submit weak password.
3. Submit password mismatch.
4. Submit duplicate email/username.

Expected:
1. Form shows generic safe failure messages.
2. No explicit disclosure of whether specific email exists.

### D3. Login and redirect policy
Steps:
1. Login with valid credentials from login shortcode page.
2. Login with invalid password.

Expected:
1. Valid login always redirects to configured dashboard page.
2. Invalid login shows clear error on login page.
3. No blank response page.

### D4. Remember me, forgot password, logout
Steps:
1. Check remember-me alignment and click behavior.
2. Use forgot password link and complete WP reset flow.
3. Logout via dashboard button/link.

Expected:
1. Remember checkbox aligned with text.
2. Forgot password uses WordPress reset system and non-enumerating messaging.
3. Logout redirects and shows short-lived notice.

### D5. Protected pages/shortcodes
Steps:
1. While logged out, navigate directly to protected page (dashboard/documents/archive page).
2. Login from redirected page.
3. Visit login/register pages while already logged in.

Expected:
1. Logged-out user redirected to login with temporary `login_required` notice.
2. Logged-in user accessing login/register is redirected to dashboard.
3. Notice auto-hides and URL query params are cleaned up.

## E. Profile
### E1. Profile update
Steps:
1. Login as member.
2. Open profile tab/page.
3. Update phone, occupation, city/state/country, and optional avatar.

Expected:
1. Save succeeds with confirmation.
2. Updated data persists and displays correctly after refresh/re-login.

## F. Courses and Assessments
### F1. Course publish validation
Steps:
1. Admin creates new course without material URL and tries publish.
2. Add material URL and publish again.

Expected:
1. Course without material is prevented from publish and stays draft with notice.
2. Course publishes successfully once material URL is present.

### F2. Assessment linkage validation
Steps:
1. Admin creates assessment without linking course and publishes.
2. Link a course and publish.

Expected:
1. Assessment is forced back to draft if no linked course.
2. Publishes after linking course.

### F3. Assessment submission
Steps:
1. Login as member.
2. Open course/assessment and submit answers.

Expected:
1. Attempt row is recorded.
2. Score/result view appears.
3. Latest attempt can be reopened.

## G. Reports and Remarks
### G1. Report visibility
Steps:
1. Secondary user opens report.
2. Primary/admin opens report(s) including user filter where applicable.

Expected:
1. Secondary sees own data only.
2. Admin can filter users and view broader report data.

### G2. Remark update
Steps:
1. Save remark via AJAX path and non-AJAX fallback (if available).

Expected:
1. Remark saves successfully.
2. Success feedback shown without page break.

## H. Funding Requests
### H1. Member submission
Steps:
1. Submit funding request with valid fields.
2. Try invalid/missing values and disallowed file.

Expected:
1. Valid submission stored with `pending` status.
2. Validation errors shown for invalid cases.

### H2. Admin review
Steps:
1. Admin opens Funding Requests.
2. Approve/reject and add notes.

Expected:
1. Status and notes persist.
2. Member report/dashboard reflects updated status.

## I. Documents Library (Shared, Admin-Managed)
### I1. Admin upload/manage
Steps:
1. Upload valid document in `LoveKin > Documents`.
2. Upload disallowed type/oversized file.
3. Delete a document.

Expected:
1. Success/error notices display clearly.
2. Uploaded item appears in admin library list with metadata.
3. Delete removes row and physical file.

### I2. Member access
Steps:
1. Login as primary and secondary.
2. Open dashboard Documents tab/page.
3. Download documents.
4. Try document download while logged out.

Expected:
1. All logged-in members can see/download library docs.
2. Logged-out access is blocked.

## J. Archive (Private Member-Owned Files)
### J1. Upload validations
Steps:
1. Upload valid file.
2. Try unsupported type.
3. Try oversized file.
4. Exceed archive quota.

Expected:
1. Correct success/error notice for each case.
2. Usage bar and file list update correctly.

### J2. Ownership enforcement
Steps:
1. User A uploads files.
2. User B logs in and attempts to list/download/delete User A file by URL/ID tampering.

Expected:
1. User B cannot access User A archive files.
2. Only owner can list/download/delete own files.

### J3. Storage/privacy hardening
Steps:
1. Inspect stored filenames and upload path.
2. Attempt direct URL access to private archive file if URL is known.

Expected:
1. Stored filenames are randomized.
2. Files are under `wp-content/uploads/lovekin/private/archive/{user_id}`.
3. `index.php`/`.htaccess` protections exist in private folders (best effort on Apache).

## K. Tools: Demo Data
### K1. Generate demo data
Steps:
1. Open `LoveKin > Tools`.
2. Click `Generate Demo Data`.

Expected:
1. Existing demo data is erased first.
2. Exactly 6 records are created per core dataset:
   - users, courses, assessments, attempts, relationships, funding requests.
3. Success notice shows created counts.

### K2. Re-run generator
Steps:
1. Click `Generate Demo Data` again immediately.

Expected:
1. Counts remain exactly 6 per dataset (no growth above 6).

### K3. Erase demo content
Steps:
1. Click `Erase Demo Content`.
2. Confirm browser confirmation modal.

Expected:
1. Only demo-tagged and demo-linked records are removed.
2. Non-demo records remain intact.
3. Re-running erase with no demo data shows neutral success message.

### K4. SQL verification (optional but recommended)
Use your DB prefix instead of `wp_`.

1. Demo users:
```sql
SELECT COUNT(*) AS demo_users
FROM wp_usermeta
WHERE meta_key = 'lk_demo_user' AND meta_value = '1';
```

2. Demo courses:
```sql
SELECT COUNT(*) AS demo_courses
FROM wp_posts p
JOIN wp_postmeta pm ON pm.post_id = p.ID
WHERE p.post_type = 'lk_course'
  AND p.post_status <> 'trash'
  AND pm.meta_key = 'lk_demo_content'
  AND pm.meta_value = '1';
```

3. Demo assessments:
```sql
SELECT COUNT(*) AS demo_assessments
FROM wp_posts p
JOIN wp_postmeta pm ON pm.post_id = p.ID
WHERE p.post_type = 'lk_assessment'
  AND p.post_status <> 'trash'
  AND pm.meta_key = 'lk_demo_content'
  AND pm.meta_value = '1';
```

4. Demo-linked attempts:
```sql
SELECT COUNT(*) AS demo_attempts
FROM wp_lk_attempts
WHERE user_id IN (
  SELECT user_id FROM wp_usermeta WHERE meta_key = 'lk_demo_user' AND meta_value = '1'
);
```

5. Demo-linked relationships:
```sql
SELECT COUNT(*) AS demo_relationships
FROM wp_lk_relationships
WHERE primary_user_id IN (
  SELECT user_id FROM wp_usermeta WHERE meta_key = 'lk_demo_user' AND meta_value = '1'
)
OR secondary_user_id IN (
  SELECT user_id FROM wp_usermeta WHERE meta_key = 'lk_demo_user' AND meta_value = '1'
);
```

6. Demo-linked funding:
```sql
SELECT COUNT(*) AS demo_funding
FROM wp_lk_funding_requests
WHERE user_id IN (
  SELECT user_id FROM wp_usermeta WHERE meta_key = 'lk_demo_user' AND meta_value = '1'
);
```

## L. UI/Responsive Checks
Test at:
1. Desktop (>=1280px)
2. Tablet (~768px)
3. Mobile (~390px)

Verify:
1. Login/register layout and spacing.
2. Dashboard tab menu behavior.
3. Documents cards and filters.
4. Archive upload panel/table responsiveness.
5. Buttons and alerts are readable and aligned.

## M. Error Logs and Regression Check
After full run:
1. Inspect PHP log:
   - `/Applications/XAMPP/xamppfiles/logs/php_error_log`
2. Confirm no new fatals/warnings from LoveKin flows.
3. Specifically verify no `move_uploaded_file ... No such file or directory` for:
   - archive uploads
   - documents uploads
4. Verify no `Cannot modify header information` from auth flows.

## 8. Defect Reporting Template
For each defect, capture:
1. Test case ID
2. Environment (browser, WP version, PHP version)
3. Preconditions
4. Steps to reproduce
5. Actual result
6. Expected result
7. Screenshot/video
8. Console/network logs (if relevant)
9. Severity (Blocker/High/Medium/Low)

## 9. Exit Criteria (Release Readiness)
Release candidate passes when:
1. All High/Blocker issues are closed.
2. Core flows pass:
   - auth, dashboard, profile, courses/assessments, reports, funding, documents, archive, tools.
3. Demo generate/erase counts and safety checks pass.
4. No critical PHP errors introduced in logs.
