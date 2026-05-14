<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Permission;
use App\Services\Authorization\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['slug' => 'request.create', 'name_ar' => 'إنشاء طلب تمويل', 'name_en' => 'Create financing request', 'group' => 'requests'],
            ['slug' => 'request.review', 'name_ar' => 'مراجعة الطلبات', 'name_en' => 'Review requests', 'group' => 'requests'],
            ['slug' => 'request.approve', 'name_ar' => 'اعتماد الطلبات', 'name_en' => 'Approve requests', 'group' => 'requests'],
            ['slug' => 'request.reject', 'name_ar' => 'رفض الطلبات', 'name_en' => 'Reject requests', 'group' => 'requests'],
            ['slug' => 'swift.upload', 'name_ar' => 'رفع وثيقة السويفت', 'name_en' => 'Upload SWIFT document', 'group' => 'swift'],
            ['slug' => 'voting.cast', 'name_ar' => 'التصويت على الطلبات', 'name_en' => 'Cast vote on requests', 'group' => 'voting'],
            ['slug' => 'voting.finalize', 'name_ar' => 'إغلاق التصويت ونشر القرار', 'name_en' => 'Finalize voting and publish decision', 'group' => 'voting'],
            ['slug' => 'customs.issue', 'name_ar' => 'إصدار البيان الجمركي', 'name_en' => 'Issue customs declaration', 'group' => 'customs'],
            ['slug' => 'reports.view', 'name_ar' => 'عرض التقارير', 'name_en' => 'View reports', 'group' => 'analytics'],
            ['slug' => 'audit.view', 'name_ar' => 'عرض سجل التدقيق', 'name_en' => 'View audit log', 'group' => 'analytics'],
            ['slug' => 'merchants.manage', 'name_ar' => 'إدارة التجار', 'name_en' => 'Manage merchants', 'group' => 'admin'],
            ['slug' => 'users.manage', 'name_ar' => 'إدارة المستخدمين', 'name_en' => 'Manage users', 'group' => 'admin'],
            ['slug' => 'entities.manage', 'name_ar' => 'إدارة البنوك والصرافات', 'name_en' => 'Manage banks', 'group' => 'admin'],
            ['slug' => 'docrules.manage', 'name_ar' => 'إدارة قواعد المستندات', 'name_en' => 'Manage document types', 'group' => 'admin'],
            ['slug' => 'roles.manage', 'name_ar' => 'إدارة الأدوار والصلاحيات', 'name_en' => 'Manage roles and permissions', 'group' => 'admin'],
            ['slug' => 'request.claim', 'name_ar' => 'حجز الطلب للمراجعة', 'name_en' => 'Claim request for review', 'group' => 'requests'],
        ];

        foreach ($permissions as $permission) {
            Permission::query()->updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        $map = [
            'request.create' => [UserRole::BANK_MANAGER, UserRole::DATA_ENTRY],
            'request.review' => [UserRole::BANK_MANAGER, UserRole::BANK_REVIEWER],
            'request.approve' => [UserRole::SUPPORT_COMMITTEE],
            'request.reject' => [UserRole::SUPPORT_COMMITTEE],
            'request.claim' => [UserRole::SUPPORT_COMMITTEE],
            'swift.upload' => [UserRole::BANK_MANAGER, UserRole::SWIFT_OFFICER],
            'voting.cast' => [UserRole::EXECUTIVE_MEMBER, UserRole::EXECUTIVE_DIRECTOR],
            'voting.finalize' => [UserRole::EXECUTIVE_DIRECTOR],
            'customs.issue' => [UserRole::EXECUTIVE_DIRECTOR],
            'reports.view' => [UserRole::CBY_ADMIN, UserRole::BANK_MANAGER, UserRole::EXECUTIVE_MEMBER, UserRole::EXECUTIVE_DIRECTOR],
            'audit.view' => [UserRole::CBY_ADMIN, UserRole::BANK_MANAGER, UserRole::SUPPORT_COMMITTEE, UserRole::EXECUTIVE_MEMBER, UserRole::EXECUTIVE_DIRECTOR],
            'merchants.manage' => [UserRole::CBY_ADMIN, UserRole::BANK_MANAGER, UserRole::DATA_ENTRY],
            'users.manage' => [UserRole::CBY_ADMIN, UserRole::BANK_MANAGER],
            'entities.manage' => [UserRole::CBY_ADMIN],
            'docrules.manage' => [UserRole::CBY_ADMIN],
            'roles.manage' => [UserRole::CBY_ADMIN, UserRole::BANK_MANAGER],
        ];

        DB::table('role_permissions')->delete();
        foreach ($map as $slug => $roles) {
            $permissionId = Permission::query()->where('slug', $slug)->value('id');
            foreach ($roles as $role) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role' => $role->value,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        app(PermissionService::class)->clearRoleCache(UserRole::CBY_ADMIN);
        app(PermissionService::class)->clearRoleCache(UserRole::BANK_MANAGER);
        app(PermissionService::class)->clearRoleCache(UserRole::DATA_ENTRY);
        app(PermissionService::class)->clearRoleCache(UserRole::BANK_REVIEWER);
        app(PermissionService::class)->clearRoleCache(UserRole::SWIFT_OFFICER);
        app(PermissionService::class)->clearRoleCache(UserRole::SUPPORT_COMMITTEE);
        app(PermissionService::class)->clearRoleCache(UserRole::EXECUTIVE_MEMBER);
        app(PermissionService::class)->clearRoleCache(UserRole::EXECUTIVE_DIRECTOR);
    }
}
