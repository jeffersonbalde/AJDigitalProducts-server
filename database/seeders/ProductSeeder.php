<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all categories from the database
        $categories = ProductCategory::pluck('name')->toArray();

        // Sample products data matching the categories
        $products = [
            // Business Category
            [
                'title' => 'Business Plan Template',
                'subtitle' => 'Professional business plan template for startups and entrepreneurs',
                'price' => 29.99,
                'old_price' => 49.99,
                'on_sale' => true,
                'category' => 'Business',
                'description' => 'Comprehensive business plan template with financial projections, market analysis, and executive summary sections.',
                'is_active' => true,
            ],
            [
                'title' => 'Business Model Canvas',
                'subtitle' => 'Strategic business model canvas for planning and innovation',
                'price' => 19.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Business',
                'description' => 'Visual business model canvas template to map out your business strategy and value proposition.',
                'is_active' => true,
            ],

            // Finance Category
            [
                'title' => 'Personal Budget Planner',
                'subtitle' => 'Monthly and annual budget planning spreadsheet',
                'price' => 14.99,
                'old_price' => 24.99,
                'on_sale' => true,
                'category' => 'Finance',
                'description' => 'Comprehensive budget planner with expense tracking, income management, and savings goals.',
                'is_active' => true,
            ],
            [
                'title' => 'Financial Dashboard',
                'subtitle' => 'Real-time financial tracking and reporting tool',
                'price' => 39.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Finance',
                'description' => 'Interactive financial dashboard with charts, graphs, and key performance indicators.',
                'is_active' => true,
            ],

            // Productivity Category
            [
                'title' => 'Daily Task Manager',
                'subtitle' => 'Productive task management and time tracking system',
                'price' => 12.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Productivity',
                'description' => 'Efficient task management template with priority levels, deadlines, and progress tracking.',
                'is_active' => true,
            ],
            [
                'title' => 'Weekly Planner Template',
                'subtitle' => 'Organize your week with this comprehensive planner',
                'price' => 9.99,
                'old_price' => 14.99,
                'on_sale' => true,
                'category' => 'Productivity',
                'description' => 'Weekly planning template with time blocks, goals, and habit tracking.',
                'is_active' => true,
            ],

            // Personal Category
            [
                'title' => 'Personal Goal Tracker',
                'subtitle' => 'Track and achieve your personal goals',
                'price' => 11.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Personal',
                'description' => 'Goal setting and tracking template with milestones and progress visualization.',
                'is_active' => true,
            ],
            [
                'title' => 'Habit Tracker',
                'subtitle' => 'Build and maintain positive habits',
                'price' => 7.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Personal',
                'description' => 'Daily habit tracking template with streaks, statistics, and motivation features.',
                'is_active' => true,
            ],

            // Bundle Category
            [
                'title' => 'Business Starter Bundle',
                'subtitle' => 'Complete business toolkit with 10 essential templates',
                'price' => 99.99,
                'old_price' => 199.99,
                'on_sale' => true,
                'category' => 'Bundle',
                'description' => 'Comprehensive bundle including business plan, financial dashboard, CRM, and more.',
                'is_active' => true,
            ],
            [
                'title' => 'Productivity Master Pack',
                'subtitle' => 'All-in-one productivity suite',
                'price' => 79.99,
                'old_price' => 149.99,
                'on_sale' => true,
                'category' => 'Bundle',
                'description' => 'Complete productivity bundle with task managers, planners, and time tracking tools.',
                'is_active' => true,
            ],

            // Spreadsheet Templates Category
            [
                'title' => 'Excel Invoice Template',
                'subtitle' => 'Professional invoice template for Excel',
                'price' => 15.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Spreadsheet Templates',
                'description' => 'Professional invoice template with automatic calculations and branding options.',
                'is_active' => true,
            ],
            [
                'title' => 'Google Sheets Budget Template',
                'subtitle' => 'Cloud-based budget planning template',
                'price' => 12.99,
                'old_price' => 19.99,
                'on_sale' => true,
                'category' => 'Spreadsheet Templates',
                'description' => 'Collaborative budget template for Google Sheets with real-time updates.',
                'is_active' => true,
            ],

            // Project Management Category
            [
                'title' => 'Project Timeline Template',
                'subtitle' => 'Visual project timeline and Gantt chart',
                'price' => 24.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Project Management',
                'description' => 'Professional project timeline template with task dependencies and milestones.',
                'is_active' => true,
            ],
            [
                'title' => 'Agile Sprint Planner',
                'subtitle' => 'Scrum and agile project management template',
                'price' => 19.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Project Management',
                'description' => 'Agile sprint planning template with backlog management and velocity tracking.',
                'is_active' => true,
            ],

            // Inventory Management Category
            [
                'title' => 'Inventory Tracking System',
                'subtitle' => 'Complete inventory management solution',
                'price' => 34.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Inventory Management',
                'description' => 'Comprehensive inventory tracking with stock levels, reorder points, and reporting.',
                'is_active' => true,
            ],
            [
                'title' => 'Warehouse Management Template',
                'subtitle' => 'Warehouse operations and logistics tracker',
                'price' => 29.99,
                'old_price' => 39.99,
                'on_sale' => true,
                'category' => 'Inventory Management',
                'description' => 'Warehouse management template with location tracking and inventory movements.',
                'is_active' => true,
            ],

            // Sales & Marketing Category
            [
                'title' => 'Sales Pipeline Tracker',
                'subtitle' => 'Manage your sales funnel and conversions',
                'price' => 22.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Sales & Marketing',
                'description' => 'Sales pipeline template with lead tracking, conversion rates, and revenue forecasting.',
                'is_active' => true,
            ],
            [
                'title' => 'Marketing Campaign Planner',
                'subtitle' => 'Plan and track marketing campaigns',
                'price' => 27.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Sales & Marketing',
                'description' => 'Marketing campaign planning template with budget tracking and ROI analysis.',
                'is_active' => true,
            ],

            // HR & Payroll Category
            [
                'title' => 'Employee Database Template',
                'subtitle' => 'Comprehensive employee management system',
                'price' => 31.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'HR & Payroll',
                'description' => 'Employee database template with contact info, roles, and employment history.',
                'is_active' => true,
            ],
            [
                'title' => 'Payroll Calculator',
                'subtitle' => 'Automated payroll calculation template',
                'price' => 26.99,
                'old_price' => 34.99,
                'on_sale' => true,
                'category' => 'HR & Payroll',
                'description' => 'Payroll calculation template with tax deductions and salary computations.',
                'is_active' => true,
            ],

            // Accounting Category
            [
                'title' => 'General Ledger Template',
                'subtitle' => 'Professional accounting ledger system',
                'price' => 32.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Accounting',
                'description' => 'General ledger template with double-entry bookkeeping and financial reporting.',
                'is_active' => true,
            ],
            [
                'title' => 'Balance Sheet Template',
                'subtitle' => 'Financial statement and balance sheet generator',
                'price' => 28.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Accounting',
                'description' => 'Balance sheet template with automatic calculations and financial ratios.',
                'is_active' => true,
            ],

            // Invoice & Billing Category
            [
                'title' => 'Professional Invoice System',
                'subtitle' => 'Complete invoicing and billing solution',
                'price' => 18.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Invoice & Billing',
                'description' => 'Professional invoice template with payment tracking and client management.',
                'is_active' => true,
            ],
            [
                'title' => 'Recurring Billing Tracker',
                'subtitle' => 'Manage subscriptions and recurring payments',
                'price' => 21.99,
                'old_price' => 29.99,
                'on_sale' => true,
                'category' => 'Invoice & Billing',
                'description' => 'Recurring billing template with subscription management and payment reminders.',
                'is_active' => true,
            ],

            // Expense Tracking Category
            [
                'title' => 'Expense Report Template',
                'subtitle' => 'Track business and personal expenses',
                'price' => 13.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Expense Tracking',
                'description' => 'Expense tracking template with receipt management and categorization.',
                'is_active' => true,
            ],
            [
                'title' => 'Travel Expense Tracker',
                'subtitle' => 'Manage travel expenses and reimbursements',
                'price' => 16.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Expense Tracking',
                'description' => 'Travel expense template with per diem calculations and mileage tracking.',
                'is_active' => true,
            ],

            // Budget Planning Category
            [
                'title' => 'Annual Budget Planner',
                'subtitle' => 'Year-long budget planning and tracking',
                'price' => 17.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Budget Planning',
                'description' => 'Annual budget planner with monthly breakdowns and variance analysis.',
                'is_active' => true,
            ],
            [
                'title' => 'Zero-Based Budget Template',
                'subtitle' => 'Zero-based budgeting system',
                'price' => 14.99,
                'old_price' => 19.99,
                'on_sale' => true,
                'category' => 'Budget Planning',
                'description' => 'Zero-based budget template where every dollar is assigned a purpose.',
                'is_active' => true,
            ],

            // Customer Relationship Category
            [
                'title' => 'CRM Template',
                'subtitle' => 'Customer relationship management system',
                'price' => 35.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Customer Relationship',
                'description' => 'CRM template with contact management, interaction history, and follow-up tracking.',
                'is_active' => true,
            ],
            [
                'title' => 'Customer Feedback Tracker',
                'subtitle' => 'Collect and analyze customer feedback',
                'price' => 20.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Customer Relationship',
                'description' => 'Customer feedback template with survey results and satisfaction metrics.',
                'is_active' => true,
            ],

            // Event Planning Category
            [
                'title' => 'Wedding Planner Template',
                'subtitle' => 'Complete wedding planning organizer',
                'price' => 23.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Event Planning',
                'description' => 'Wedding planning template with vendor management, timeline, and budget tracking.',
                'is_active' => true,
            ],
            [
                'title' => 'Corporate Event Planner',
                'subtitle' => 'Professional event planning system',
                'price' => 25.99,
                'old_price' => 34.99,
                'on_sale' => true,
                'category' => 'Event Planning',
                'description' => 'Corporate event planning template with attendee management and logistics.',
                'is_active' => true,
            ],

            // Content Creation Category
            [
                'title' => 'Content Calendar Template',
                'subtitle' => 'Plan and schedule your content',
                'price' => 19.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Content Creation',
                'description' => 'Content calendar template with publishing schedules and content ideas.',
                'is_active' => true,
            ],
            [
                'title' => 'Social Media Planner',
                'subtitle' => 'Social media content planning and scheduling',
                'price' => 16.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Content Creation',
                'description' => 'Social media planning template for multiple platforms with engagement tracking.',
                'is_active' => true,
            ],

            // Education Category
            [
                'title' => 'Grade Book Template',
                'subtitle' => 'Teacher grade book and student tracker',
                'price' => 15.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Education',
                'description' => 'Grade book template with automatic grade calculations and student progress tracking.',
                'is_active' => true,
            ],
            [
                'title' => 'Lesson Plan Template',
                'subtitle' => 'Structured lesson planning system',
                'price' => 12.99,
                'old_price' => 17.99,
                'on_sale' => true,
                'category' => 'Education',
                'description' => 'Lesson plan template with learning objectives, activities, and assessment sections.',
                'is_active' => true,
            ],

            // Real Estate Category
            [
                'title' => 'Property Management System',
                'subtitle' => 'Complete property and rental management',
                'price' => 38.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Real Estate',
                'description' => 'Property management template with tenant tracking, rent collection, and maintenance logs.',
                'is_active' => true,
            ],
            [
                'title' => 'Real Estate Investment Tracker',
                'subtitle' => 'Track property investments and ROI',
                'price' => 33.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Real Estate',
                'description' => 'Real estate investment template with cash flow analysis and property comparisons.',
                'is_active' => true,
            ],

            // Fitness & Health Category
            [
                'title' => 'Workout Planner',
                'subtitle' => 'Plan and track your fitness journey',
                'price' => 11.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Fitness & Health',
                'description' => 'Workout planning template with exercise library, progress tracking, and goal setting.',
                'is_active' => true,
            ],
            [
                'title' => 'Meal Planning Template',
                'subtitle' => 'Plan meals and track nutrition',
                'price' => 13.99,
                'old_price' => 18.99,
                'on_sale' => true,
                'category' => 'Fitness & Health',
                'description' => 'Meal planning template with recipes, grocery lists, and nutritional information.',
                'is_active' => true,
            ],

            // Travel Planning Category
            [
                'title' => 'Travel Itinerary Planner',
                'subtitle' => 'Plan your perfect trip',
                'price' => 10.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Travel Planning',
                'description' => 'Travel itinerary template with flight details, accommodations, and activity schedules.',
                'is_active' => true,
            ],
            [
                'title' => 'Trip Budget Tracker',
                'subtitle' => 'Manage travel expenses and budget',
                'price' => 9.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Travel Planning',
                'description' => 'Travel budget template with expense categories and currency conversion.',
                'is_active' => true,
            ],

            // Home Management Category
            [
                'title' => 'Home Maintenance Schedule',
                'subtitle' => 'Track home maintenance and repairs',
                'price' => 14.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Home Management',
                'description' => 'Home maintenance template with seasonal tasks and service provider contacts.',
                'is_active' => true,
            ],
            [
                'title' => 'Household Budget Planner',
                'subtitle' => 'Manage household finances and expenses',
                'price' => 12.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Home Management',
                'description' => 'Household budget template with bill tracking and savings goals.',
                'is_active' => true,
            ],

            // Time Tracking Category
            [
                'title' => 'Employee Time Tracker',
                'subtitle' => 'Track employee hours and attendance',
                'price' => 24.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Time Tracking',
                'description' => 'Time tracking template with clock in/out, break times, and payroll integration.',
                'is_active' => true,
            ],
            [
                'title' => 'Project Time Tracker',
                'subtitle' => 'Track time spent on projects and tasks',
                'price' => 18.99,
                'old_price' => 24.99,
                'on_sale' => true,
                'category' => 'Time Tracking',
                'description' => 'Project time tracking template with billable hours and productivity analysis.',
                'is_active' => true,
            ],

            // Goal Setting Category
            [
                'title' => 'SMART Goals Tracker',
                'subtitle' => 'Set and achieve SMART goals',
                'price' => 10.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Goal Setting',
                'description' => 'SMART goals template with specific, measurable, achievable, relevant, and time-bound objectives.',
                'is_active' => true,
            ],
            [
                'title' => 'Annual Goals Planner',
                'subtitle' => 'Plan and track annual goals',
                'price' => 12.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Goal Setting',
                'description' => 'Annual goal planning template with quarterly reviews and progress tracking.',
                'is_active' => true,
            ],

            // Data Analysis Category
            [
                'title' => 'Data Analysis Dashboard',
                'subtitle' => 'Analyze and visualize your data',
                'price' => 36.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Data Analysis',
                'description' => 'Data analysis template with pivot tables, charts, and statistical functions.',
                'is_active' => true,
            ],
            [
                'title' => 'Sales Analytics Template',
                'subtitle' => 'Analyze sales performance and trends',
                'price' => 29.99,
                'old_price' => 39.99,
                'on_sale' => true,
                'category' => 'Data Analysis',
                'description' => 'Sales analytics template with trend analysis, forecasting, and KPI tracking.',
                'is_active' => true,
            ],

            // Quality Control Category
            [
                'title' => 'Quality Inspection Checklist',
                'subtitle' => 'Quality assurance and inspection system',
                'price' => 22.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Quality Control',
                'description' => 'Quality control checklist template with inspection criteria and defect tracking.',
                'is_active' => true,
            ],
            [
                'title' => 'Product Quality Tracker',
                'subtitle' => 'Track product quality metrics',
                'price' => 27.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Quality Control',
                'description' => 'Product quality tracking template with defect rates and improvement plans.',
                'is_active' => true,
            ],

            // Supply Chain Category
            [
                'title' => 'Supply Chain Tracker',
                'subtitle' => 'Manage supply chain operations',
                'price' => 37.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Supply Chain',
                'description' => 'Supply chain management template with supplier tracking and logistics.',
                'is_active' => true,
            ],
            [
                'title' => 'Vendor Performance Tracker',
                'subtitle' => 'Monitor vendor and supplier performance',
                'price' => 30.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Supply Chain',
                'description' => 'Vendor performance template with delivery tracking and quality metrics.',
                'is_active' => true,
            ],

            // Legal Documents Category
            [
                'title' => 'Contract Management System',
                'subtitle' => 'Track contracts and legal documents',
                'price' => 40.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Legal Documents',
                'description' => 'Contract management template with renewal dates, terms, and compliance tracking.',
                'is_active' => true,
            ],
            [
                'title' => 'Legal Document Tracker',
                'subtitle' => 'Organize legal documents and filings',
                'price' => 28.99,
                'old_price' => 38.99,
                'on_sale' => true,
                'category' => 'Legal Documents',
                'description' => 'Legal document tracker with filing dates, expiration dates, and document storage.',
                'is_active' => true,
            ],

            // Training & Development Category
            [
                'title' => 'Employee Training Tracker',
                'subtitle' => 'Track employee training and development',
                'price' => 26.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Training & Development',
                'description' => 'Training tracker template with course completion, certifications, and skill development.',
                'is_active' => true,
            ],
            [
                'title' => 'Training Program Planner',
                'subtitle' => 'Plan and schedule training programs',
                'price' => 23.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Training & Development',
                'description' => 'Training program planning template with curriculum, schedules, and participant tracking.',
                'is_active' => true,
            ],

            // Performance Review Category
            [
                'title' => 'Employee Performance Review',
                'subtitle' => 'Conduct comprehensive performance reviews',
                'price' => 25.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Performance Review',
                'description' => 'Performance review template with evaluation criteria, goals, and development plans.',
                'is_active' => true,
            ],
            [
                'title' => '360-Degree Feedback System',
                'subtitle' => 'Collect comprehensive feedback',
                'price' => 31.99,
                'old_price' => 41.99,
                'on_sale' => true,
                'category' => 'Performance Review',
                'description' => '360-degree feedback template with peer, manager, and self-evaluations.',
                'is_active' => true,
            ],

            // Asset Management Category
            [
                'title' => 'Asset Tracking System',
                'subtitle' => 'Track company assets and equipment',
                'price' => 32.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Asset Management',
                'description' => 'Asset tracking template with depreciation calculations and maintenance schedules.',
                'is_active' => true,
            ],
            [
                'title' => 'IT Asset Inventory',
                'subtitle' => 'Manage IT equipment and software licenses',
                'price' => 29.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Asset Management',
                'description' => 'IT asset inventory template with license tracking and warranty management.',
                'is_active' => true,
            ],

            // Risk Management Category
            [
                'title' => 'Risk Assessment Template',
                'subtitle' => 'Identify and manage business risks',
                'price' => 34.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Risk Management',
                'description' => 'Risk assessment template with risk matrix, mitigation strategies, and monitoring.',
                'is_active' => true,
            ],
            [
                'title' => 'Project Risk Tracker',
                'subtitle' => 'Track and mitigate project risks',
                'price' => 27.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Risk Management',
                'description' => 'Project risk tracking template with probability, impact, and response planning.',
                'is_active' => true,
            ],

            // Compliance Category
            [
                'title' => 'Compliance Tracker',
                'subtitle' => 'Track regulatory compliance requirements',
                'price' => 35.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Compliance',
                'description' => 'Compliance tracking template with requirements, deadlines, and audit trails.',
                'is_active' => true,
            ],
            [
                'title' => 'Audit Checklist Template',
                'subtitle' => 'Prepare for audits and inspections',
                'price' => 28.99,
                'old_price' => 38.99,
                'on_sale' => true,
                'category' => 'Compliance',
                'description' => 'Audit checklist template with compliance items and evidence tracking.',
                'is_active' => true,
            ],

            // Vendor Management Category
            [
                'title' => 'Vendor Database Template',
                'subtitle' => 'Manage vendor relationships and contracts',
                'price' => 30.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Vendor Management',
                'description' => 'Vendor management template with contact info, contracts, and performance ratings.',
                'is_active' => true,
            ],
            [
                'title' => 'Supplier Evaluation System',
                'subtitle' => 'Evaluate and rate suppliers',
                'price' => 26.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Vendor Management',
                'description' => 'Supplier evaluation template with quality, delivery, and cost metrics.',
                'is_active' => true,
            ],

            // Meeting Notes Category
            [
                'title' => 'Meeting Notes Template',
                'subtitle' => 'Professional meeting documentation',
                'price' => 8.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Meeting Notes',
                'description' => 'Meeting notes template with agenda, action items, and follow-up tracking.',
                'is_active' => true,
            ],
            [
                'title' => 'Board Meeting Minutes',
                'subtitle' => 'Formal board meeting documentation',
                'price' => 11.99,
                'old_price' => 16.99,
                'on_sale' => true,
                'category' => 'Meeting Notes',
                'description' => 'Board meeting minutes template with formal structure and approval tracking.',
                'is_active' => true,
            ],

            // Workflow Automation Category
            [
                'title' => 'Process Workflow Template',
                'subtitle' => 'Map and automate business processes',
                'price' => 33.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Workflow Automation',
                'description' => 'Workflow template with process mapping, automation triggers, and task assignments.',
                'is_active' => true,
            ],
            [
                'title' => 'Approval Workflow System',
                'subtitle' => 'Manage approval processes',
                'price' => 28.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Workflow Automation',
                'description' => 'Approval workflow template with multi-level approvals and status tracking.',
                'is_active' => true,
            ],

            // Reporting Tools Category
            [
                'title' => 'Business Report Generator',
                'subtitle' => 'Create professional business reports',
                'price' => 31.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Reporting Tools',
                'description' => 'Business report template with charts, graphs, and executive summaries.',
                'is_active' => true,
            ],
            [
                'title' => 'KPI Dashboard Template',
                'subtitle' => 'Track key performance indicators',
                'price' => 35.99,
                'old_price' => 45.99,
                'on_sale' => true,
                'category' => 'Reporting Tools',
                'description' => 'KPI dashboard template with real-time metrics and performance visualization.',
                'is_active' => true,
            ],

            // Strategic Planning Category
            [
                'title' => 'Strategic Plan Template',
                'subtitle' => 'Develop comprehensive strategic plans',
                'price' => 42.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Strategic Planning',
                'description' => 'Strategic planning template with SWOT analysis, goals, and action plans.',
                'is_active' => true,
            ],
            [
                'title' => 'Business Strategy Planner',
                'subtitle' => 'Plan long-term business strategy',
                'price' => 38.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Strategic Planning',
                'description' => 'Business strategy template with vision, mission, and strategic initiatives.',
                'is_active' => true,
            ],

            // Competitive Analysis Category
            [
                'title' => 'Competitor Analysis Template',
                'subtitle' => 'Analyze competitors and market position',
                'price' => 36.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Competitive Analysis',
                'description' => 'Competitor analysis template with SWOT, pricing, and feature comparisons.',
                'is_active' => true,
            ],
            [
                'title' => 'Market Research Template',
                'subtitle' => 'Conduct market research and analysis',
                'price' => 32.99,
                'old_price' => 42.99,
                'on_sale' => true,
                'category' => 'Competitive Analysis',
                'description' => 'Market research template with customer surveys, trends, and opportunity analysis.',
                'is_active' => true,
            ],

            // Product Launch Category
            [
                'title' => 'Product Launch Checklist',
                'subtitle' => 'Plan and execute product launches',
                'price' => 39.99,
                'old_price' => null,
                'on_sale' => false,
                'category' => 'Product Launch',
                'description' => 'Product launch template with timeline, tasks, and marketing activities.',
                'is_active' => true,
            ],
            [
                'title' => 'Go-to-Market Strategy',
                'subtitle' => 'Develop go-to-market plans',
                'price' => 44.99,
                'old_price' => 59.99,
                'on_sale' => true,
                'category' => 'Product Launch',
                'description' => 'Go-to-market strategy template with target audience, pricing, and distribution.',
                'is_active' => true,
            ],
        ];

        // Create products
        foreach ($products as $productData) {
            // Verify category exists
            if (!in_array($productData['category'], $categories)) {
                $this->command->warn("Category '{$productData['category']}' not found. Skipping product: {$productData['title']}");
                continue;
            }

            // Generate slug from title
            $slug = Product::createSlug($productData['title']);

            // Check if product with this slug already exists
            $existingProduct = Product::where('slug', $slug)->first();
            if ($existingProduct) {
                // Append number to make it unique
                $counter = 1;
                $originalSlug = $slug;
                while (Product::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                }
            }

            // Create the product
            Product::create([
                'title' => $productData['title'],
                'subtitle' => $productData['subtitle'],
                'price' => $productData['price'],
                'old_price' => $productData['old_price'],
                'on_sale' => $productData['on_sale'],
                'category' => $productData['category'],
                'slug' => $slug,
                'description' => $productData['description'],
                'is_active' => $productData['is_active'],
                'file_path' => null,
                'file_name' => null,
                'file_size' => null,
            ]);
        }

        $this->command->info('Products seeded successfully!');
    }
}

