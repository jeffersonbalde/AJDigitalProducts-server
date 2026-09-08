<?php

namespace Database\Seeders;

use App\Models\LandingPageSection;
use Illuminate\Database\Seeder;

class LandingPageSectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sections = [
            // Hero Slider Section
            [
                'title' => 'Hero Slider',
                'section_type' => 'hero',
                'source_type' => null,
                'source_value' => null,
                'product_count' => 0,
                'display_style' => 'grid',
                'is_active' => true,
                'display_order' => 1,
                'description' => 'Main hero slider section for landing page',
                'status' => 'published',
                'published_at' => now(),
                'config' => [
                    'slides' => [
                        [
                            'image' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1920&h=1080&fit=crop',
                            'title' => 'Professional Spreadsheet Templates',
                            'subtitle' => 'Transform your workflow with expertly designed Excel and Google Sheets templates. From financial planning to project management, get instant access to premium digital tools.',
                            'buttonText' => 'Explore Templates',
                            'buttonLink' => '/all-products',
                            'buttonColor' => '#0066CC',
                            'textColor' => '#FFFFFF',
                        ],
                        [
                            'image' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1920&h=1080&fit=crop',
                            'title' => 'Business & Finance Solutions',
                            'subtitle' => 'Streamline your business operations with our comprehensive collection of budget trackers, expense managers, and financial planning tools. Download instantly, use immediately.',
                            'buttonText' => 'Shop Business Tools',
                            'buttonLink' => '/all-products',
                            'buttonColor' => '#000000',
                            'textColor' => '#FFFFFF',
                        ],
                        [
                            'image' => 'https://images.unsplash.com/photo-1551650975-87deedd944c3?w=1920&h=1080&fit=crop',
                            'title' => 'Productivity & Organization',
                            'subtitle' => 'Boost your efficiency with professionally crafted planners, trackers, and organizational templates. Perfect for entrepreneurs, freelancers, and teams.',
                            'buttonText' => 'View All Products',
                            'buttonLink' => '/all-products',
                            'buttonColor' => '#0066CC',
                            'textColor' => '#FFFFFF',
                        ],
                    ],
                    'autoplay' => true,
                    'autoplayDelay' => 5000,
                    'showNavigation' => true,
                    'showPagination' => true,
                    'backgroundColor' => '#FFFFFF',
                ],
            ],
            // Product Grid Sections
            [
                'title' => 'Our New Arrivals',
                'section_type' => 'product_grid',
                'source_type' => 'collection',
                'source_value' => 'new-arrivals',
                'product_count' => 4,
                'display_style' => 'grid',
                'is_active' => true,
                'display_order' => 2,
                'description' => 'Latest products recently added to the store',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'Our Best Sellers',
                'section_type' => 'product_grid',
                'source_type' => 'collection',
                'source_value' => 'best-sellers',
                'product_count' => 8,
                'display_style' => 'grid',
                'is_active' => true,
                'display_order' => 3,
                'description' => 'Top-selling products based on customer popularity',
                'status' => 'published',
                'published_at' => now(),
            ],
            [
                'title' => 'Featured Products',
                'section_type' => 'product_grid',
                'source_type' => 'collection',
                'source_value' => 'featured-products',
                'product_count' => 6,
                'display_style' => 'grid',
                'is_active' => false, // Disabled by default
                'display_order' => 4,
                'description' => 'Hand-picked featured products',
                'status' => 'draft',
            ],
            // FAQ Section
            [
                'title' => 'Frequently Asked Questions',
                'section_type' => 'faq',
                'source_type' => null,
                'source_value' => null,
                'product_count' => 0,
                'display_style' => 'grid',
                'is_active' => true,
                'display_order' => 7,
                'description' => 'Common questions and answers',
                'status' => 'published',
                'published_at' => now(),
                'config' => [
                    'title' => 'Frequently Asked Questions',
                    'faqs' => [
                        [
                            'id' => 'faq-1',
                            'question' => 'How do I access my digital products after purchase?',
                            'answer' => 'After checkout, you will receive an email with a secure download link. You can also access your files anytime from your account dashboard under Orders.',
                            'is_active' => true,
                            'order' => 1,
                        ],
                        [
                            'id' => 'faq-2',
                            'question' => 'What file formats are included in the templates?',
                            'answer' => 'Most products include Excel (.xlsx) and Google Sheets versions. If a product has additional formats (PDF, CSV, Notion, etc.), it will be listed on the product page.',
                            'is_active' => true,
                            'order' => 2,
                        ],
                        [
                            'id' => 'faq-3',
                            'question' => 'Can I use the templates for client or commercial projects?',
                            'answer' => 'Yes, commercial use is allowed for your own business or client work. Reselling or redistributing the original files is not permitted.',
                            'is_active' => true,
                            'order' => 3,
                        ],
                        [
                            'id' => 'faq-4',
                            'question' => 'Do you offer refunds for digital products?',
                            'answer' => 'Due to the instant-download nature of digital files, all sales are final. If you have trouble accessing your files, contact support and we will help you immediately.',
                            'is_active' => true,
                            'order' => 4,
                        ],
                        [
                            'id' => 'faq-5',
                            'question' => 'Can I customize the templates to match my brand?',
                            'answer' => 'Absolutely. All templates are fully editable so you can update colors, fonts, and layouts to match your brand identity.',
                            'is_active' => true,
                            'order' => 5,
                        ],
                        [
                            'id' => 'faq-6',
                            'question' => 'Do I receive future updates after purchase?',
                            'answer' => 'Yes. When we release updates or improvements, you will receive the latest version at no additional cost.',
                            'is_active' => true,
                            'order' => 6,
                        ],
                    ],
                    'layout' => 'accordion',
                    'allowMultipleOpen' => false,
                    'backgroundColor' => '#F3F3F3',
                ],
            ],
            // Testimonials Section
            [
                'title' => 'What Our Users Are Saying',
                'section_type' => 'testimonials',
                'source_type' => null,
                'source_value' => null,
                'product_count' => 0,
                'display_style' => 'slider',
                'is_active' => true,
                'display_order' => 8,
                'description' => 'Customer testimonials and reviews',
                'status' => 'published',
                'published_at' => now(),
                'config' => [
                    'title' => 'What Our Users Are Saying',
                    'testimonials' => [
                        [
                            'id' => 'testimonial-1',
                            'rating' => 5,
                            'content' => 'This template has completely transformed how I manage my business operations. The inventory tracking is intuitive and the sales reports are incredibly detailed.',
                            'name' => 'Evita Veron Carig',
                            'role' => 'Inventory Management & Sales Tracker',
                            'order' => 1,
                            'is_active' => true,
                            'image' => 'https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?w=200&h=200&fit=crop&crop=faces',
                        ],
                        [
                            'id' => 'testimonial-2',
                            'rating' => 5,
                            'content' => 'I love how user-friendly this template is. It took me just minutes to set up and start tracking my inventory. The automated calculations save me hours every week.',
                            'name' => 'Ryan P.',
                            'role' => 'Inventory Management & Sales Tracker',
                            'order' => 2,
                            'is_active' => true,
                        ],
                        [
                            'id' => 'testimonial-3',
                            'rating' => 5,
                            'content' => 'As a small business owner, this template has been a game-changer. I can now track everything in one place and make data-driven decisions with confidence.',
                            'name' => 'Mikaela Padilla',
                            'role' => 'Inventory Management & Sales Tracker',
                            'order' => 3,
                            'is_active' => true,
                            'image' => 'https://images.unsplash.com/photo-1544723795-3fb6469f5b39?w=200&h=200&fit=crop&crop=faces',
                        ],
                        [
                            'id' => 'testimonial-4',
                            'rating' => 5,
                            'content' => 'The best investment I\'ve made for my business. The template is well-organized, easy to customize, and the video tutorials made setup a breeze.',
                            'name' => 'Sarah Johnson',
                            'role' => 'Inventory Management & Sales Tracker',
                            'order' => 4,
                            'is_active' => true,
                        ],
                        [
                            'id' => 'testimonial-5',
                            'rating' => 5,
                            'content' => 'This template exceeded my expectations. The level of detail and functionality is impressive. It has streamlined my entire workflow.',
                            'name' => 'Michael Chen',
                            'role' => 'Inventory Management & Sales Tracker',
                            'order' => 5,
                            'is_active' => true,
                        ],
                    ],
                    'displayStyle' => 'slider',
                    'autoRotate' => true,
                    'backgroundColor' => '#FFFFFF',
                ],
            ],
            // Email Subscribe Section
            [
                'title' => 'Subscribe to our emails',
                'section_type' => 'email_subscribe',
                'source_type' => null,
                'source_value' => null,
                'product_count' => 0,
                'display_style' => 'grid',
                'is_active' => true,
                'display_order' => 9,
                'description' => 'Email subscription form',
                'status' => 'published',
                'published_at' => now(),
                'config' => [
                    'title' => 'Subscribe to our emails',
                    'description' => 'Get early access to new spreadsheet drops, tutorials, and exclusive promos.',
                    'placeholder' => 'Enter your email',
                    'buttonText' => '➜',
                    'backgroundColor' => '#F3F3F3',
                    'subscribeBandColor' => '#FDD238',
                ],
            ],
        ];

        foreach ($sections as $section) {
            LandingPageSection::updateOrCreate(
                ['title' => $section['title']],
                $section
            );
        }
    }
}
