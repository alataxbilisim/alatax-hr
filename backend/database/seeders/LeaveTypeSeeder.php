<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaveTypes = [
            [
                'name' => 'Yıllık İzin',
                'code' => 'YI',
                'description' => 'Çalışanların yıllık dinlenme hakkı',
                'is_paid' => true,
                'default_days' => 14,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => 14,
                'min_days_notice' => 3,
            ],
            [
                'name' => 'Mazeret İzni',
                'code' => 'MI',
                'description' => 'Acil durumlar için kısa süreli izin',
                'is_paid' => true,
                'default_days' => 5,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => 2,
                'min_days_notice' => 0,
            ],
            [
                'name' => 'Hastalık İzni',
                'code' => 'HI',
                'description' => 'Sağlık sorunları için izin',
                'is_paid' => true,
                'default_days' => 10,
                'requires_document' => true,
                'gender_restriction' => 'all',
                'max_days_at_once' => null,
                'min_days_notice' => 0,
            ],
            [
                'name' => 'Evlilik İzni',
                'code' => 'EI',
                'description' => 'Evlenme durumunda verilen izin',
                'is_paid' => true,
                'default_days' => 3,
                'requires_document' => true,
                'gender_restriction' => 'all',
                'max_days_at_once' => 3,
                'min_days_notice' => 7,
            ],
            [
                'name' => 'Doğum İzni',
                'code' => 'DI',
                'description' => 'Anne adayları için doğum izni',
                'is_paid' => true,
                'default_days' => 112,
                'requires_document' => true,
                'gender_restriction' => 'female',
                'max_days_at_once' => null,
                'min_days_notice' => 30,
            ],
            [
                'name' => 'Babalık İzni',
                'code' => 'BI',
                'description' => 'Baba adayları için doğum izni',
                'is_paid' => true,
                'default_days' => 5,
                'requires_document' => true,
                'gender_restriction' => 'male',
                'max_days_at_once' => 5,
                'min_days_notice' => 1,
            ],
            [
                'name' => 'Ölüm İzni',
                'code' => 'OI',
                'description' => 'Yakın kaybı durumunda izin',
                'is_paid' => true,
                'default_days' => 3,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => 3,
                'min_days_notice' => 0,
            ],
            [
                'name' => 'Ücretsiz İzin',
                'code' => 'UI',
                'description' => 'Maaş kesilmeli izin',
                'is_paid' => false,
                'default_days' => 30,
                'requires_document' => false,
                'gender_restriction' => 'all',
                'max_days_at_once' => null,
                'min_days_notice' => 7,
            ],
        ];

        // Her firma için varsayılan izin türlerini oluştur
        $companies = \App\Models\Company::all();
        
        foreach ($companies as $company) {
            foreach ($leaveTypes as $leaveType) {
                LeaveType::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $leaveType['code'],
                    ],
                    array_merge($leaveType, ['company_id' => $company->id, 'is_active' => true])
                );
            }
        }
    }
}

