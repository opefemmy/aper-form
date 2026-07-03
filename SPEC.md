# Annual Performance Evaluation Report System - Specification

## Project Overview
- **Project Name:** Annual Performance Evaluation Report System
- **Type:** Web Application (WordPress Plugin/Embedded Page)
- **Core Functionality:** A comprehensive performance evaluation system for educational institutions with automatic calculations, Excel export, and email notifications
- **Target Users:** Educational institution staff, supervisors, and administrators

## UI/UX Specification

### Layout Structure

**Page Sections:**
1. Header - Institution branding and title
2. Progress Bar - Multi-step form progress indicator
3. Form Sections - 5 collapsible/expandable sections
4. Results Panel - Live score calculations display
5. Submit Area - Form submission with loading states
6. Footer - Copyright and support info

**Responsive Breakpoints:**
- Mobile: < 576px
- Tablet: 576px - 991px
- Desktop: ≥ 992px

### Visual Design

**Color Palette:**
- Primary Blue: #1e3a8a (deep institutional blue)
- Secondary Blue: #3b82f6 (bright accent blue)
- Light Blue: #dbeafe (background tints)
- White: #ffffff
- Light Gray: #f8fafc
- Medium Gray: #64748b
- Dark Gray: #334155
- Success Green: #10b981
- Warning Orange: #f59e0b
- Error Red: #ef4444

**Typography:**
- Headings: 'Segoe UI', system-ui, sans-serif (700 weight)
- Body: 'Segoe UI', system-ui, sans-serif (400 weight)
- Font Sizes: h1: 2rem, h2: 1.5rem, h3: 1.25rem, body: 1rem

**Spacing System:**
- Section padding: 2rem
- Card padding: 1.5rem
- Element gap: 1rem
- Input padding: 0.75rem 1rem

**Visual Effects:**
- Card shadows: 0 4px 6px -1px rgba(0, 0, 0, 0.1)
- Hover transitions: 0.3s ease
- Section expand/collapse animation: 0.4s ease
- Loading spinner: CSS animated

### Components

**Form Elements:**
- Text inputs with floating labels
- Select dropdowns with custom styling
- Radio button groups for scoring (1-5 scale)
- File input for logo upload
- Textarea for remarks

**Interactive Elements:**
- Collapsible sections with chevron icons
- Progress bar with percentage
- Score display cards with icons
- Loading spinner overlay
- Toast notifications (success/error)
- Print button
- Smooth scroll navigation

**States:**
- Default, Focus, Disabled, Error, Valid
- Hover: slight scale + shadow increase
- Active: color shift to primary
- Disabled: 50% opacity

## Functionality Specification

### Section 1: Institution Details
- Institution Name (text, required)
- Institution Logo (file upload, optional)
- Report Title (text, pre-filled)
- Academic Session (select, required)
- Semester (select, required)
- Evaluation Year (number, required)
- Date (date picker, auto-filled)

### Section 2: Staff Information
- Full Name (text, required)
- Staff ID (text, required)
- Department (text, required)
- Faculty/School (text, required)
- Designation (text, required)
- Grade Level (select, required)
- Employment Status (select, required)
- Years of Service (number, required)
- Email (email, required, validated)
- Phone (tel, required, validated)

### Section 3: Performance Evaluation
Categories with scoring (5-point scale):

**Teaching Performance (6 questions):**
- Lecture Delivery
- Class Attendance
- Student Engagement
- Course Preparation
- Course Coverage
- Time Management

**Research Performance (5 questions):**
- Publications
- Conferences
- Research Grants
- Journal Articles
- Innovations

**Administrative Duties (5 questions):**
- Attendance
- Punctuality
- Leadership
- Teamwork
- Record Keeping

**Community Service (3 questions):**
- Community Development
- Committee Participation
- Institutional Representation

**Professional Development (4 questions):**
- Workshops
- Training
- Certifications
- Seminars

**Auto-Calculation:**
- Total Score: Sum of all question scores
- Average Score: Total / Number of questions
- Percentage: (Total / Max Possible) × 100
- Grade: Based on percentage thresholds
- Status: Text description of grade

### Section 4: Supervisor Assessment
- Supervisor Name (text, required)
- Supervisor Designation (text, required)
- Remarks (textarea)
- Overall Rating (select: Outstanding/Excellent/Very Good/Good/Fair/Poor)
- Recommendation (select: Promoted/Confirmed/Continued/Probation/Terminated)
- Digital Signature (file upload or text input)
- Date (date picker)

### Section 5: Registrar/Management
- Registrar Name (text, required)
- Remarks (textarea)
- Approval Status (select: Approved/Pending/Rejected)
- Date (date picker)
- Digital Signature (file upload or text input)

### Validation Rules
- All required fields must be filled
- Email: valid email format
- Phone: valid phone format (10-15 digits)
- Scores: 1-5 range
- Year: 2000-current year
- Duplicate submission prevention via token

### Export Features
- Excel (.xlsx) generation with PhpSpreadsheet
- Professional formatting with headers
- Include all form data
- Include calculation results
- Auto-save to server

### Email Features
- PHPMailer integration
- Send to: evaluation@yourinstitution.edu.ng
- Subject: Annual Performance Evaluation Report Submission
- Body: Staff name, department, session, date
- Attach generated Excel file

### Security Features
- CSRF token generation and validation
- Input sanitization (htmlspecialchars, strip_tags)
- SQL injection protection (prepared statements)
- XSS protection (output encoding)
- Spam protection (honeypot field)
- File type validation for uploads

## Acceptance Criteria

1. ✓ Form displays correctly on all device sizes
2. ✓ All 5 sections are collapsible
3. ✓ Progress bar updates as sections complete
4. ✓ Score calculations update in real-time
5. ✓ Grade displays correctly based on percentage
6. ✓ Validation shows error messages inline
7. ✓ Excel file generates with all data
8. ✓ Email sends with attachment
9. ✓ Print layout is clean and professional
10. ✓ WordPress shortcode renders form correctly

## File Structure
```
aper form/
├── index.html          # Main HTML form
├── style.css          # Styling
├── script.js           # JavaScript functionality
├── mail.php            # PHPMailer handler
├── generate_excel.php  # PhpSpreadsheet Excel generator
├── functions.php       # WordPress shortcode
├── composer.json       # Dependencies
└── README.md          # Documentation
```