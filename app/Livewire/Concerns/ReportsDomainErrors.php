<?php

namespace App\Livewire\Concerns;

use App\Exceptions\InvalidCharge;
use App\Exceptions\InvalidCustomerService;
use App\Exceptions\InvalidQuote;
use App\Support\Ui\FieldErrors;

/**
 * Shows a business-rule refusal from an Action under the form field it is about, or at the top of the window
 * (the `form` error) when the reason belongs to no field of this window (docs/UI.md §2 Kural 4).
 */
trait ReportsDomainErrors
{
    /**
     * @param  list<string>  $fields  names of the component properties that are fields of this window
     */
    protected function reportDomainError(InvalidCharge|InvalidCustomerService|InvalidQuote $exception, array $fields): void
    {
        $field = FieldErrors::fieldFor($exception->reason);

        $this->addError($field !== null && in_array($field, $fields, true) ? $field : 'form', $exception->getMessage());
    }
}
