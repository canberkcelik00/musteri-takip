<?php

namespace App\Enums;

enum Permission: string
{
    case MoneyView = 'money.view';
    case MoneyManage = 'money.manage';
    case CustomersManage = 'customers.manage';
    case ServicesView = 'services.view';
    case ServicesManage = 'services.manage';
    case QuotesManage = 'quotes.manage';
    case CatalogManage = 'catalog.manage';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';

    public function getLabel(): string
    {
        return __('enums.permission.'.str_replace('.', '_', $this->value));
    }

    public function getDescription(): string
    {
        return __('enums.permission_hint.'.str_replace('.', '_', $this->value));
    }

    /**
     * The group the roles screen lists the permission under.
     */
    public function group(): string
    {
        return match ($this) {
            self::MoneyView, self::MoneyManage => 'money',
            self::CustomersManage, self::ServicesView, self::ServicesManage => 'customers',
            self::QuotesManage, self::CatalogManage => 'sales',
            self::SettingsManage, self::UsersManage => 'settings',
        };
    }

    /**
     * The permission this one cannot work without: managing something implies seeing it, so a role never
     * "manages money but cannot see it".
     */
    public function requires(): ?self
    {
        return match ($this) {
            self::MoneyManage => self::MoneyView,
            self::ServicesManage => self::ServicesView,
            default => null,
        };
    }
}
