# LoveKin Plugin Test Guide (Non-Technical)

## Who this is for
This guide is for testers who are not developers.

You do not need code tools or database access.
You only need a browser and test user accounts.

## Goal
Confirm the plugin is ready for real users by checking:
1. Login and registration
2. Dashboard access and navigation
3. Profile updates
4. Documents and Archive
5. Funding flow
6. Admin tools (Generate and Erase demo data)

## Estimated time
60 to 90 minutes.

## What you need before you start
1. Site URL: `http://localhost/lovekin`
2. One admin account
3. One primary member account
4. Two secondary member accounts
5. These pages should already exist:
   - Login page
   - Register page
   - Dashboard page

If any account/page is missing, ask the project owner before starting.

## How to record results
For each test, mark:
1. `PASS` if result matches expected behavior
2. `FAIL` if result is different
3. Add screenshot for every failure

For each failure, capture:
1. What you clicked
2. What happened
3. What you expected instead
4. URL shown in browser

Use the included sample tracker file:
1. Open `TEST_TRACKER_TEMPLATE.xlsx` in Excel, Numbers, or Google Sheets (`TEST_TRACKER_TEMPLATE.csv` is also included if needed)
2. Save a copy as `TEST_TRACKER_RUN_<date>.xlsx` (example: `TEST_TRACKER_RUN_2026-03-01.xlsx`)
3. Fill one row per test case
4. Set `Status` to `PASS`, `FAIL`, `BLOCKED`, or `NOT RUN`
5. Add screenshot file names in `Screenshot File` for every `FAIL`

Simple tracking rule:
1. If a test is blocked because of a previous bug, mark it `BLOCKED` and explain in `Actual Result`
2. Keep the `Expected Result` text unchanged so anyone can compare easily

## Test Run Order

## 1. Basic access check
### 1.1 LoveKin admin menu
Steps:
1. Login as admin
2. Open WordPress admin sidebar
3. Find `LoveKin`

Expected:
1. LoveKin menu is visible
2. Submenus are visible (Dashboard, Courses, Assessments, Relationships, Reports, Funding Requests, Documents, Settings, Tools)

### 1.2 Frontend pages load
Steps:
1. Open login page
2. Open register page
3. Open dashboard page while logged out

Expected:
1. Login page loads normally
2. Register page loads normally
3. Dashboard page redirects to login with a short message asking user to log in

## 2. Registration and login
### 2.1 Register a new user
Steps:
1. Go to register page
2. Fill all fields:
   - First Name
   - Last Name
   - Username
   - Email
   - Phone Number
   - Occupation
   - City
   - State
   - Country
   - Password
   - Confirm Password
3. Submit form

Expected:
1. Registration succeeds
2. User is sent to login page
3. Success message appears
4. User is not auto-logged in

### 2.2 Register validation checks
Run each check one at a time:
1. Leave required fields empty and submit
2. Use weak password and submit
3. Use different password and confirm password
4. Try an existing username/email

Expected:
1. Clear error message appears
2. No blank page
3. Errors do not expose private account details

### 2.3 Login success and failure
Steps:
1. Login with valid user
2. Logout
3. Login with wrong password

Expected:
1. Valid login goes to dashboard
2. Wrong password shows a clear error
3. No blank page

### 2.4 Forgot password and remember me
Steps:
1. On login page, click `Forgot password?`
2. Complete reset request
3. On login page, check visual alignment of `Remember me` checkbox and text

Expected:
1. Forgot password flow opens WordPress reset process
2. Message is shown after request
3. Remember-me checkbox and label are aligned and readable

## 3. Access control and redirects
### 3.1 Protected pages
Steps:
1. While logged out, open dashboard/documents/archive pages directly
2. Login from redirected login page

Expected:
1. Logged-out users are redirected to login
2. Temporary message explains login is required
3. After login, dashboard opens correctly

### 3.2 Login/Register pages while already logged in
Steps:
1. Login as member
2. Open login page URL directly
3. Open register page URL directly

Expected:
1. User is redirected to dashboard

## 4. Profile tests
### 4.1 Update profile fields
Steps:
1. Login as member
2. Open profile tab/page
3. Update:
   - Phone
   - Occupation
   - City
   - State
   - Country
4. Save
5. Refresh page

Expected:
1. Success message appears
2. New values remain after refresh

## 5. Documents tests
### 5.1 Admin uploads a document
Steps:
1. Login as admin
2. Open `LoveKin > Documents`
3. Upload a valid file

Expected:
1. Success message appears
2. File appears in admin library list

### 5.2 Admin validation checks
Steps:
1. Upload unsupported file type
2. Upload too-large file (if possible)

Expected:
1. Clear error message appears for each invalid case

### 5.3 Member can view/download documents
Steps:
1. Login as primary member and open dashboard documents tab
2. Download a document
3. Repeat as secondary member

Expected:
1. Both logged-in member roles can see shared library
2. Download works

## 6. Archive tests (private user files)
### 6.1 Upload and manage own archive file
Steps:
1. Login as Secondary User A
2. Open Archive tab/page
3. Upload a valid file
4. Download uploaded file
5. Delete uploaded file

Expected:
1. Upload success message appears
2. File appears in list
3. Download works
4. Delete works

### 6.2 Archive privacy check between users
Steps:
1. Login as Secondary User A and upload file
2. Logout
3. Login as Secondary User B
4. Open Archive tab/page

Expected:
1. User B does not see User A file
2. User B cannot access User A archive content

### 6.3 Archive validation checks
Steps:
1. Try unsupported file type
2. Try large file beyond limit

Expected:
1. Correct error message appears
2. No blank page

## 7. Funding tests
### 7.1 Member submits funding request
Steps:
1. Login as member
2. Open Funding tab/page
3. Submit valid funding request

Expected:
1. Request is submitted
2. Success message appears
3. Request appears in member view/history

### 7.2 Admin reviews funding request
Steps:
1. Login as admin
2. Open `LoveKin > Funding Requests`
3. Approve or reject a request and add note

Expected:
1. Status update saves successfully
2. Updated status is visible

## 8. Reports tests
### 8.1 View reports by role
Steps:
1. Login as secondary and open report
2. Login as primary and open report
3. Login as admin and open report area

Expected:
1. Secondary sees own report data
2. Primary sees permitted member-related data
3. Admin can access full report tools

## 9. Tools tests (Demo Data)
### 9.1 Generate demo data
Steps:
1. Login as admin
2. Open `LoveKin > Tools`
3. Click `Generate Demo Data`

Expected:
1. Success message appears
2. Demo users list is shown
3. No errors or blank page

### 9.2 Re-run generate demo data
Steps:
1. Click `Generate Demo Data` again

Expected:
1. Action succeeds again
2. No duplicate explosion or broken records

### 9.3 Erase demo content
Steps:
1. Click `Erase Demo Content`
2. Confirm the popup

Expected:
1. Demo content is removed
2. Success message appears
3. Running erase again should still be safe (no crash)

## 10. Visual and mobile checks
Test these pages on desktop and mobile width:
1. Login page
2. Register page
3. Dashboard tabs
4. Documents section
5. Archive section

Expected:
1. Text is readable
2. Buttons are usable
3. No overlapping/jumbled controls
4. No major layout break

## 11. Final sign-off checklist
Mark each as Yes/No:
1. Registration works with required fields
2. Login works and redirects correctly
3. Forgot password works
4. Protected pages redirect logged-out users
5. Profile save works for phone/location/occupation
6. Admin can upload/manage documents
7. Members can download documents
8. Archive upload/download/delete works
9. Archive privacy between users works
10. Funding submit and review work
11. Tools generate/erase demo content works
12. No blank pages in tested flows

Release recommendation:
1. Approve release only if all critical items above are `Yes`.
