<?php

    namespace Database\Seeders;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\RequestStatus;
use App\Models\FundingCategory;
use App\Models\Role;
use App\Models\Permission;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed request statuses
        RequestStatus::firstOrCreate(['status_name' => 'pending'], ['description' => 'Funding request pending review']);
        RequestStatus::firstOrCreate(['status_name' => 'approved'], ['description' => 'Funding request approved']);
        RequestStatus::firstOrCreate(['status_name' => 'rejected'], ['description' => 'Funding request rejected']);
        RequestStatus::firstOrCreate(['status_name' => 'completed'], ['description' => 'Funding request completed']);

        // Seed funding categories
        FundingCategory::firstOrCreate(['category_name' => 'Education'], [
            'description' => 'Educational programs and scholarships',
            'is_active' => true,
            'weight_in_scoring' => 1.2,
            'approval_priority' => 1,
        ]);

        FundingCategory::firstOrCreate(['category_name' => 'Healthcare'], [
            'description' => 'Medical and healthcare services',
            'is_active' => true,
            'weight_in_scoring' => 1.3,
            'approval_priority' => 1,
        ]);

        FundingCategory::firstOrCreate(['category_name' => 'Food & Shelter'], [
            'description' => 'Food and housing assistance',
            'is_active' => true,
            'weight_in_scoring' => 1.1,
            'approval_priority' => 2,
        ]);

        FundingCategory::firstOrCreate(['category_name' => 'Emergency Relief'], [
            'description' => 'Emergency disaster relief',
            'is_active' => true,
            'weight_in_scoring' => 1.4,
            'approval_priority' => 0,
        ]);

        FundingCategory::firstOrCreate(['category_name' => 'Community Development'], [
            'description' => 'Community development projects',
            'is_active' => true,
            'weight_in_scoring' => 1.0,
            'approval_priority' => 3,
        ]);

        // Seed roles
        Role::firstOrCreate(['role_name' => 'admin'], ['description' => 'System administrator with full access']);
        Role::firstOrCreate(['role_name' => 'user'], ['description' => 'Regular user with basic permissions']);
        Role::firstOrCreate(['role_name' => 'moderator'], ['description' => 'Moderator for approval decisions']);
        Role::firstOrCreate(['role_name' => 'editor'], ['description' => 'Content editor']);
        Role::firstOrCreate(['role_name' => 'provider'], ['description' => 'Service provider']);

        // Seed permissions
        Permission::firstOrCreate(['permission_name' => 'view_dashboard'], ['description' => 'View dashboard']);
        Permission::firstOrCreate(['permission_name' => 'submit_funding_request'], ['description' => 'Submit funding requests']);
        Permission::firstOrCreate(['permission_name' => 'view_funding_requests'], ['description' => 'View funding requests']);
        Permission::firstOrCreate(['permission_name' => 'approve_funding'], ['description' => 'Approve funding requests']);
        Permission::firstOrCreate(['permission_name' => 'reject_funding'], ['description' => 'Reject funding requests']);
        Permission::firstOrCreate(['permission_name' => 'manage_users'], ['description' => 'Manage system users']);
        Permission::firstOrCreate(['permission_name' => 'view_reports'], ['description' => 'View system reports']);
        Permission::firstOrCreate(['permission_name' => 'manage_iot_boxes'], ['description' => 'Manage IoT boxes']);
        Permission::firstOrCreate(['permission_name' => 'manage_categories'], ['description' => 'Manage funding categories']);
        Permission::firstOrCreate(['permission_name' => 'view_audit_logs'], ['description' => 'View audit logs']);
    }
}
