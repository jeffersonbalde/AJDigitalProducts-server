<?php

namespace Database\Seeders;

use App\Models\ProductFaq;
use Illuminate\Database\Seeder;

class ProductFaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ProductFaq::query()->delete();

        $faqs = [
            [
                'question' => 'Do you have a Demo File?',
                'answer' => 'Yes! We provide demo files for most of our templates. You can preview the demo file before purchasing to ensure it meets your needs. Demo files are read-only versions that showcase the template\'s features and functionality.',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'question' => 'Does it work with Excel, Google Sheets, or both?',
                'answer' => 'Your purchase **ONLY** works with Google Sheets. The template is **NOT** compatible with MS Excel.',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'question' => 'How to Access?',
                'answer' => "- Once your purchase has been confirmed, you will receive an email with the link to access the files.\n- Download the PDF File.\n- The PDF will contain a link to the Google Sheet.\n- Open the link and begin accessing your files!\n\n**NOTE:** Make sure you have a GMAIL ACCOUNT. If you are using a phone/tablet, please also make sure to install the Google Sheets app first.",
                'display_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($faqs as $faq) {
            ProductFaq::create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'display_order' => $faq['display_order'],
                'is_active' => $faq['is_active'],
            ]);
        }
    }
}
