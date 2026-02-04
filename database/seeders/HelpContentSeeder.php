<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;

class HelpContentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Essential guides to help you set up and start using KasamaHR',
                'icon' => 'rocket',
                'sort_order' => 1,
                'articles' => [
                    [
                        'title' => 'Welcome to KasamaHR',
                        'slug' => 'welcome',
                        'excerpt' => 'An introduction to KasamaHR and its core features.',
                        'content' => '<h2>Welcome to KasamaHR</h2><p>KasamaHR is a comprehensive Human Resource Information System (HRIS) designed to streamline HR operations for Philippine businesses.</p><h3>Key Features</h3><ul><li><strong>Employee Management</strong> - Centralized employee records and profiles</li><li><strong>Time & Attendance</strong> - Track work hours with biometric integration</li><li><strong>Payroll Processing</strong> - Automated salary computation with Philippine compliance</li><li><strong>Leave Management</strong> - Handle leave requests and balances</li><li><strong>Performance Management</strong> - Set goals and track employee performance</li></ul><p>Use the navigation menu on the left to explore different features.</p>',
                        'is_featured' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Quick Start Guide',
                        'slug' => 'quick-start',
                        'excerpt' => 'Get up and running with KasamaHR in minutes.',
                        'content' => '<h2>Quick Start Guide</h2><p>Follow these steps to get started with KasamaHR:</p><h3>Step 1: Complete Your Profile</h3><p>Navigate to Settings > Profile to update your personal information.</p><h3>Step 2: Explore the Dashboard</h3><p>Your dashboard shows key metrics and quick access to common tasks.</p><h3>Step 3: Set Up Two-Factor Authentication</h3><p>For enhanced security, enable 2FA in Settings > Two-Factor Auth.</p>',
                        'is_featured' => true,
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Employee Management',
                'slug' => 'employee-management',
                'description' => 'Learn how to manage employee records, profiles, and assignments',
                'icon' => 'users',
                'sort_order' => 2,
                'articles' => [
                    [
                        'title' => 'Adding New Employees',
                        'slug' => 'adding-employees',
                        'excerpt' => 'Step-by-step guide to adding new employee records.',
                        'content' => '<h2>Adding New Employees</h2><p>HR administrators can add new employees through the Employees section.</p><h3>Required Information</h3><ul><li>Full Name</li><li>Email Address</li><li>Employee Number</li><li>Department and Position</li><li>Government IDs (SSS, PhilHealth, Pag-IBIG, TIN)</li></ul><h3>Steps</h3><ol><li>Go to Employees > Add Employee</li><li>Fill in the required fields</li><li>Upload profile photo (optional)</li><li>Click Save to create the employee record</li></ol>',
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Managing Employee Profiles',
                        'slug' => 'managing-profiles',
                        'excerpt' => 'How to view and update employee information.',
                        'content' => '<h2>Managing Employee Profiles</h2><p>Employee profiles contain all relevant information about an employee.</p><h3>Profile Sections</h3><ul><li><strong>Personal Information</strong> - Basic details and contact info</li><li><strong>Employment Details</strong> - Position, department, hire date</li><li><strong>Government IDs</strong> - SSS, PhilHealth, Pag-IBIG, TIN numbers</li><li><strong>Documents</strong> - Uploaded files and certificates</li><li><strong>Compensation</strong> - Salary and benefits information</li></ul>',
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Time & Attendance',
                'slug' => 'time-attendance',
                'description' => 'Track work hours, manage schedules, and view attendance records',
                'icon' => 'clock',
                'sort_order' => 3,
                'articles' => [
                    [
                        'title' => 'Understanding Your Daily Time Record',
                        'slug' => 'daily-time-record',
                        'excerpt' => 'How to view and understand your DTR.',
                        'content' => '<h2>Understanding Your Daily Time Record</h2><p>The Daily Time Record (DTR) shows your attendance for each workday.</p><h3>DTR Information</h3><ul><li><strong>Time In/Out</strong> - Your clock in and out times</li><li><strong>Overtime</strong> - Any approved overtime hours</li><li><strong>Late/Undertime</strong> - Minutes late or early departure</li><li><strong>Status</strong> - Present, Absent, On Leave, Holiday</li></ul><h3>Viewing Your DTR</h3><p>Go to My DTR from the self-service menu to view your attendance records.</p>',
                        'is_featured' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Biometric Time Tracking',
                        'slug' => 'biometric-tracking',
                        'excerpt' => 'How biometric devices capture your attendance.',
                        'content' => '<h2>Biometric Time Tracking</h2><p>KasamaHR integrates with biometric devices for accurate time tracking.</p><h3>How It Works</h3><ol><li>Scan your fingerprint or face at the biometric device</li><li>The system records your timestamp</li><li>Data syncs to KasamaHR automatically</li><li>View your records in the DTR section</li></ol><h3>Troubleshooting</h3><p>If your time entry is missing, contact HR to manually add or verify the record.</p>',
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Leave Management',
                'slug' => 'leave-management',
                'description' => 'Request time off, check balances, and manage leave approvals',
                'icon' => 'calendar',
                'sort_order' => 4,
                'articles' => [
                    [
                        'title' => 'Filing a Leave Request',
                        'slug' => 'filing-leave',
                        'excerpt' => 'How to submit a leave request.',
                        'content' => '<h2>Filing a Leave Request</h2><p>Submit leave requests through the self-service portal.</p><h3>Steps</h3><ol><li>Go to My Leaves > File Leave</li><li>Select the leave type</li><li>Choose start and end dates</li><li>Add any notes or attachments</li><li>Submit for approval</li></ol><h3>Leave Types</h3><ul><li>Vacation Leave</li><li>Sick Leave</li><li>Emergency Leave</li><li>Maternity/Paternity Leave</li></ul>',
                        'is_featured' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Checking Leave Balances',
                        'slug' => 'leave-balances',
                        'excerpt' => 'View your available leave credits.',
                        'content' => '<h2>Checking Leave Balances</h2><p>Stay informed about your remaining leave credits.</p><h3>Viewing Balances</h3><p>Go to My Leaves to see your current balances for each leave type.</p><h3>Understanding Leave Accrual</h3><p>Leave credits typically accrue monthly based on company policy. Check with HR for specific accrual rates.</p>',
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Payroll',
                'slug' => 'payroll',
                'description' => 'Understand payroll processing, payslips, and deductions',
                'icon' => 'banknote',
                'sort_order' => 5,
                'articles' => [
                    [
                        'title' => 'Understanding Your Payslip',
                        'slug' => 'understanding-payslip',
                        'excerpt' => 'A guide to reading and understanding your payslip.',
                        'content' => '<h2>Understanding Your Payslip</h2><p>Your payslip provides a breakdown of your earnings and deductions.</p><h3>Earnings Section</h3><ul><li>Basic Pay</li><li>Overtime Pay</li><li>Holiday Pay</li><li>Allowances</li></ul><h3>Deductions Section</h3><ul><li>SSS Contribution</li><li>PhilHealth Contribution</li><li>Pag-IBIG Contribution</li><li>Withholding Tax</li><li>Loans (if any)</li></ul><h3>Net Pay</h3><p>This is your take-home pay after all deductions.</p>',
                        'is_featured' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Government Contributions',
                        'slug' => 'government-contributions',
                        'excerpt' => 'Learn about SSS, PhilHealth, and Pag-IBIG deductions.',
                        'content' => '<h2>Government Contributions</h2><p>As required by Philippine law, certain contributions are deducted from your salary.</p><h3>SSS (Social Security System)</h3><p>Provides benefits for disability, sickness, maternity, and retirement.</p><h3>PhilHealth</h3><p>Provides health insurance coverage for hospitalization and medical expenses.</p><h3>Pag-IBIG</h3><p>A savings program that also provides housing loans.</p><h3>Withholding Tax</h3><p>Income tax withheld based on BIR tax tables.</p>',
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Performance Management',
                'slug' => 'performance-management',
                'description' => 'Set goals, track progress, and participate in performance reviews',
                'icon' => 'target',
                'sort_order' => 6,
                'articles' => [
                    [
                        'title' => 'Setting Performance Goals',
                        'slug' => 'setting-goals',
                        'excerpt' => 'How to create and manage your performance goals.',
                        'content' => '<h2>Setting Performance Goals</h2><p>Goals help you and your manager track your progress and development.</p><h3>Creating Goals</h3><ol><li>Go to Performance > My Goals</li><li>Click Add Goal</li><li>Define the goal title and description</li><li>Set key results and milestones</li><li>Assign a target date</li></ol><h3>SMART Goals</h3><p>Make your goals Specific, Measurable, Achievable, Relevant, and Time-bound.</p>',
                        'sort_order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Recruitment',
                'slug' => 'recruitment',
                'description' => 'Manage job postings, applications, and the hiring process',
                'icon' => 'briefcase',
                'sort_order' => 7,
                'articles' => [
                    [
                        'title' => 'Creating Job Requisitions',
                        'slug' => 'job-requisitions',
                        'excerpt' => 'How to request new hires through the requisition process.',
                        'content' => '<h2>Creating Job Requisitions</h2><p>Start the hiring process by submitting a job requisition.</p><h3>Steps</h3><ol><li>Go to Recruitment > New Requisition</li><li>Select the position and department</li><li>Specify number of openings</li><li>Add job description and requirements</li><li>Submit for approval</li></ol>',
                        'sort_order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Training & Compliance',
                'slug' => 'training-compliance',
                'description' => 'Access training courses and complete compliance requirements',
                'icon' => 'graduation-cap',
                'sort_order' => 8,
                'articles' => [
                    [
                        'title' => 'Accessing Training Courses',
                        'slug' => 'accessing-training',
                        'excerpt' => 'How to enroll in and complete training courses.',
                        'content' => '<h2>Accessing Training Courses</h2><p>KasamaHR provides access to online training and compliance courses.</p><h3>Finding Courses</h3><ol><li>Go to My Training from the menu</li><li>Browse available courses</li><li>Enroll in courses relevant to your role</li><li>Complete modules at your own pace</li></ol><h3>Tracking Progress</h3><p>Your training dashboard shows completed, in-progress, and assigned courses.</p>',
                        'sort_order' => 1,
                    ],
                ],
            ],
            [
                'name' => 'Self-Service Portal',
                'slug' => 'self-service',
                'description' => 'Access your personal information, documents, and requests',
                'icon' => 'user',
                'sort_order' => 9,
                'articles' => [
                    [
                        'title' => 'Self-Service Overview',
                        'slug' => 'self-service-overview',
                        'excerpt' => 'What you can do in the self-service portal.',
                        'content' => '<h2>Self-Service Overview</h2><p>The self-service portal gives you control over your HR information.</p><h3>Available Features</h3><ul><li>View and update personal information</li><li>Check your DTR and attendance</li><li>File and track leave requests</li><li>Access payslips and tax documents</li><li>Request documents (COE, clearance, etc.)</li><li>View loans and apply for new ones</li></ul>',
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Requesting Documents',
                        'slug' => 'requesting-documents',
                        'excerpt' => 'How to request certificates and official documents.',
                        'content' => '<h2>Requesting Documents</h2><p>Request official documents through the self-service portal.</p><h3>Available Documents</h3><ul><li>Certificate of Employment (COE)</li><li>ITR/BIR Forms</li><li>Leave Certificate</li><li>Clearance</li></ul><h3>How to Request</h3><ol><li>Go to My Documents > Request Document</li><li>Select document type</li><li>Add any special requirements</li><li>Submit request</li></ol>',
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Manager Functions',
                'slug' => 'manager-functions',
                'description' => 'Tools and features available to managers and supervisors',
                'icon' => 'user-check',
                'sort_order' => 10,
                'articles' => [
                    [
                        'title' => 'Approving Leave Requests',
                        'slug' => 'approving-leaves',
                        'excerpt' => 'How to review and approve team leave requests.',
                        'content' => '<h2>Approving Leave Requests</h2><p>As a manager, you can approve or reject leave requests from your team.</p><h3>Steps</h3><ol><li>Check your notifications for pending requests</li><li>Go to Team > Leave Approvals</li><li>Review the request details</li><li>Approve or reject with comments</li></ol><h3>Best Practices</h3><p>Respond to requests promptly and consider team coverage before approving.</p>',
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Team Dashboard',
                        'slug' => 'team-dashboard',
                        'excerpt' => 'Monitor your team\'s attendance and performance.',
                        'content' => '<h2>Team Dashboard</h2><p>Get a quick overview of your team\'s status and activities.</p><h3>Dashboard Features</h3><ul><li>Team attendance summary</li><li>Pending approvals</li><li>Leave calendar</li><li>Performance status</li></ul>',
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Admin Functions',
                'slug' => 'admin-functions',
                'description' => 'Administrative features for HR and system administrators',
                'icon' => 'settings',
                'sort_order' => 11,
                'articles' => [
                    [
                        'title' => 'Managing Users and Access',
                        'slug' => 'managing-users',
                        'excerpt' => 'How to add users and manage their access levels.',
                        'content' => '<h2>Managing Users and Access</h2><p>Administrators can control who has access to the system.</p><h3>User Roles</h3><ul><li><strong>Employee</strong> - Basic self-service access</li><li><strong>Manager</strong> - Team management features</li><li><strong>HR</strong> - Full employee management</li><li><strong>Admin</strong> - System configuration</li></ul><h3>Adding Users</h3><p>Go to Users > Invite User to add new system users.</p>',
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'System Configuration',
                        'slug' => 'system-configuration',
                        'excerpt' => 'Configure organization settings and preferences.',
                        'content' => '<h2>System Configuration</h2><p>Customize KasamaHR to match your organization\'s needs.</p><h3>Configuration Areas</h3><ul><li>Company information and branding</li><li>Work schedules and holidays</li><li>Leave types and policies</li><li>Payroll settings</li><li>Contribution tables</li></ul><h3>Accessing Settings</h3><p>Navigate to Organization > Settings to access configuration options.</p>',
                        'sort_order' => 2,
                    ],
                ],
            ],
            [
                'name' => 'Troubleshooting',
                'slug' => 'troubleshooting',
                'description' => 'Common issues and how to resolve them',
                'icon' => 'help-circle',
                'sort_order' => 12,
                'articles' => [
                    [
                        'title' => 'Common Login Issues',
                        'slug' => 'login-issues',
                        'excerpt' => 'Solutions for login and authentication problems.',
                        'content' => '<h2>Common Login Issues</h2><h3>Forgot Password</h3><p>Click "Forgot Password" on the login page to receive a reset link.</p><h3>Account Locked</h3><p>After multiple failed attempts, your account may be locked. Contact HR to unlock it.</p><h3>Two-Factor Authentication Issues</h3><p>If you can\'t access your 2FA codes, contact HR to reset your authentication.</p>',
                        'sort_order' => 1,
                    ],
                    [
                        'title' => 'Getting Help',
                        'slug' => 'getting-help',
                        'excerpt' => 'How to get support when you need it.',
                        'content' => '<h2>Getting Help</h2><p>If you can\'t find an answer in the Help Center, here are your options:</p><h3>Contact HR</h3><p>For HR-related inquiries, reach out to your HR department directly.</p><h3>System Issues</h3><p>For technical problems, contact your system administrator.</p><h3>Feedback</h3><p>We value your input! Share suggestions for improving the system with your administrator.</p>',
                        'sort_order' => 2,
                    ],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $articles = $categoryData['articles'] ?? [];
            unset($categoryData['articles']);

            $category = HelpCategory::create($categoryData);

            foreach ($articles as $articleData) {
                $articleData['help_category_id'] = $category->id;
                $articleData['is_active'] = true;
                $articleData['is_featured'] = $articleData['is_featured'] ?? false;
                HelpArticle::create($articleData);
            }
        }
    }
}
