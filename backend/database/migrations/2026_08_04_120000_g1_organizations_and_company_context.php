<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * G1 — organizations + company_user + last_company_id + backfill.
 * Idempotent backfill: iki kez çalışsa çoğaltmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->jsonb('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('companies', 'organization_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->foreignId('organization_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('organizations')
                    ->nullOnDelete();
                $table->index('organization_id');
            });
        }

        if (! Schema::hasTable('company_user')) {
            Schema::create('company_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->boolean('is_default')->default(false);
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['user_id', 'company_id']);
                $table->index('user_id');
                $table->index('company_id');
            });
        }

        if (! Schema::hasColumn('users', 'last_company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('last_company_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('companies')
                    ->nullOnDelete();
            });
        }

        $this->backfill();
    }

    /**
     * Mevcut firmalar → 1:1 organization; kullanıcılar → membership + last_company_id.
     */
    private function backfill(): void
    {
        $companies = DB::table('companies')->select('id', 'name', 'slug', 'organization_id')->get();

        foreach ($companies as $company) {
            if ($company->organization_id !== null) {
                continue;
            }

            $baseSlug = 'org-'.($company->slug ?: ('company-'.$company->id));
            $slug = $baseSlug;
            $n = 1;
            while (DB::table('organizations')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$n++;
            }

            $orgId = DB::table('organizations')->insertGetId([
                'name' => $company->name,
                'slug' => $slug,
                'settings' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('companies')->where('id', $company->id)->update([
                'organization_id' => $orgId,
            ]);
        }

        $users = DB::table('users')
            ->whereNotNull('company_id')
            ->select('id', 'company_id', 'last_company_id')
            ->get();

        foreach ($users as $user) {
            $exists = DB::table('company_user')
                ->where('user_id', $user->id)
                ->where('company_id', $user->company_id)
                ->exists();

            if (! $exists) {
                DB::table('company_user')->insert([
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'role_id' => null,
                    'is_default' => true,
                    'created_at' => now(),
                ]);
            } else {
                // En az bir is_default garantisi
                $hasDefault = DB::table('company_user')
                    ->where('user_id', $user->id)
                    ->where('is_default', true)
                    ->exists();
                if (! $hasDefault) {
                    DB::table('company_user')
                        ->where('user_id', $user->id)
                        ->where('company_id', $user->company_id)
                        ->update(['is_default' => true]);
                }
            }

            if ($user->last_company_id === null) {
                DB::table('users')->where('id', $user->id)->update([
                    'last_company_id' => $user->company_id,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'last_company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('last_company_id');
            });
        }

        Schema::dropIfExists('company_user');

        if (Schema::hasColumn('companies', 'organization_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }

        Schema::dropIfExists('organizations');
    }
};
