<?php

namespace Lomkit\Rest\Http\Controllers\Traits;

use Lomkit\Rest\Documentation\Schemas\Operation;

trait ExtendsDocumentationOperations
{
    /**
     * Extend "detail" documentation operation.
     *
     * @param Operation $operation
     *
     * @return Operation
     */
    public function generateDocumentationDetailOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "search" documentation operation.
     *
     * @param Operation $operation
     *
     * @return Operation
     */
    public function generateDocumentationSearchOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "mutate" documentation operation.
     *
     * @param Operation $operation
     *
     * @return Operation
     */
    public function generateDocumentationMutateOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "actions" documentation operation.
     *
     * @param Operation $operation
     *
     * @return Operation
     */
    public function generateDocumentationActionsOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "destroy" documentation operation.
     *
     * @param Operation $operation
     *
     * @return Operation
     */
    public function generateDocumentationDestroyOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "restore" documentation operation.
     *
     * @param Operation $operation
     *
     * @return Operation
     */
    public function generateDocumentationRestoreOperation(Operation $operation): Operation
    {
        return $operation;
    }

    /**
     * Extend "forceDelete" documentation operation.
     *
     * @param Operation $operation
     *
     * @return Operation
     */
    public function generateDocumentationForceDeleteOperation(Operation $operation): Operation
    {
        return $operation;
    }
}
