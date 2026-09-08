<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Business',
                'description' => 'Business management tools and templates for entrepreneurs and companies.',
            ],
            [
                'name' => 'Finance',
                'description' => 'Financial planning, budgeting, and accounting templates and tools.',
            ],
            [
                'name' => 'Productivity',
                'description' => 'Tools and templates to boost productivity and efficiency.',
            ],
            [
                'name' => 'Personal',
                'description' => 'Personal organization and lifestyle management templates.',
            ],
            [
                'name' => 'Bundle',
                'description' => 'Special bundle packages combining multiple products at discounted rates.',
            ],
            [
                'name' => 'Spreadsheet Templates',
                'description' => 'Ready-to-use Excel and Google Sheets templates for various purposes.',
            ],
            [
                'name' => 'Project Management',
                'description' => 'Templates and tools for managing projects, tasks, and team collaboration.',
            ],
            [
                'name' => 'Inventory Management',
                'description' => 'Tools for tracking inventory, stock levels, and warehouse management.',
            ],
            [
                'name' => 'Sales & Marketing',
                'description' => 'Templates for sales tracking, marketing campaigns, and customer management.',
            ],
            [
                'name' => 'HR & Payroll',
                'description' => 'Human resources management, employee tracking, and payroll templates.',
            ],
            [
                'name' => 'Accounting',
                'description' => 'Accounting templates including ledgers, balance sheets, and financial reports.',
            ],
            [
                'name' => 'Invoice & Billing',
                'description' => 'Professional invoice templates and billing management systems.',
            ],
            [
                'name' => 'Expense Tracking',
                'description' => 'Tools for tracking expenses, receipts, and financial transactions.',
            ],
            [
                'name' => 'Budget Planning',
                'description' => 'Monthly and annual budget planning templates and calculators.',
            ],
            [
                'name' => 'Customer Relationship',
                'description' => 'CRM templates and customer database management tools.',
            ],
            [
                'name' => 'Event Planning',
                'description' => 'Templates for organizing events, weddings, and corporate gatherings.',
            ],
            [
                'name' => 'Content Creation',
                'description' => 'Templates for content planning, social media, and editorial calendars.',
            ],
            [
                'name' => 'Education',
                'description' => 'Educational templates for teachers, students, and academic planning.',
            ],
            [
                'name' => 'Real Estate',
                'description' => 'Property management, rental tracking, and real estate investment tools.',
            ],
            [
                'name' => 'Fitness & Health',
                'description' => 'Workout plans, meal planning, and health tracking templates.',
            ],
            [
                'name' => 'Travel Planning',
                'description' => 'Travel itineraries, expense tracking, and trip planning templates.',
            ],
            [
                'name' => 'Home Management',
                'description' => 'Home maintenance schedules, meal planning, and household organization.',
            ],
            [
                'name' => 'Time Tracking',
                'description' => 'Time management and employee time tracking templates.',
            ],
            [
                'name' => 'Goal Setting',
                'description' => 'Personal and professional goal setting and achievement tracking templates.',
            ],
            [
                'name' => 'Data Analysis',
                'description' => 'Data analysis templates and reporting tools for business intelligence.',
            ],
            [
                'name' => 'Quality Control',
                'description' => 'Quality assurance checklists and inspection templates.',
            ],
            [
                'name' => 'Supply Chain',
                'description' => 'Supply chain management and logistics tracking templates.',
            ],
            [
                'name' => 'Legal Documents',
                'description' => 'Legal document templates and contract management tools.',
            ],
            [
                'name' => 'Training & Development',
                'description' => 'Employee training schedules and skill development tracking templates.',
            ],
            [
                'name' => 'Performance Review',
                'description' => 'Employee performance evaluation and review templates.',
            ],
            [
                'name' => 'Asset Management',
                'description' => 'Asset tracking and depreciation calculation templates.',
            ],
            [
                'name' => 'Risk Management',
                'description' => 'Risk assessment and mitigation planning templates.',
            ],
            [
                'name' => 'Compliance',
                'description' => 'Compliance tracking and regulatory documentation templates.',
            ],
            [
                'name' => 'Vendor Management',
                'description' => 'Vendor database and supplier relationship management templates.',
            ],
            [
                'name' => 'Meeting Notes',
                'description' => 'Professional meeting notes and action item tracking templates.',
            ],
            [
                'name' => 'Workflow Automation',
                'description' => 'Workflow templates and process automation tools.',
            ],
            [
                'name' => 'Reporting Tools',
                'description' => 'Business reporting templates and dashboard tools.',
            ],
            [
                'name' => 'Strategic Planning',
                'description' => 'Strategic planning and business development templates.',
            ],
            [
                'name' => 'Competitive Analysis',
                'description' => 'Market research and competitive analysis templates.',
            ],
            [
                'name' => 'Product Launch',
                'description' => 'Product launch planning and go-to-market strategy templates.',
            ],
        ];

        foreach ($categories as $category) {
            ProductCategory::create($category);
        }
    }
}

