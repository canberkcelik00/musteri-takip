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
}
