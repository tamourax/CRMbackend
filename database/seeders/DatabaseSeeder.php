<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo seeding is only allowed in local or testing environments.');
        }
        DB::transaction(function (): void {
            $legacyUsers = User::whereIn('email', ['alex@orbit.example', 'sarah@orbit.example'])->get();
            foreach ($legacyUsers as $legacyUser) {
                $customerIds = Customer::where('user_id', $legacyUser->id)->pluck('id');
                Deal::whereIn('customer_id', $customerIds)->delete();
                Customer::where('user_id', $legacyUser->id)->delete();
                $legacyUser->delete();
            }
        });

        $companies = [
            ['NileBridge Technologies', 'Technology', 'Cairo', 'Ahmed Hassan', 'ahmed.hassan@nilebridge.example', 'نايل بريدج للتكنولوجيا', 'التقنية', 'القاهرة', 'أحمد حسن'],
            ['DeltaMed Clinics', 'Healthcare', 'Giza', 'Nour El-Sayed', 'nour.elsayed@deltamed.example', 'عيادات دلتا ميد', 'الرعاية الصحية', 'الجيزة', 'نور السيد'],
            ['Cairo Solar Systems', 'Energy', 'Cairo', 'Omar Mostafa', 'omar.mostafa@cairosolar.example', 'أنظمة القاهرة للطاقة الشمسية', 'الطاقة', 'القاهرة', 'عمر مصطفى'],
            ['Alexandria Logistics', 'Logistics', 'Alexandria', 'Mariam Fathy', 'mariam.fathy@alexlogistics.example', 'الإسكندرية للخدمات اللوجستية', 'الخدمات اللوجستية', 'الإسكندرية', 'مريم فتحي'],
            ['Pyramids Finance', 'Financial Services', 'Giza', 'Youssef Ibrahim', 'youssef.ibrahim@pyramidsfinance.example', 'أهرامات للتمويل', 'الخدمات المالية', 'الجيزة', 'يوسف إبراهيم'],
            ['Lotus Retail Group', 'Retail', 'Cairo', 'Salma Nabil', 'salma.nabil@lotusretail.example', 'مجموعة لوتس للتجزئة', 'التجزئة', 'القاهرة', 'سلمى نبيل'],
            ['Giza Learning', 'Education', 'Giza', 'Karim Adel', 'karim.adel@gizalearning.example', 'جيزة للتعليم', 'التعليم', 'الجيزة', 'كريم عادل'],
            ['Red Sea Hospitality', 'Hospitality', 'Hurghada', 'Farida Mahmoud', 'farida.mahmoud@redseahospitality.example', 'البحر الأحمر للضيافة', 'الضيافة', 'الغردقة', 'فريدة محمود'],
            ['Sphinx Manufacturing', 'Manufacturing', '6th of October City', 'Amr Khaled', 'amr.khaled@sphinxmanufacturing.example', 'سفنكس للصناعات', 'التصنيع', 'مدينة السادس من أكتوبر', 'عمرو خالد'],
            ['Canal Foods', 'Food & Beverage', 'Ismailia', 'Layla Tarek', 'layla.tarek@canalfoods.example', 'قناة للأغذية', 'الأغذية والمشروبات', 'الإسماعيلية', 'ليلى طارق'],
        ];
        $titles = ['ERP implementation', 'Annual service renewal', 'Branch expansion', 'Customer onboarding', 'Business analytics pilot', 'Support partnership'];
        $arabicTitles = ['تنفيذ نظام تخطيط موارد المؤسسات', 'تجديد الخدمة السنوي', 'توسعة الفروع', 'تهيئة العميل', 'تجربة تحليلات الأعمال', 'شراكة الدعم الفني'];
        $notes = [
            'The Cairo operations team requested a phased rollout across its branches. Procurement review is pending.',
            'The clinic network is interested in a wider deployment. Final budget approval is still pending.',
            'The customer requested an implementation schedule aligned with the next quarter and local site readiness.',
            'The decision maker asked for an EGP-denominated proposal and a clear delivery timeline.',
            null,
        ];
        $arabicNotes = [
            'طلب فريق العمليات تنفيذ المشروع على مراحل في فروع القاهرة. ما زالت مراجعة المشتريات قيد الانتظار.',
            'تهتم شبكة العيادات بتوسيع نطاق الخدمة. ما زالت الموافقة النهائية على الميزانية معلقة.',
            'طلب العميل جدول تنفيذ يتوافق مع الربع القادم وجاهزية المواقع المحلية.',
            'طلب صاحب القرار عرض سعر بالجنيه المصري وجدولًا زمنيًا واضحًا للتسليم.',
            null,
        ];
        $lossReasons = ['Budget was reassigned to another local initiative.', 'The customer retained its existing supplier.', 'The delivery timeline did not match the planned branch opening.', null];
        $arabicLossReasons = ['أُعيد توجيه الميزانية إلى مبادرة محلية أخرى.', 'قرر العميل الاستمرار مع المورّد الحالي.', 'لم يتوافق موعد التنفيذ مع موعد افتتاح الفرع المخطط له.', null];
        foreach ([['Ahmed Tamer', 'ahmed@7bahmedtamer.example', 'أحمد تامر'], ['Nour Hassan', 'nour@7bahmedtamer.example', 'نور حسن']] as $userIndex => [$name, $email, $arabicName]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('OrbitDemo!2026'),
                'translations' => ['ar' => ['name' => $arabicName]],
            ]);
            foreach ($companies as $index => [$company, $industry, $city, $contact, $contactEmail, $companyAr, $industryAr, $cityAr, $contactAr]) {
                $companyName = $userIndex === 0 ? $company : $company.' Egypt';
                $companyNameAr = $userIndex === 0 ? $companyAr : $companyAr.' مصر';
                $customer = Customer::updateOrCreate(['user_id' => $user->id, 'company' => $companyName], [
                    'name' => $contact, 'email' => $contactEmail,
                    'phone' => '+20 10 0000 '.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    'industry' => $industry, 'city' => $city, 'status' => ['active', 'prospect', 'active', 'inactive'][$index % 4], 'notes' => $notes[$index % 5],
                    'translations' => ['ar' => [
                        'name' => $contactAr,
                        'company' => $companyNameAr,
                        'industry' => $industryAr,
                        'city' => $cityAr,
                        'notes' => $arabicNotes[$index % 5],
                    ]],
                ]);
                $dealCount = $index === 9 ? 0 : ($index === 0 ? 6 : 3);
                for ($dealIndex = 0; $dealIndex < $dealCount; $dealIndex++) {
                    $sequence = $index * 3 + $dealIndex;
                    $stage = Deal::STAGES[($sequence + $userIndex) % 6];
                    $date = match ($sequence % 5) {
                        0 => now()->startOfMonth()->addDays(4),
                        1 => now()->endOfMonth(),
                        2 => now()->addMonthNoOverflow()->startOfMonth()->addDays(8),
                        3 => now()->subMonthNoOverflow()->startOfMonth()->addDays(12),
                        default => now()->addDays(3),
                    };
                    $title = $titles[$sequence % 6].' · '.strtok($company, ' ');
                    $titleAr = $arabicTitles[$sequence % 6].' · '.$companyNameAr;
                    $description = 'A '.$industry.' project for '.$companyName.' in '.$city.'. Scope includes discovery, phased implementation, staff onboarding, and support. Commercial terms are quoted in Egyptian pounds (EGP).';
                    $descriptionAr = 'مشروع في قطاع '.$industryAr.' لصالح '.$companyNameAr.' في '.$cityAr.'. يشمل النطاق دراسة الاحتياجات والتنفيذ على مراحل وتدريب الموظفين والدعم الفني. جميع القيم بالجنيه المصري.';
                    Deal::updateOrCreate(['customer_id' => $customer->id, 'title' => $title], [
                        'value' => (string) (850000 + (($sequence * 1375000 + $userIndex * 500000) % 14200000)),
                        'stage' => $stage, 'expected_close_date' => $sequence % 13 === 0 ? null : $date->toDateString(),
                        'description' => $description,
                        'lost_reason' => $stage === 'lost' ? $lossReasons[$index % 4] : null,
                        'translations' => ['ar' => [
                            'title' => $titleAr,
                            'description' => $descriptionAr,
                            'lost_reason' => $stage === 'lost' ? $arabicLossReasons[$index % 4] : null,
                        ]],
                    ]);
                }
            }
        }
    }
}
