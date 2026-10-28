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
     * @var \Illuminate\Database\Eloquent\Collection|Collection
     */
    protected $models;

    /**
     * The action instance.
     */
    protected Action $action;

    /**
     * The resolved field values, keyed by field name.
     *
     * @var array<string, mixed>
     */
    protected array $fields;

    /**
     * Create a new job instance.
     *
     * @param  array<string, mixed>  $fields
     * @param  \Illuminate\Database\Eloquent\Collection|Collection  $models
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
