# Annual Performance Evaluation Report System

A comprehensive, professional web-based Annual Performance Evaluation Report System designed for educational institutions. Built with HTML5, CSS3, JavaScript, PHP, PhpSpreadsheet, and PHPMailer.

## Features

### 📋 Complete Form Sections
- **Section 1:** Institution Details (Name, Logo, Session, Semester, Year, Date)
- **Section 2:** Staff Information (Personal details, Employment info)
- **Section 3:** Performance Evaluation (5 categories with 23 questions)
- **Section 4:** Supervisor Assessment (Rating, Recommendation, Signature)
- **Section 5:** Registrar/Management (Approval, Signature)

### 📊 Automatic Calculations
- Total Score (sum of all ratings)
- Average Score
- Percentage
- Performance Grade (Outstanding/Excellent/Very Good/Good/Fair/Poor)
- Performance Status

### 📈 Evaluation Categories
1. **Teaching Performance** (6 questions)
   - Lecture Delivery, Class Attendance, Student Engagement
   - Course Preparation, Course Coverage, Time Management

2. **Research Performance** (5 questions)
   - Publications, Conferences, Research Grants
   - Journal Articles, Innovations

3. **Administrative Duties** (5 questions)
   - Attendance, Punctuality, Leadership
   - Teamwork, Record Keeping

4. **Community Service** (3 questions)
   - Community Development, Committee Participation
   - Institutional Representation

5. **Professional Development** (4 questions)
   - Workshops, Training, Certifications, Seminars

### 🎯 Grading System
| Percentage | Grade | Status |
|------------|-------|--------|
| 90-100% | Outstanding | Excellent Performance |
| 80-89% | Excellent | Very Good Performance |
| 70-79% | Very Good | Good Performance |
| 60-69% | Good | Satisfactory |
| 50-59% | Fair | Needs Improvement |
| Below 50% | Poor | Unsatisfactory |

### 🔒 Security Features
- CSRF Protection
- Input Sanitization
- XSS Protection
- SQL Injection Prevention (via prepared statements)
- Spam Protection (Honeypot)
- File Validation

### 📧 Email Integration
- PHPMailer integration
- Automatic email with Excel attachment
- Professional HTML email template
- Configurable SMTP settings

### 📊 Excel Export
- Professional formatted Excel file
- All form data included
- Scores and calculations
- Summary section with grades
- Print-ready layout

### 🎨 User Experience
- Modern, responsive design
- Bootstrap 5
- Collapsible sections
- Progress bar
- Real-time calculations
- Loading spinners
- Toast notifications
- Print-friendly layout

## File Structure

```
annual-performance-evaluation/
├── index.html          # Main HTML form
├── style.css          # CSS styling
├── script.js          # JavaScript functionality
├── generate_excel.php # PhpSpreadsheet Excel generator
├── mail.php           # PHPMailer email handler
├── functions.php      # WordPress integration
├── composer.json      # Dependencies
├── SPEC.md           # Project specification
└── README.md         # This file
```

## Installation

### Option 1: Standalone (Non-WordPress)

1. **Install Dependencies**
   ```bash
   composer install
   ```

2. **Configure Email Settings**
   
   Edit `generate_excel.php` or set environment variables:
   ```bash
   export SMTP_HOST=smtp.gmail.com
   export SMTP_USERNAME=your-email@gmail.com
   export SMTP_PASSWORD=your-app-password
   export EMAIL_FROM_ADDRESS=noreply@yourinstitution.edu.ng
   export EMAIL_TO_ADDRESS=evaluation@yourinstitution.edu.ng
   ```

3. **Upload Files**
   
   Upload all files to your web server (public_html or subdirectory).

4. **Access the Form**
   
   Navigate to `index.html` in your browser.

### Option 2: WordPress Integration

1. **Upload Files**
   
   Upload all files to your WordPress theme or plugin directory:
   ```
   wp-content/themes/your-theme/annual-evaluation/
   ```

2. **Install Dependencies**
   
   Upload via FTP/SFTP, then run:
   ```bash
   composer install
   ```
   
   Or use a tool like Composerize to generate a proper plugin.

3. **Configure SMTP**
   
   Add to your theme's `functions.php` or use a WordPress SMTP plugin:
   ```php
   add_filter('aper_smtp_config', function($config) {
       return [
           'host' => 'smtp.gmail.com',
           'username' => 'your-email@gmail.com',
           'password' => 'your-app-password',
           'from_address' => 'noreply@yourinstitution.edu.ng',
           'to_address' => 'evaluation@yourinstitution.edu.ng'
       ];
   });
   ```

4. **Add Shortcode**
   
   Add to any page or post:
   ```
   [annual_performance_evaluation]
   ```

5. **Configure Settings (Optional)**
   
   Go to WordPress Admin > Settings > Performance Evaluation

## Configuration

### Email Settings

Set these environment variables for email functionality:

```bash
# SMTP Configuration
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password

# Email Configuration
EMAIL_FROM_ADDRESS=noreply@yourinstitution.edu.ng
EMAIL_FROM_NAME=Performance Evaluation System
EMAIL_TO_ADDRESS=evaluation@yourinstitution.edu.ng
```

**Note:** For Gmail, you need to use an [App Password](https://support.google.com/accounts/answer/185833).

### WordPress Shortcode Options

```
[annual_performance_evaluation]
[annual_performance_evaluation institution="University Name"]
[annual_performance_evaluation show_logo="true"]
[annual_performance_evaluation require_login="true"]
```

### WordPress Settings

In WordPress Admin, go to **Settings > Performance Evaluation** to configure:
- Institution Name
- Logo URL
- Email To/From Addresses

## Usage

### For Staff
1. Fill in Institution Details
2. Enter Staff Information
3. Complete Performance Evaluation (rate all 23 questions)
4. Wait for Supervisor and Management sections to be filled

### For Supervisors
1. Enter Supervisor Name and Designation
2. Provide overall rating
3. Add recommendation (Promote, Confirm, Continue, Probation, Terminate)
4. Add remarks and digital signature

### For Management
1. Enter Registrar Name
2. Set approval status
3. Add remarks and approval signature

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome for Android)

## Requirements

### Server Requirements
- PHP 7.4 or higher
- PHP extensions:
  - php_zip
  - php_xml
  - php_gd2
  - php_mbstring
  - php_openssl
- Web server (Apache, Nginx, LiteSpeed)
- Composer (for dependency management)

### WordPress Requirements
- WordPress 5.0 or higher
- PHP 7.4 or higher

## Security Considerations

### File Permissions
```
uploads/     - 755 (read/write for web server)
vendor/      - 755 (read only)
```

### HTTPS
Always use HTTPS in production to protect sensitive data.

### Input Validation
All inputs are sanitized and validated on both client and server sides.

### Rate Limiting
Consider implementing rate limiting for form submissions to prevent abuse.

## Troubleshooting

### Email Not Sending
1. Check SMTP credentials
2. Verify firewall settings
3. Check spam folder
4. Enable debug mode in `mail.php`

### Excel Not Generating
1. Check PHP extensions (zip, xml)
2. Verify upload directory exists and is writable
3. Check PHP error logs

### Form Not Submitting
1. Check browser console for JavaScript errors
2. Verify CSRF token is being generated
3. Check PHP error logs

### WordPress Conflicts
1. Ensure jQuery is loaded
2. Check for CSS conflicts with theme
3. Verify shortcode is being rendered

## Customization

### Changing Colors
Edit `style.css`:
```css
:root {
    --primary-blue: #1e3a8a;
    --secondary-blue: #3b82f6;
    /* ... */
}
```

### Adding Questions
1. Add to `index.html` in Section 3
2. Update `script.js` - CONFIG.totalQuestions
3. Update `generate_excel.php` - categories array
4. Update scoring in `script.js`

### Adding Categories
1. Add in `index.html` Section 3
2. Add in `generate_excel.php`
3. Update JavaScript CONFIG

## Support

For issues and feature requests:
- Email: support@yourinstitution.edu.ng
- Create an issue in your internal ticketing system

## License

MIT License - See LICENSE file for details.

## Credits

- [Bootstrap 5](https://getbootstrap.com/)
- [Font Awesome](https://fontawesome.com/)
- [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet)
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)

## Changelog

### v1.0.0
- Initial release
- Complete form with 5 sections
- 23 evaluation questions across 5 categories
- Automatic calculations
- Excel export with PhpSpreadsheet
- Email notification with PHPMailer
- WordPress shortcode integration
- Responsive design
- Print-friendly layout
- Security features (CSRF, XSS, SQL injection protection)

---

**Version:** 1.0.0  
**Last Updated:** July 2026  
**Author:** Your Institution