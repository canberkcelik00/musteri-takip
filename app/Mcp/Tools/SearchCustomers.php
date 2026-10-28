<?php

namespace App\Mcp\Tools;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The global search for Claude: the same Customer::search() scope as the search box, archived customers only when
 * asked for.
 */
#[Description('Find customers by name, legal title, a contact\'s name, e-mail or phone (any spelling), or a domain. Every word must match. Archived customers are left out unless include_archived is true. Use the returned id with customer-card.')]
#[IsReadOnly]
#[IsIdempotent]
class SearchCustomers extends BaseTool
{
    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Words to search for, such as "acme" or "0532 123 45 67".')->required(),
            'include_archived' => $schema->boolean()->description('Also list archived customers.'),
        ];
    }

    protected function run(Request $request, User $user): Response|ResponseFactory
    {
        $input = $request->validate([
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'include_archived' => ['boolean'],
        ]);

        $query = Customer::query()
            ->search($input['query'])
            ->when(! ($input['include_archived'] ?? false), fn ($customers) => $customers->notArchived());

        $customers = (clone $query)
            ->with(['primaryContact', 'customerServices.items'])
            ->orderBy('short_name')
            ->orderBy('id')
            ->limit(self::MAX_ROWS)
            ->get();

        return Response::structured([
            'total' => $query->count(),
            'customers' => $customers->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'short_name' => $customer->short_name,
                'legal_title' => $customer->legal_title,
                'archived' => $customer->isArchived(),
                'domains' => $customer->domains(),
                'primary_contact' => $customer->primaryContact instanceof Contact ? [
                    'name' => $customer->primaryContact->name,
                    'phone' => $customer->primaryContact->phone,
                    'email' => $customer->primaryContact->email,
                ] : null,
            ])->all(),
        ]);
    }
}
