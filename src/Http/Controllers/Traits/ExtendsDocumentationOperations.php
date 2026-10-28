<?php

namespace Lomkit\Rest\Http\Controllers\Traits;

use Lomkit\Rest\Documentation\Schemas\Operation;

trait ExtendsDocumentationOperations
{
    /**
     * Extend "detail" documentation operation.
     */
    public function generateDocumentationDetailOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "search" documentation operation.
     */
    public function generateDocumentationSearchOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "mutate" documentation operation.
     */
    public function generateDocumentationMutateOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "actions" documentation operation.
     */
    public function generateDocumentationActionsOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "destroy" documentation operation.
     */
    public function generateDocumentationDestroyOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "restore" documentation operation.
     */
    public function generateDocumentationRestoreOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "forceDelete" documentation operation.
     */
    public function generateDocumentationForceDeleteOperation(Operation $operation): Operation
    {
        return $operation;
    }
}
