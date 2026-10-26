<?php

namespace App\Support\Ui;

use App\Actions\CatalogServices\DeleteCatalogService;
use App\Actions\Contacts\DeleteContact;
use App\Actions\Customers\ArchiveCustomer;
use App\Actions\Customers\DeleteCustomer;
use App\Actions\CustomerServices\ConfirmFreeContinuation;
use App\Actions\CustomerServices\DeleteCustomerService;
use App\Actions\Packages\DeletePackage;
use App\Actions\PaymentMethods\ArchivePaymentMethod;
use App\Actions\PaymentMethods\DeletePaymentMethod;
use App\Actions\Payments\DeletePayment;
use App\Actions\Quotes\DeleteQuote;
use App\Actions\Quotes\RejectQuote;
use App\Actions\Servers\DeleteServer;
use App\Enums\Permission;
use App\Models\CatalogService;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerService;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Quote;
use App\Models\Server;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The questions the generic confirm window asks (docs/UI.md §2 Kural 6: dangerous work is separate, red and confirmed).
 * Each entry names what the window says and runs one Action; a refusal from the Action comes out as an exception the
 * window shows at its top. The names are the only way a browser can pick one, so nothing else can be run through it.
 */
final class Confirmations
{
    /**
     * @return array{heading: string, description: string, label: string, danger: bool}
     */
    public static function describe(string $action, int $id = 0): array
    {
        return match ($action) {
            'archive-customer' => ['heading' => __('archive.archive_heading'), 'description' => __('archive.archive_description'), 'label' => __('archive.archive'), 'danger' => false],
            'unarchive-customer' => ['heading' => __('archive.unarchive'), 'description' => '', 'label' => __('archive.unarchive'), 'danger' => false],
            'delete-customer' => ['heading' => __('customers.delete_heading'), 'description' => __('archive.delete_description'), 'label' => __('customers.delete'), 'danger' => true],
            'delete-payment' => ['heading' => __('payments.delete_heading'), 'description' => __('payments.delete_description'), 'label' => __('payments.delete'), 'danger' => true],
            'delete-contact' => ['heading' => __('contacts.delete_heading'), 'description' => '', 'label' => __('contacts.delete'), 'danger' => true],
            'confirm-free-continuation' => ['heading' => __('customer_services.confirm_free_continuation'), 'description' => '', 'label' => __('customer_services.confirm_free_continuation'), 'danger' => false],
            'reject-quote' => ['heading' => __('quotes.reject_heading'), 'description' => __('quotes.reject_description', ['number' => Quote::query()->find($id)?->number]), 'label' => __('quotes.reject'), 'danger' => false],
            'delete-quote' => ['heading' => __('quotes.delete_heading'), 'description' => __('quotes.delete_description'), 'label' => __('quotes.delete'), 'danger' => true],
            'delete-service' => ['heading' => __('customer_services.delete_heading'), 'description' => __('archive.delete_description'), 'label' => __('customer_services.delete'), 'danger' => true],
            'archive-catalog-service', 'archive-package', 'archive-payment-method', 'archive-server' => ['heading' => __('archive.archive_heading'), 'description' => __('archive.archive_description'), 'label' => __('archive.archive'), 'danger' => false],
            'unarchive-catalog-service', 'unarchive-package', 'unarchive-payment-method', 'unarchive-server' => ['heading' => __('archive.unarchive'), 'description' => '', 'label' => __('archive.unarchive'), 'danger' => false],
            'delete-catalog-service', 'delete-package', 'delete-payment-method', 'delete-server' => ['heading' => __('archive.delete_heading'), 'description' => __('archive.delete_description'), 'label' => __('archive.delete'), 'danger' => true],
            default => throw new InvalidArgumentException("Unknown confirmation [{$action}]."),
        };
    }

    /**
     * The permission a user needs to be asked, and to confirm, the action.
     */
    public static function permission(string $action): Permission
    {
        return match ($action) {
            'archive-customer', 'unarchive-customer', 'delete-customer', 'delete-contact' => Permission::CustomersManage,
            'delete-payment' => Permission::MoneyManage,
            'delete-service', 'confirm-free-continuation' => Permission::ServicesManage,
            'reject-quote', 'delete-quote' => Permission::QuotesManage,
            'archive-catalog-service', 'unarchive-catalog-service', 'delete-catalog-service',
            'archive-package', 'unarchive-package', 'delete-package' => Permission::CatalogManage,
            'archive-payment-method', 'unarchive-payment-method', 'delete-payment-method',
            'archive-server', 'unarchive-server', 'delete-server' => Permission::SettingsManage,
            default => throw new InvalidArgumentException("Unknown confirmation [{$action}]."),
        };
    }

    /**
     * Run the confirmed action.
     *
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    public static function run(string $action, int $id): array
    {
        return match ($action) {
            'archive-customer' => self::archiveCustomer($id),
            'unarchive-customer' => self::unarchiveCustomer($id),
            'delete-customer' => self::deleteCustomer($id),
            'delete-payment' => self::deletePayment($id),
            'delete-contact' => self::deleteContact($id),
            'delete-service' => self::deleteService($id),
            'reject-quote' => self::rejectQuote($id),
            'delete-quote' => self::deleteQuote($id),
            'confirm-free-continuation' => self::confirmFreeContinuation($id),
            'archive-catalog-service' => self::archive(CatalogService::query()->findOrFail($id), 'catalog-changed'),
            'unarchive-catalog-service' => self::unarchive(CatalogService::query()->findOrFail($id), 'catalog-changed'),
            'delete-catalog-service' => self::delete(DeleteCatalogService::class, CatalogService::query()->findOrFail($id), 'catalog-changed'),
            'archive-package' => self::archive(Package::query()->findOrFail($id), 'catalog-changed'),
            'unarchive-package' => self::unarchive(Package::query()->findOrFail($id), 'catalog-changed'),
            'delete-package' => self::delete(DeletePackage::class, Package::query()->findOrFail($id), 'catalog-changed'),
            'archive-payment-method' => self::archivePaymentMethod($id),
            'unarchive-payment-method' => self::unarchive(PaymentMethod::query()->findOrFail($id), 'payment-methods-changed'),
            'delete-payment-method' => self::delete(DeletePaymentMethod::class, PaymentMethod::query()->findOrFail($id), 'payment-methods-changed'),
            'archive-server' => self::archive(Server::query()->findOrFail($id), 'servers-changed'),
            'unarchive-server' => self::unarchive(Server::query()->findOrFail($id), 'servers-changed'),
            'delete-server' => self::delete(DeleteServer::class, Server::query()->findOrFail($id), 'servers-changed'),
            default => throw new InvalidArgumentException("Unknown confirmation [{$action}]."),
        };
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function archiveCustomer(int $id): array
    {
        app(ArchiveCustomer::class)->handle(Customer::query()->findOrFail($id));

        return ['message' => __('archive.archived'), 'event' => 'customers-changed', 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function unarchiveCustomer(int $id): array
    {
        Customer::query()->findOrFail($id)->unarchive();

        return ['message' => __('archive.unarchived'), 'event' => 'customers-changed', 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function deleteCustomer(int $id): array
    {
        app(DeleteCustomer::class)->handle(Customer::query()->findOrFail($id));

        return [
            'message' => __('archive.deleted'),
            'event' => 'customers-changed',
            'redirect' => route('app.customers.index'),
        ];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function deletePayment(int $id): array
    {
        $reopened = app(DeletePayment::class)->handle(Payment::query()->findOrFail($id));

        return [
            'message' => $reopened > 0 ? __('payments.deleted_reopened', ['count' => $reopened]) : __('payments.deleted'),
            'event' => 'payments-changed',
            'redirect' => null,
        ];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function deleteContact(int $id): array
    {
        app(DeleteContact::class)->handle(Contact::query()->findOrFail($id));

        return ['message' => __('contacts.deleted'), 'event' => 'contacts-changed', 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function rejectQuote(int $id): array
    {
        app(RejectQuote::class)->handle(Quote::query()->findOrFail($id));

        return ['message' => __('quotes.rejected'), 'event' => 'quotes-changed', 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function deleteQuote(int $id): array
    {
        app(DeleteQuote::class)->handle(Quote::query()->findOrFail($id));

        return ['message' => __('quotes.deleted'), 'event' => 'quotes-changed', 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function confirmFreeContinuation(int $id): array
    {
        if (! app(ConfirmFreeContinuation::class)->handle(CustomerService::query()->findOrFail($id))) {
            return ['message' => __('customer_services.free_action_not_applied'), 'event' => 'customer-services-changed', 'redirect' => null, 'tone' => 'danger'];
        }

        return ['message' => __('customer_services.free_continuation_confirmed'), 'event' => 'customer-services-changed', 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function deleteService(int $id): array
    {
        app(DeleteCustomerService::class)->handle(CustomerService::query()->findOrFail($id));

        return ['message' => __('archive.deleted'), 'event' => 'customer-services-changed', 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function archive(Model $record, string $event): array
    {
        assert(method_exists($record, 'archive'));

        $record->archive();

        return ['message' => __('archive.archived'), 'event' => $event, 'redirect' => null];
    }

    /**
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function unarchive(Model $record, string $event): array
    {
        assert(method_exists($record, 'unarchive'));

        $record->unarchive();

        return ['message' => __('archive.unarchived'), 'event' => $event, 'redirect' => null];
    }

    /**
     * @param  class-string  $action
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function delete(string $action, Model $record, string $event): array
    {
        app($action)->handle($record);

        return ['message' => __('archive.deleted'), 'event' => $event, 'redirect' => null];
    }

    /**
     * The last active method is never archived.
     *
     * @return array{message: string, event: string, redirect: ?string, tone?: string}
     */
    private static function archivePaymentMethod(int $id): array
    {
        app(ArchivePaymentMethod::class)->handle(PaymentMethod::query()->findOrFail($id));

        return ['message' => __('archive.archived'), 'event' => 'payment-methods-changed', 'redirect' => null];
    }
}
