<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CallRestApiAction
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Models collection.
     *
     * @var \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection
     */
    protected $models;

    /**
     * The action instance.
     *
     * @var \Lomkit\Rest\Actions\Action
     */
    protected \Lomkit\Rest\Actions\Action $action;

    /**
     * The fields for the action instance.
     *
     * @var array
     */
    protected array $fields;

    /**
     * Create a new job instance.
     *
     * @param \Lomkit\Rest\Actions\Action                                             $action
     * @param array                                                                   $fields
     * @param \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection $models
     *
     * @return void
     */
    public function __construct(Action $action, array $fields, Collection $models)
    {
        $this->action = $action;
        $this->fields = $fields;
        $this->models = $models;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->action->setJob($this->job)->handle($this->fields, $this->models);
    }

    /**
     * Get the display name for the queued job.
     *
     * @return string
     */
    public function displayName()
    {
        return get_class($this->action);
    }
}
