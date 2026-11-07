<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Archive\DocumentResource;

class StaffDocumentResource extends DocumentResource
{
    public static bool $exports = true;

    #[\Override]
    public function actions(): array
    {
        return [
            ...(self::$exports ? [ExportDocuments::make()] : []),
            BatchedExportDocuments::make(),
            RoutedExportDocuments::make(),
            RetriedExportDocuments::make(),
            GuestExportDocuments::make(),
            StandaloneExportDocuments::make(),
        ];
    }

    #[\Override]
    public function perimeterQuery(RestRequest $request, Builder $query): Builder
    {
        $user = $request->user();

        if (! $user instanceof ArchiveUser || ! $user->isStaff()) {
            $query->where('title', '!=', 'Payroll');
        }

        return $query->where('archived', false);
    }
}
