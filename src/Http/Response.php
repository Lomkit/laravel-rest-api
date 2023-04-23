<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

class Response implements Responsable
{
    protected $responsable;

    public function responsable($responsable) {
        return tap($this, function () use ($responsable) {
            $this->responsable = $responsable;
        });
    }

    public function toResponse($request) {
        if ($this->responsable instanceof LengthAwarePaginator) {
            return $this->responsable->through(function ($model) {
                return $this->map($model);
            });
        }

        return $this->map($this->responsable);
    }

    protected function map(Model $model) {
        return $model;
    }
}