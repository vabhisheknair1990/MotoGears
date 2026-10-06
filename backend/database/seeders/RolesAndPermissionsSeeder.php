<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public const PERMISSIONS = [
        'dashboard.view' => ['Dashboard', 'View dashboard'],
        'reports.view' => ['Reports', 'View reports'],
        'products.manage' => ['Catalog', 'Manage products & attributes'],
        'categories.manage' => ['Catalog', 'Manage categories'],
        'brands.manage' => ['Catalog', 'Manage brands'],
        'vehicles.manage' => ['Catalog', 'Manage vehicles'],
        'orders.view' => ['Orders', 'View orders'],
        'orders.manage' => ['Orders', 'Update orders'],
        'customers.view' => ['Customers', 'View customers'],
        'customers.manage' => ['Customers', 'Enable / disable customers'],
        'inventory.manage' => ['Inventory', 'Manage inventory'],
        'coupons.manage' => ['Marketing', 'Manage coupons'],
        'reviews.manage' => ['Marketing', 'Moderate reviews'],
        'cms.manage' => ['Content', 'Manage banners, pages, blog, FAQs'],
        'settings.manage' => ['System', 'Manage store settings & audit log'],
        'users.manage' => ['System', 'Manage staff & roles'],
    ];

    public const ROLES = [
        Role::SUPER_ADMIN => ['Super Admin', 'Full access to everything', true, ['*']],
        Role::ADMIN => ['Admin', 'Runs the store day to day', true, [
            'dashboard.view', 'reports.view', 'products.manage', 'categories.manage', 'brands.manage', 'vehicles.manage',
            'orders.view', 'orders.manage', 'customers.view', 'customers.manage', 'inventory.manage', 'coupons.manage',
            'reviews.manage', 'cms.manage', 'settings.manage',
        ]],
        Role::CATALOG_MANAGER => ['Catalog Manager', 'Products, categories, brands and vehicle fitment', true, [
            'dashboard.view', 'products.manage', 'categories.manage', 'brands.manage', 'vehicles.manage', 'reviews.manage',
        ]],
        Role::ORDER_MANAGER => ['Order Manager', 'Order fulfilment and customer service', true, [
            'dashboard.view', 'orders.view', 'orders.manage', 'customers.view', 'reports.view',
        ]],
        Role::INVENTORY_MANAGER => ['Inventory Manager', 'Stock levels and movements', true, [
            'dashboard.view', 'inventory.manage', 'orders.view', 'reports.view',
        ]],
        Role::CONTENT_MANAGER => ['Content Manager', 'Homepage, blog and marketing content', true, [
            'dashboard.view', 'cms.manage', 'coupons.manage', 'reviews.manage',
        ]],
        Role::CUSTOMER => ['Customer', 'Storefront customer', false, []],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => [$group, $label]) {
            Permission::updateOrCreate(['name' => $name], ['group' => $group, 'label' => $label]);
        }
        $all = Permission::pluck('id', 'name');

        foreach (self::ROLES as $name => [$label, $description, $staff, $perms]) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label, 'description' => $description, 'is_staff' => $staff]);
            $role->permissions()->sync($perms === ['*'] ? $all->values() : $all->only($perms)->values());
        }
    }
}
